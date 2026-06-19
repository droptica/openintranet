<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

/**
 * Tests the create ECA action.
 *
 * @group openintranet_notifications
 */
final class CreateActionTest extends NotificationActionKernelTestBase {

  /**
   * Creates and saves a single notification, writing its id to a token.
   */
  public function testCreateSavesNotificationAndWritesIdToken(): void {
    $action = $this->actionManager->createInstance('openintranet_notifications_create', [
      'notification_type' => 'default',
      'uid' => '42',
      'subject' => 'Hi there',
      'body' => 'Body text',
      'token_name' => 'new_notification_id',
    ]);

    $action->execute(NULL);

    $notifications = $this->loadAllNotifications();
    self::assertCount(1, $notifications);
    $notification = reset($notifications);
    self::assertSame(42, (int) $notification->get('uid')->target_id);
    self::assertSame('Hi there', $notification->get('subject')->value);
    self::assertSame('Body text', $notification->get('body')->value);
    self::assertSame('created', $notification->get('status')->value);

    // The new id is written to the configured output token.
    self::assertSame(
      (int) $notification->id(),
      (int) $this->tokenServices->getTokenData('new_notification_id'),
    );
  }

  /**
   * No deliveries or queue items are created (create does not enqueue).
   */
  public function testCreateDoesNotEnqueue(): void {
    $action = $this->actionManager->createInstance('openintranet_notifications_create', [
      'notification_type' => 'default',
      'uid' => '42',
      'subject' => 'Hi',
      'body' => 'B',
      'token_name' => 'nid',
    ]);

    $action->execute(NULL);

    $notification = reset($this->loadAllNotifications());
    self::assertCount(0, $this->loadDeliveriesFor((int) $notification->id()));
    self::assertSame(0, $this->queueCount());
  }

}
