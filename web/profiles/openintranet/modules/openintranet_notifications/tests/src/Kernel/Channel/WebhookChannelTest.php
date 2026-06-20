<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\openintranet_notifications\Channel\NotificationChannelInterface;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the webhook channel send path: HMAC signing and error classification.
 *
 * The Guzzle http_client is replaced with a Client built on a MockHandler queue
 * so no real network call is made; a history middleware captures the outgoing
 * request to assert the HMAC signature header.
 *
 * @group openintranet_notifications
 */
final class WebhookChannelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'dynamic_entity_reference',
    'options',
    'text',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'openintranet_notifications',
  ];

  /**
   * Captured outgoing requests from the mocked Guzzle client.
   *
   * @var array
   */
  private array $history = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['openintranet_notifications']);
  }

  /**
   * Installs a mocked http_client whose queue answers with the given responses.
   *
   * @param array $queue
   *   A queue of Response/Throwable the MockHandler returns in order.
   */
  private function mockHttpClient(array $queue): void {
    $handler = HandlerStack::create(new MockHandler($queue));
    $this->history = [];
    $handler->push(Middleware::history($this->history));
    $this->container->set('http_client', new Client(['handler' => $handler]));
  }

  /**
   * Sets the webhook channel config.
   */
  private function setWebhookConfig(array $values): void {
    $this->config('openintranet_notifications.channel.webhook')
      ->setData($values + [
        'url' => '',
        'key_id' => '',
        'timeout' => 10,
        'signature_header' => 'X-Signature',
      ])
      ->save();
  }

  /**
   * Instantiates the channel under test.
   */
  private function channel(): NotificationChannelInterface {
    /** @var \Drupal\openintranet_notifications\Channel\ChannelPluginManager $manager */
    $manager = $this->container->get('plugin.manager.notification_channel');
    $channel = $manager->createInstance('webhook');
    self::assertInstanceOf(NotificationChannelInterface::class, $channel);
    return $channel;
  }

  /**
   * A recipient addressed at the configured webhook endpoint.
   */
  private function recipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'endpoint', value: 'https://hook.example.com/in', langcode: 'en');
  }

  /**
   * A simple message.
   */
  private function message(): NotificationMessage {
    return new NotificationMessage(subject: 'Subj', body: 'Body', summary: 'Sum', payload: ['k' => 'v']);
  }

  /**
   * A 2xx response classifies as success and carries the provider message id.
   */
  public function testHttp200IsSuccess(): void {
    $this->setWebhookConfig(['url' => 'https://hook.example.com/in']);
    $this->mockHttpClient([new Response(200, ['X-Message-Id' => 'prov-123'])]);

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertTrue($result->success);
    self::assertSame('prov-123', $result->providerMessageId);
  }

  /**
   * A 500 response classifies as a retryable HTTP_500 failure.
   */
  public function testHttp500IsRetryable(): void {
    $this->setWebhookConfig(['url' => 'https://hook.example.com/in']);
    $this->mockHttpClient([new Response(500)]);

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('HTTP_500', $result->errorCode);
  }

  /**
   * A 429 response classifies as a retryable HTTP_429 failure.
   */
  public function testHttp429IsRetryable(): void {
    $this->setWebhookConfig(['url' => 'https://hook.example.com/in']);
    $this->mockHttpClient([new Response(429)]);

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('HTTP_429', $result->errorCode);
  }

  /**
   * A 404 response classifies as a permanent HTTP_404 failure.
   */
  public function testHttp404IsPermanent(): void {
    $this->setWebhookConfig(['url' => 'https://hook.example.com/in']);
    $this->mockHttpClient([new Response(404)]);

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('HTTP_404', $result->errorCode);
  }

  /**
   * A connection error classifies as a retryable NETWORK failure.
   */
  public function testConnectExceptionIsRetryable(): void {
    $this->setWebhookConfig(['url' => 'https://hook.example.com/in']);
    $this->mockHttpClient([
      new ConnectException('Connection refused', new Request('POST', 'https://hook.example.com/in')),
    ]);

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertTrue($result->retryable);
    self::assertSame('NETWORK', $result->errorCode);
  }

  /**
   * With no url configured and a non-endpoint recipient, send is NO_URL.
   */
  public function testNoUrlIsPermanentFailure(): void {
    $this->setWebhookConfig(['url' => '']);
    $this->mockHttpClient([new Response(200)]);

    $recipient = new NotificationRecipient(type: 'user', id: 1, langcode: 'en');
    $result = $this->channel()->send($recipient, $this->message());

    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_URL', $result->errorCode);
  }

  /**
   * A configured key_id that does not resolve to a key is a permanent NO_KEY.
   */
  public function testMissingKeyIsPermanentFailure(): void {
    $this->setWebhookConfig([
      'url' => 'https://hook.example.com/in',
      'key_id' => 'does_not_exist',
    ]);
    $this->mockHttpClient([new Response(200)]);

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertFalse($result->success);
    self::assertFalse($result->retryable);
    self::assertSame('NO_KEY', $result->errorCode);
  }

  /**
   * A configured HMAC key signs the exact sent body under the chosen header.
   */
  public function testHmacSignatureMatchesSentBody(): void {
    $secret = 'top-secret-shared-key';
    $this->createConfigKey('webhook_hmac', $secret);

    $this->setWebhookConfig([
      'url' => 'https://hook.example.com/in',
      'key_id' => 'webhook_hmac',
      'signature_header' => 'X-Hub-Signature',
    ]);
    $this->mockHttpClient([new Response(200)]);

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertTrue($result->success);
    self::assertCount(1, $this->history);

    /** @var \Psr\Http\Message\RequestInterface $request */
    $request = $this->history[0]['request'];
    $sentBody = (string) $request->getBody();
    $expected = hash_hmac('sha256', $sentBody, $secret);

    self::assertSame($expected, $request->getHeaderLine('X-Hub-Signature'));
    self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    // The signed body is the exact JSON payload carrying message + recipient.
    $decoded = json_decode($sentBody, TRUE);
    self::assertSame('Subj', $decoded['subject']);
    self::assertSame('endpoint', $decoded['recipient']['type']);
  }

  /**
   * Without a key_id no signature header is sent.
   */
  public function testNoKeyIdSendsNoSignatureHeader(): void {
    $this->setWebhookConfig([
      'url' => 'https://hook.example.com/in',
      'key_id' => '',
      'signature_header' => 'X-Signature',
    ]);
    $this->mockHttpClient([new Response(200)]);

    $result = $this->channel()->send($this->recipient(), $this->message());

    self::assertTrue($result->success);
    self::assertCount(1, $this->history);
    /** @var \Psr\Http\Message\RequestInterface $request */
    $request = $this->history[0]['request'];
    self::assertFalse($request->hasHeader('X-Signature'));
  }

  /**
   * Creates a config-stored Key entity with a known plaintext secret.
   */
  private function createConfigKey(string $id, string $secret): void {
    Key::create([
      'id' => $id,
      'label' => 'Webhook HMAC',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_provider_settings' => [
        'key_value' => $secret,
        'base64_encoded' => FALSE,
      ],
      'key_input' => 'text_field',
    ])->save();
  }

}
