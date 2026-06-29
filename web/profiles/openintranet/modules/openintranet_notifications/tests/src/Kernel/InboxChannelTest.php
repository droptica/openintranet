<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Channel\NotificationChannelInterface;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;

/**
 * Tests the inbox channel plugin.
 *
 * @group openintranet_notifications
 */
final class InboxChannelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'text',
    'token',
    'key',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * The inbox channel under test.
   */
  private NotificationChannelInterface $channel;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->channel = $this->container
      ->get('plugin.manager.openintranet_notification_channel')
      ->createInstance('inbox');
  }

  /**
   * The inbox channel is discovered and available.
   */
  public function testChannelIsDiscoveredAndAvailable(): void {
    self::assertSame('inbox', $this->channel->getId());
    self::assertTrue($this->channel->isAvailable());
  }

  /**
   * The send() call is a no-op success: the notification entity is the record.
   */
  public function testSendReturnsSuccess(): void {
    $recipient = new NotificationRecipient(type: 'user', id: 42);
    $message = new NotificationMessage(subject: 'Hi', body: 'Body');
    $result = $this->channel->send($recipient, $message);
    self::assertTrue($result->success);
    self::assertFalse($result->retryable);
  }

  /**
   * The canSendTo() call is TRUE only for user recipients with an id.
   */
  public function testCanSendToOnlyUsers(): void {
    self::assertTrue($this->channel->canSendTo(new NotificationRecipient(type: 'user', id: 42)));
    self::assertFalse($this->channel->canSendTo(new NotificationRecipient(type: 'email', value: 'x@y.test')));
  }

}
