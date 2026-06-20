<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_smsapi\Plugin\NotificationChannel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\smsapi\Services\SmsapiServiceInterface;
use Smsapi\Client\Feature\Sms\Data\Sms;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends notifications as SMS via the smsapi integration (smsapi.service).
 *
 * Addresses phone-type recipients by their raw value and users by a configured
 * phone field. smsapi owns all token/region/sender/dev/mock configuration; this
 * channel only forwards the message and an optional default sender. The service
 * never throws — it returns an Sms on success and NULL on failure (logging to
 * its own channel), which this channel classifies into a DeliveryResult.
 */
#[NotificationChannel(
  id: 'sms_smsapi',
  label: new TranslatableMarkup('SMS (SMSAPI)'),
  description: new TranslatableMarkup('Sends the message as an SMS via the SMSAPI integration.'),
)]
final class SmsSmsApiChannel extends NotificationChannelBase {

  /**
   * Maximum SMS text length forwarded to smsapi (multi-part concatenated SMS).
   */
  private const MAX_SMS_LENGTH = 640;

  /**
   * Constructs a SmsSmsApiChannel.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\smsapi\Services\SmsapiServiceInterface $smsapi
   *   The smsapi service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly SmsapiServiceInterface $smsapi,
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
      $container->get('smsapi.service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'sender' => '',
      'phone_field' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    if ($recipient->type === 'phone') {
      return ($recipient->value !== NULL && $recipient->value !== '') ? $recipient->value : NULL;
    }

    if ($recipient->isUser() && $recipient->account instanceof FieldableEntityInterface) {
      $field = (string) ($this->configuration['phone_field'] ?? '');
      if ($field === '' || !$recipient->account->hasField($field)) {
        return NULL;
      }
      $value = $recipient->account->get($field)->value;
      return ($value !== NULL && $value !== '') ? (string) $value : NULL;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // SMSAPI owns availability (token/region/dev/mock are its own config).
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $phone = $this->getRecipientAddress($recipient);
    if ($phone === NULL) {
      return DeliveryResult::permanentFailure('NO_PHONE', 'No phone number for recipient.');
    }

    $text = $message->getSummaryOrTruncatedBody(self::MAX_SMS_LENGTH);

    try {
      $sms = $this->smsapi->sendSms($phone, $text, (string) ($this->configuration['sender'] ?? ''));
    }
    catch (\Throwable $e) {
      return DeliveryResult::retryableFailure('SMSAPI_EXCEPTION', $e->getMessage());
    }

    if ($sms instanceof Sms) {
      return DeliveryResult::success((string) $sms->id);
    }
    return DeliveryResult::retryableFailure('SMSAPI_NULL', 'SMSAPI send failed (see smsapi log).');
  }

}
