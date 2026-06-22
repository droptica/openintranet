<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Event\NotificationCreatedEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationQueuedEvent;
use Drupal\openintranet_notifications\Service\NotificationDispatcher;
use Drupal\openintranet_notifications\Service\NotificationFactory;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

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
    // Tests install their own "default" type fixture, so drop the types
    // shipped in config/install first.
    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());

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

  /**
   * An empty recipient set resolves recipients via the type's resolvers.
   */
  public function testDispatchRequestResolvesRecipientsFromTypeResolvers(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    foreach ([41, 42] as $uid) {
      $user = User::load($uid);
      $user->addRole('editor');
      $user->save();
    }

    NotificationType::create([
      'id' => 'resolved',
      'label' => 'Resolved',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'recipient_resolvers' => [
        ['id' => 'role_users', 'configuration' => ['role' => 'editor']],
      ],
    ])->save();

    $this->dispatcher->dispatchRequest('resolved', [], ['subject' => 'Hi', 'body' => 'B']);

    $notifications = $this->loadAllNotifications();
    self::assertCount(2, $notifications);
    $uids = array_map(static fn ($n) => (int) $n->get('uid')->target_id, $notifications);
    sort($uids);
    self::assertSame([41, 42], $uids);
  }

  /**
   * Created and queued events fire once per recipient on a successful send.
   */
  public function testCreatedAndQueuedEventsFirePerRecipient(): void {
    $created = 0;
    $queued = 0;
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(NotificationEvents::CREATED, function (NotificationCreatedEvent $event) use (&$created): void {
      $created++;
    });
    $dispatcher->addListener(NotificationEvents::QUEUED, function (NotificationQueuedEvent $event) use (&$queued): void {
      $queued++;
    });

    $this->dispatcher->dispatchRequest('default', [41, 42, 43], ['subject' => 'Hi', 'body' => 'B']);

    self::assertSame(3, $created);
    self::assertSame(3, $queued);
  }

  /**
   * A digest_only dispatch persists, is not cancelled, fires CREATED, digests.
   *
   * The digest_only policy legitimately selects no immediate channel; the empty
   * set is the policy's INTENDED outcome, so the notification must persist
   * (status NOT cancelled), the CREATED event must fire, and the DigestBuilder
   * must still pick it up (digested IS NULL).
   */
  public function testDigestOnlyPersistsNotCancelledAndStaysDigestable(): void {
    $created = 0;
    $this->container->get('event_dispatcher')->addListener(
      NotificationEvents::CREATED,
      function () use (&$created): void {
        $created++;
      },
    );

    NotificationType::create([
      'id' => 'digest',
      'label' => 'Digest',
      'delivery_policy' => 'digest_only',
      'dedupe_window' => 0,
    ])->save();

    $n = $this->factory->create('digest', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $n->save();
    $this->dispatcher->enqueue($n);

    self::assertNotSame('cancelled', $this->reload((int) $n->id())->get('status')->value);
    self::assertCount(0, $this->loadDeliveriesFor((int) $n->id()));
    self::assertSame(1, $created);

    // The DigestBuilder still finds it (not digested, not cancelled-out).
    $dispatched = $this->container->get('openintranet_notifications.digest_builder')->buildAndDispatch();
    self::assertSame(1, $dispatched);
    self::assertTrue($this->reload((int) $n->id())->isDigested());
  }

  /**
   * A silent_audit_only dispatch persists, is not cancelled and fires CREATED.
   *
   * The silent_audit_only policy records an audit entry with no channel; it is
   * not a cancellation. The notification persists, the CREATED event fires,
   * there are no deliveries, and the status is the terminal audit state
   * 'delivered' (audit recorded, nothing to send).
   */
  public function testSilentAuditPersistsNotCancelledAndFiresCreated(): void {
    $created = 0;
    $this->container->get('event_dispatcher')->addListener(
      NotificationEvents::CREATED,
      function () use (&$created): void {
        $created++;
      },
    );

    NotificationType::create([
      'id' => 'audit',
      'label' => 'Audit',
      'delivery_policy' => 'silent_audit_only',
      'dedupe_window' => 0,
    ])->save();

    $n = $this->factory->create('audit', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $n->save();
    $this->dispatcher->enqueue($n);

    self::assertSame('delivered', $this->reload((int) $n->id())->get('status')->value);
    self::assertCount(0, $this->loadDeliveriesFor((int) $n->id()));
    self::assertSame(1, $created);
  }

  /**
   * A disabled type produces no notification, no deliveries and no queue items.
   *
   * The entity `enabled` flag is the dispatch gate: a disabled type bails
   * before anything dispatches, so disabling a type takes effect (FIX 3).
   */
  public function testDisabledTypeProducesNothing(): void {
    NotificationType::create([
      'id' => 'off',
      'label' => 'Off',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'enabled' => FALSE,
    ])->save();

    $n = $this->factory->create('off', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $n->save();
    $this->dispatcher->enqueue($n);

    self::assertCount(0, $this->loadDeliveriesFor((int) $n->id()));
    self::assertSame(0, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
    // The notification it left behind is not 'queued' (it never dispatched).
    self::assertNotSame('queued', $this->reload((int) $n->id())->get('status')->value);
  }

  /**
   * An enabled type dispatches normally (the gate does not over-block).
   */
  public function testEnabledTypeStillDispatches(): void {
    $n = $this->factory->create('default', ['uid' => 42, 'subject' => 'Hi', 'body' => 'B']);
    $n->save();
    $this->dispatcher->enqueue($n);

    self::assertSame('queued', $this->reload((int) $n->id())->get('status')->value);
    self::assertCount(2, $this->loadDeliveriesFor((int) $n->id()));
  }

  /**
   * The blocked/empty-channel cancel path fires neither created nor queued.
   */
  public function testNoEventsOnBlockedRecipientPath(): void {
    $created = 0;
    $queued = 0;
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(NotificationEvents::CREATED, function () use (&$created): void {
      $created++;
    });
    $dispatcher->addListener(NotificationEvents::QUEUED, function () use (&$queued): void {
      $queued++;
    });

    $blocked = User::load(43);
    $blocked->set('status', 0)->save();

    $n = $this->factory->create('default', ['uid' => 43, 'subject' => 'Hi', 'body' => 'B']);
    $n->save();
    $this->dispatcher->enqueue($n);

    self::assertSame('cancelled', $this->reload((int) $n->id())->get('status')->value);
    self::assertSame(0, $created);
    self::assertSame(0, $queued);
  }

}
