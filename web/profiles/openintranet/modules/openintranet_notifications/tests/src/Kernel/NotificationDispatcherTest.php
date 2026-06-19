<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Service\NotificationDispatcher;
use Drupal\openintranet_notifications\Service\NotificationFactory;
use Drupal\user\Entity\User;

/**
 * Tests the NotificationDispatcher orchestration service.
 *
 * @group openintranet_notifications
 */
final class NotificationDispatcherTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'openintranet_notifications',
    'openintranet_notifications_test',
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
   * The notification factory.
   */
  private NotificationFactory $factory;

  /**
   * The dispatcher under test.
   */
  private NotificationDispatcher $dispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['openintranet_notifications']);

    // A type whose policy selects [inbox, log_only] for any user.
    NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 600,
    ])->save();

    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['inbox', 'log_only'])
      ->save();

    foreach ([41, 42, 43] as $uid) {
      User::create(['uid' => $uid, 'name' => 'user' . $uid, 'status' => 1])->save();
    }

    $this->factory = $this->container->get('openintranet_notifications.notification_factory');
    $this->dispatcher = $this->container->get('openintranet_notifications.notification_dispatcher');
  }

  /**
   * Reloads a notification fresh from storage.
   */
  private function reload(int $id): object {
    $storage = $this->container->get('entity_type.manager')->getStorage('openintranet_notification');
    $storage->resetCache([$id]);
    return $storage->load($id);
  }

  /**
   * Loads every delivery row for a notification.
   *
   * @return array<int, object>
   *   The delivery entities.
   */
  private function loadDeliveriesFor(int $notificationId): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('openintranet_notif_delivery');
    return $storage->loadByProperties(['notification_id' => $notificationId]);
  }

  /**
   * Loads every notification entity.
   *
   * @return array<int, object>
   *   The notification entities.
   */
  private function loadAllNotifications(): array {
    return $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->loadMultiple();
  }

  /**
   * Enqueue persists the notification, creates deliveries and queues them.
   */
  public function testEnqueueSingleNotificationCreatesDeliveriesAndQueues(): void {
    $n = $this->factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $n->save();

    $this->dispatcher->enqueue($n);

    self::assertSame('queued', $this->reload((int) $n->id())->get('status')->value);
    self::assertCount(2, $this->loadDeliveriesFor((int) $n->id()));
    self::assertSame(2, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

  /**
   * Dispatch request fans out to one notification entity per recipient.
   */
  public function testDispatchRequestFansOutOnePerRecipient(): void {
    $this->dispatcher->dispatchRequest('default', [41, 42, 43], ['subject' => 'Hi', 'body' => 'B']);

    self::assertCount(3, $this->loadAllNotifications());
  }

  /**
   * A second identical dispatch within the window is deduplicated.
   *
   * The dedupe must suppress deliveries and queue items too, not merely the
   * notification count (FIX #13).
   */
  public function testDedupeSkipsSecondIdenticalWithinWindow(): void {
    $this->dispatcher->dispatchRequest('default', [42], ['subject' => 'Hi', 'body' => 'B']);

    $notifications = $this->loadAllNotifications();
    self::assertCount(1, $notifications);
    $first = reset($notifications);
    self::assertCount(2, $this->loadDeliveriesFor((int) $first->id()));
    self::assertSame(2, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());

    $this->dispatcher->dispatchRequest('default', [42], ['subject' => 'Hi', 'body' => 'B']);

    self::assertCount(1, $this->loadAllNotifications());
    self::assertCount(2, $this->loadDeliveriesFor((int) $first->id()));
    self::assertSame(2, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

  /**
   * A blocked recipient (policy returns []) cancels the notification.
   *
   * No deliveries, no queue items, and dedupe must NOT be recorded so a later
   * non-blocked dispatch with the same key still goes through (FIX #4).
   */
  public function testBlockedRecipientCancelsAndDoesNotRecordDedupe(): void {
    $blocked = User::load(43);
    $blocked->set('status', 0)->save();

    $n = $this->factory->create('default', ['uid' => 43, 'subject' => 'Hi', 'body' => 'B']);
    $n->save();
    $dedupeKey = (string) $n->get('dedupe_key')->value;
    $this->dispatcher->enqueue($n);

    self::assertSame('cancelled', $this->reload((int) $n->id())->get('status')->value);
    self::assertCount(0, $this->loadDeliveriesFor((int) $n->id()));
    self::assertSame(0, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());

    // The dedupe key was not recorded: a non-blocked dispatch with the same key
    // still produces deliveries.
    $blocked->set('status', 1)->save();
    $n2 = $this->factory->create('default', ['uid' => 43, 'subject' => 'Hi', 'body' => 'B']);
    $n2->set('dedupe_key', $dedupeKey);
    $n2->save();
    $this->dispatcher->enqueue($n2);

    self::assertSame('queued', $this->reload((int) $n2->id())->get('status')->value);
    self::assertCount(2, $this->loadDeliveriesFor((int) $n2->id()));
  }

}
