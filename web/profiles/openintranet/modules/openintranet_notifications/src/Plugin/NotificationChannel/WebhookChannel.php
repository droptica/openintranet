<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationChannel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\key\KeyRepositoryInterface;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Posts the rendered message as JSON to a configured webhook endpoint.
 *
 * The payload is optionally signed with an HMAC-SHA256 of the exact request
 * body, using a shared secret stored in the Key module (config holds only the
 * key id, never the secret). Transport outcomes are classified — never thrown —
 * so the queue worker decides whether to retry (00-synteza §3.1/§8).
 */
#[NotificationChannel(
  id: 'webhook',
  label: new TranslatableMarkup('Webhook'),
  description: new TranslatableMarkup('Posts the message as a signed JSON payload to a configured HTTP endpoint.'),
)]
final class WebhookChannel extends NotificationChannelBase {

  private const CONFIG_NAME = 'openintranet_notifications.channel.webhook';

  /**
   * Constructs a WebhookChannel.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The Guzzle HTTP client.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The Key module repository for the HMAC secret.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The module logger channel.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly ClientInterface $httpClient,
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('key.repository'),
      $container->get('config.factory'),
      $container->get('logger.channel.openintranet_notifications'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return $this->configuredUrl() !== '';
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    $configUrl = $this->configuredUrl();
    if ($recipient->type === 'endpoint' || $recipient->type === 'external') {
      // An explicit endpoint value wins; otherwise fall back to the global url.
      return ($recipient->value !== NULL && $recipient->value !== '') ? $recipient->value : ($configUrl ?: NULL);
    }
    // Any other recipient posts to the configured endpoint when one is set.
    return $configUrl ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $url = $this->getRecipientAddress($recipient);
    if ($url === NULL || $url === '') {
      return DeliveryResult::permanentFailure('NO_URL', 'No webhook URL configured.');
    }

    $payload = [
      'subject' => $message->subject,
      'body' => $message->body,
      'summary' => $message->summary,
      'payload' => $message->payload,
      'recipient' => [
        'type' => $recipient->type,
        'id' => $recipient->id,
        'value' => $recipient->value,
      ],
    ];
    $json = json_encode($payload);

    $config = $this->configFactory->get(self::CONFIG_NAME);
    $headers = ['Content-Type' => 'application/json'];

    $keyId = (string) $config->get('key_id');
    if ($keyId !== '') {
      $key = $this->keyRepository->getKey($keyId);
      if ($key === NULL) {
        $this->logger->error('Webhook HMAC key %id is configured but not found.', ['%id' => $keyId]);
        return DeliveryResult::permanentFailure('NO_KEY', 'Configured HMAC key not found.');
      }
      $signatureHeader = (string) ($config->get('signature_header') ?: 'X-Signature');
      $headers[$signatureHeader] = hash_hmac('sha256', $json, $key->getKeyValue());
    }

    $timeout = (int) ($config->get('timeout') ?: 10);

    try {
      $response = $this->httpClient->request('POST', $url, [
        'body' => $json,
        'headers' => $headers,
        'timeout' => $timeout,
        'http_errors' => FALSE,
      ]);
    }
    catch (ConnectException $e) {
      return DeliveryResult::retryableFailure('NETWORK', $e->getMessage());
    }
    catch (\Throwable $e) {
      return DeliveryResult::retryableFailure('HTTP_EXCEPTION', $e->getMessage());
    }

    $code = $response->getStatusCode();
    if ($code >= 200 && $code < 300) {
      $providerId = $response->getHeaderLine('X-Message-Id');
      return DeliveryResult::success($providerId !== '' ? $providerId : NULL);
    }
    if ($code === 429 || $code >= 500) {
      return DeliveryResult::retryableFailure("HTTP_$code", "Webhook returned HTTP $code.");
    }
    return DeliveryResult::permanentFailure("HTTP_$code", "Webhook returned HTTP $code.");
  }

  /**
   * The configured endpoint URL, or an empty string when unset.
   */
  private function configuredUrl(): string {
    return (string) $this->configFactory->get(self::CONFIG_NAME)->get('url');
  }

}
