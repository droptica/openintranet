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
    // Create a first notification so the asserted id below is not 1: that
    // proves the token round-trip carries the real id rather than passing
    // coincidentally on the first kernel id.
    $this->actionManager->createInstance('openintranet_notifications_create', [
      'notification_type' => 'default',
      'uid' => '41',
      'subject' => 'First',
      'body' => 'First body',
      'token_name' => 'first_id',
    ])->execute(NULL);

    $action = $this->actionManager->createInstance('openintranet_notifications_create', [
      'notification_type' => 'default',
      'uid' => '42',
      'subject' => 'Hi there',
      'body' => 'Body text',
      'token_name' => 'new_notification_id',
    ]);

    $action->execute(NULL);

    $notifications = $this->loadAllNotifications();
    self::assertCount(2, $notifications);
    $notification = end($notifications);
    self::assertSame(42, (int) $notification->get('uid')->target_id);
    self::assertSame('Hi there', $notification->get('subject')->value);
    self::assertSame('Body text', $notification->get('body')->value);
    self::assertSame('created', $notification->get('status')->value);

    // The new id is written to the configured output token. Read it via the
    // string representation: ECA wraps the stored scalar in a DTO, so casting
    // to (int) would warn and collapse to 1; the string round-trip exercises
    // the real value for any id.
    self::assertGreaterThan(1, (int) $notification->id());
    self::assertSame(
      (string) $notification->id(),
      (string) $this->tokenServices->getOrReplace('[new_notification_id]'),
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

    $notifications = $this->loadAllNotifications();
    $notification = reset($notifications);
    self::assertCount(0, $this->loadDeliveriesFor((int) $notification->id()));
    self::assertSame(0, $this->queueCount());
  }

}
