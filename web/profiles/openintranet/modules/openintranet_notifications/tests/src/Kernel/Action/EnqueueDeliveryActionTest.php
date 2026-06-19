<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

/**
 * Tests the enqueue_delivery ECA action.
 *
 * @group openintranet_notifications
 */
final class EnqueueDeliveryActionTest extends NotificationActionKernelTestBase {

  /**
   * Enqueues deliveries for a previously created notification.
   */
  public function testEnqueuesDeliveriesForNotification(): void {
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $notification->save();
    $this->tokenServices->addTokenData('notification', $notification);

    $action = $this->actionManager->createInstance('openintranet_notifications_enqueue_delivery', [
      'notification' => '[notification]',
      'recipients' => '',
    ]);

    $action->execute(NULL);

    self::assertSame('queued', $this->reloadNotification((int) $notification->id())->get('status')->value);
    self::assertCount(2, $this->loadDeliveriesFor((int) $notification->id()));
    self::assertSame(2, $this->queueCount());
  }

  /**
   * A missing notification token is a no-op.
   */
  public function testMissingNotificationIsNoop(): void {
    $action = $this->actionManager->createInstance('openintranet_notifications_enqueue_delivery', [
      'notification' => '[notification]',
      'recipients' => '',
    ]);

    $action->execute(NULL);

    self::assertCount(0, $this->loadAllNotifications());
    self::assertSame(0, $this->queueCount());
  }

}
