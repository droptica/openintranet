<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

/**
 * Tests the send_now ECA action.
 *
 * @group openintranet_notifications
 */
final class SendNowActionTest extends NotificationActionKernelTestBase {

  /**
   * Sends synchronously on a channel and marks the delivery sent.
   */
  public function testSendNowMarksDeliverySent(): void {
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $notification->save();
    $this->tokenServices->addTokenData('notification', $notification);

    $action = $this->actionManager->createInstance('openintranet_notifications_send_now', [
      'notification' => '[notification]',
      'channel' => 'log_only',
    ]);

    $action->execute(NULL);

    $deliveries = $this->loadDeliveriesFor((int) $notification->id());
    self::assertCount(1, $deliveries);
    $delivery = reset($deliveries);
    self::assertSame('log_only', $delivery->get('channel')->value);
    self::assertSame('sent', $delivery->get('status')->value);

    // Synchronous send: nothing is left on the queue.
    self::assertSame(0, $this->queueCount());
  }

  /**
   * A missing notification token is a no-op.
   */
  public function testMissingNotificationIsNoop(): void {
    $action = $this->actionManager->createInstance('openintranet_notifications_send_now', [
      'notification' => '[notification]',
      'channel' => 'log_only',
    ]);

    $action->execute(NULL);

    self::assertCount(0, $this->loadAllNotifications());
  }

}
