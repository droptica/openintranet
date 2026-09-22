<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

use Drupal\openintranet_notifications_test\Plugin\NotificationChannel\CountingChannel;

/**
 * Tests the send_now ECA action.
 *
 * @group openintranet_notifications
 */
final class SendNowActionTest extends NotificationActionKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Adds the test channels (counting/unavailable_stub) so the idempotency and
   * availability guards SendNow now shares with the worker can be exercised.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'dynamic_entity_reference',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'node',
    'openintranet_notifications',
    'openintranet_notifications_test',
  ];

  /**
   * The current CountingChannel send counter.
   */
  private function countingSends(): int {
    return (int) \Drupal::state()->get(CountingChannel::STATE_KEY, 0);
  }

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

  /**
   * SendNow sends exactly once and rolls the parent up to delivered.
   *
   * Proves SendNow now goes through the shared sender's send + classify +
   * status roll-up (the action never rolled up the parent before).
   */
  public function testSendNowSendsOnceAndRollsUpParent(): void {
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $notification->save();
    $this->tokenServices->addTokenData('notification', $notification);

    $action = $this->actionManager->createInstance('openintranet_notifications_send_now', [
      'notification' => '[notification]',
      'channel' => 'counting',
    ]);
    $action->execute(NULL);

    self::assertSame(1, $this->countingSends());
    $deliveries = $this->loadDeliveriesFor((int) $notification->id());
    self::assertCount(1, $deliveries);
    self::assertSame('sent', reset($deliveries)->get('status')->value);
    // The shared sender rolled the parent notification up from its rows.
    self::assertSame('delivered', $this->reloadNotification((int) $notification->id())->get('status')->value);
  }

  /**
   * SendNow on an already-sent delivery does NOT re-send (idempotency).
   *
   * The first call sends and makes the row terminal; a second call rebuilds the
   * SAME idempotency-keyed row, and the shared sender's terminal/claim guards
   * stop it from sending again.
   */
  public function testSendNowIsIdempotentOnAlreadySentDelivery(): void {
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $notification->save();
    $this->tokenServices->addTokenData('notification', $notification);

    $action = $this->actionManager->createInstance('openintranet_notifications_send_now', [
      'notification' => '[notification]',
      'channel' => 'counting',
    ]);

    $action->execute(NULL);
    self::assertSame(1, $this->countingSends());

    // A second SendNow for the same notification/channel must not send again.
    $action->execute(NULL);
    self::assertSame(1, $this->countingSends());
  }

  /**
   * SendNow on an unavailable channel skips the send (availability gate).
   */
  public function testSendNowSkipsUnavailableChannel(): void {
    $factory = $this->container->get('openintranet_notifications.notification_factory');
    $notification = $factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $notification->save();
    $this->tokenServices->addTokenData('notification', $notification);

    $action = $this->actionManager->createInstance('openintranet_notifications_send_now', [
      'notification' => '[notification]',
      'channel' => 'unavailable_stub',
    ]);
    $action->execute(NULL);

    $deliveries = $this->loadDeliveriesFor((int) $notification->id());
    self::assertCount(1, $deliveries);
    self::assertSame('skipped', reset($deliveries)->get('status')->value);
  }

}
