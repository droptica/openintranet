<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

/**
 * Tests the cancel ECA action.
 *
 * @group openintranet_notifications
 */
final class CancelActionTest extends NotificationActionKernelTestBase {

  /**
   * Cancels the notification and its pending/processing deliveries.
   */
  public function testCancelsNotificationAndPendingDeliveries(): void {
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $notification->save();
    $this->container->get('openintranet_notifications.notification_dispatcher')->enqueue($notification);

    $deliveryStorage = $this->container->get('entity_type.manager')->getStorage('openintranet_notif_delivery');
    $deliveries = $this->loadDeliveriesFor((int) $notification->id());
    self::assertCount(2, $deliveries);
    // Make one delivery already sent (terminal) so it must NOT be cancelled.
    $sent = reset($deliveries);
    $sent->set('status', 'sent')->save();

    $this->tokenServices->addTokenData('notification', $notification);
    $action = $this->actionManager->createInstance('openintranet_notifications_cancel', [
      'notification' => '[notification]',
    ]);

    $action->execute(NULL);

    self::assertSame('cancelled', $this->reloadNotification((int) $notification->id())->get('status')->value);

    $deliveryStorage->resetCache();
    $statuses = [];
    foreach ($this->loadDeliveriesFor((int) $notification->id()) as $delivery) {
      $statuses[] = $delivery->get('status')->value;
    }
    sort($statuses);
    // One stays 'sent' (terminal), the pending one becomes 'cancelled'.
    self::assertSame(['cancelled', 'sent'], $statuses);
  }

}
