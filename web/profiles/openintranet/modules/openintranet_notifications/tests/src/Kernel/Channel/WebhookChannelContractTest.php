<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Channel;

use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Channel contract conformance for the webhook channel.
 *
 * The channel addresses any endpoint recipient once a url is configured, so it
 * declares no unaddressable recipient. The failing scenario drives a mocked 4xx
 * response so send() must classify it as a failure without throwing.
 *
 * @group openintranet_notifications
 */
final class WebhookChannelContractTest extends ChannelContractTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['openintranet_notifications']);
    // A url makes the channel available and able to address recipients.
    $this->config('openintranet_notifications.channel.webhook')
      ->set('url', 'https://hook.example.com/in')
      ->save();
    // Replace the http_client so the failing scenario hits a mocked 400, never
    // the network. The queue answers each send() with one 400.
    $handler = HandlerStack::create(new MockHandler([
      new Response(400),
      new Response(400),
    ]));
    $this->container->set('http_client', new Client(['handler' => $handler]));
  }

  /**
   * {@inheritdoc}
   */
  protected function channelId(): string {
    return 'webhook';
  }

  /**
   * {@inheritdoc}
   */
  protected function addressableRecipient(): NotificationRecipient {
    return new NotificationRecipient(type: 'endpoint', value: 'https://hook.example.com/in', langcode: 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function failingScenario(): ?array {
    return [
      $this->addressableRecipient(),
      new NotificationMessage(subject: 'Contract subject', body: 'Body'),
    ];
  }

}
