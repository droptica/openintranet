<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Service\Deduplicator;
use Drupal\openintranet_notifications\Service\NotificationDispatcher;
use Drupal\openintranet_notifications\Service\NotificationFactory;
use Drupal\openintranet_notifications\Service\RateLimiter;
use Drupal\user\Entity\User;

/**
 * Tests the per-(user, type) rate limit enforced at dispatch time (§8).
 *
 * The dispatcher reads the type's rate_limit/rate_limit_window and, when the
 * limit is positive, caps how many notifications a single user may receive for
 * that type within the window. A limit of 0 means unlimited, so shipped types
 * (which never set rate_limit) are unaffected. The cap is per user per type per
 * window — distinct users and distinct types each have an independent counter.
 *
 * @group openintranet_notifications
 */
final class RateLimitDispatchTest extends KernelTestBase {

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

    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());

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
   * Creates a notification type. Dedupe is off so only the rate limit gates.
   *
   * @param string $id
   *   The type id.
   * @param int $rateLimit
   *   The per-window rate limit (0 = unlimited).
   * @param int $window
   *   The rate-limit window in seconds.
   */
  private function makeType(string $id, int $rateLimit, int $window = 3600): void {
    NotificationType::create([
      'id' => $id,
      'label' => ucfirst($id),
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'rate_limit' => $rateLimit,
      'rate_limit_window' => $window,
    ])->save();
  }

  /**
   * Dispatches one notification of a type to a user (fresh dedupe key).
   */
  private function dispatchOnce(string $typeId, int $uid, int $nonce): void {
    $n = $this->factory->create($typeId, [
      'uid' => $uid,
      'subject' => 'Hi',
      'body' => 'B',
      // A unique dedupe_key per call keeps dedupe out of the picture so the
      // test isolates the rate-limit guard.
      'dedupe_key' => $typeId . ':' . $uid . ':' . $nonce,
    ]);
    $n->save();
    $this->dispatcher->enqueue($n);
  }

  /**
   * Counts notifications that reached the queued state for a user.
   *
   * @return int
   *   The number of queued notifications for the given recipient.
   */
  private function queuedCountFor(int $uid): int {
    $storage = $this->container->get('entity_type.manager')->getStorage('openintranet_notification');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('status', 'queued')
      ->execute();
    return count($ids);
  }

  /**
   * A type with rate_limit=2 caps the 3rd dispatch to the same user.
   *
   * The first two go through (status queued, deliveries created); the third is
   * skipped — no queued notification, no deliveries, no extra queue items.
   */
  public function testThirdDispatchOverLimitIsSkipped(): void {
    $this->makeType('capped', 2);

    $this->dispatchOnce('capped', 42, 1);
    $this->dispatchOnce('capped', 42, 2);
    $this->dispatchOnce('capped', 42, 3);

    self::assertSame(2, $this->queuedCountFor(42), 'Only the first two dispatches reach queued.');
    // The third produced no deliveries: 2 dispatches * 2 channels each.
    self::assertSame(4, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

  /**
   * A type with rate_limit=0 is unlimited; shipped types are unaffected.
   */
  public function testZeroLimitIsUnlimited(): void {
    $this->makeType('uncapped', 0);

    foreach (range(1, 5) as $nonce) {
      $this->dispatchOnce('uncapped', 42, $nonce);
    }

    self::assertSame(5, $this->queuedCountFor(42));
  }

  /**
   * The cap is per user: a second user gets its own independent budget.
   */
  public function testDistinctUsersAreIndependent(): void {
    $this->makeType('capped', 2);

    $this->dispatchOnce('capped', 41, 1);
    $this->dispatchOnce('capped', 41, 2);
    $this->dispatchOnce('capped', 41, 3);
    $this->dispatchOnce('capped', 42, 1);
    $this->dispatchOnce('capped', 42, 2);

    self::assertSame(2, $this->queuedCountFor(41));
    self::assertSame(2, $this->queuedCountFor(42));
  }

  /**
   * Each created delivery records a per-(user, channel, type) usage tick (§8).
   *
   * The dispatcher's per-(user, type) cap is one dimension; §8 also calls for a
   * per-CHANNEL dimension. That dimension is exposed to admins through the
   * below_rate_limit ECA condition, whose peek can only throttle if SOMETHING
   * increments a per-(user, channel, type) counter. Dispatching a notification
   * must record that tick on every channel it fans out to, so after N
   * deliveries on a channel the peek sees N — proving the per-channel dimension
   * is real, not a counter that is forever 0. A different channel is
   * independent.
   */
  public function testEachDeliveryRecordsPerChannelUsage(): void {
    $this->makeType('per_channel', 0);

    // Two channels fan out per dispatch (inbox + log_only); three dispatches.
    $this->dispatchOnce('per_channel', 42, 1);
    $this->dispatchOnce('per_channel', 42, 2);
    $this->dispatchOnce('per_channel', 42, 3);

    $limiter = $this->container->get('openintranet_notifications.rate_limiter');
    \assert($limiter instanceof RateLimiter);

    // The inbox channel saw three sends for (42, inbox, per_channel): the peek
    // at limit 3 is now at-limit (FALSE), proving the counter incremented.
    self::assertFalse($limiter->isWithinLimit(42, 'inbox', 'per_channel', 3));
    // Below three it is over budget too; below four it is still within.
    self::assertFalse($limiter->isWithinLimit(42, 'inbox', 'per_channel', 2));
    self::assertTrue($limiter->isWithinLimit(42, 'inbox', 'per_channel', 4));

    // The log_only channel was also hit three times, independently.
    self::assertFalse($limiter->isWithinLimit(42, 'log_only', 'per_channel', 3));

    // A channel never delivered on is untouched: still at zero.
    self::assertTrue($limiter->isWithinLimit(42, 'email_core', 'per_channel', 1));

    // Recording the per-channel tick must NOT bleed into the dispatcher's
    // per-(user, type) cap on the reserved sentinel: that counter is separate.
    self::assertSame(3, $this->queuedCountFor(42), 'The per-channel record-tick never gates dispatch.');
  }

  /**
   * The cap is per type: two capped types share no counter for one user.
   */
  public function testDistinctTypesAreIndependent(): void {
    $this->makeType('capped_a', 1);
    $this->makeType('capped_b', 1);

    $this->dispatchOnce('capped_a', 42, 1);
    $this->dispatchOnce('capped_a', 42, 2);
    $this->dispatchOnce('capped_b', 42, 1);

    // One from each type reaches queued (the 2nd capped_a is over its limit).
    self::assertSame(2, $this->queuedCountFor(42));
  }

  /**
   * Rate-limit configuration never replaces the dedupe claim's TTL.
   */
  public function testRateLimitWindowDoesNotOverwriteDedupeWindow(): void {
    NotificationType::create([
      'id' => 'combined',
      'label' => 'Combined',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 600,
      'rate_limit' => 5,
      'rate_limit_window' => 60,
    ])->save();

    $notification = $this->factory->create('combined', [
      'uid' => 42,
      'subject' => 'Hi',
      'body' => 'B',
    ]);
    $notification->save();
    $this->dispatcher->enqueue($notification);

    $expire = $this->container->get('database')
      ->select('key_value_expire', 'kv')
      ->fields('kv', ['expire'])
      ->condition('collection', 'openintranet_notifications.dedupe')
      ->condition('name', (string) $notification->get('dedupe_key')->value)
      ->execute()
      ->fetchField();

    self::assertSame(
      \Drupal::time()->getRequestTime() + 600,
      (int) $expire,
      'The dedupe TTL uses dedupe_window, not rate_limit_window.',
    );
  }

  /**
   * A rate-limited dispatch releases its provisional dedupe claim.
   */
  public function testRateLimitedDispatchReleasesDedupeClaim(): void {
    NotificationType::create([
      'id' => 'claim_release',
      'label' => 'Claim release',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 600,
      'rate_limit' => 1,
      'rate_limit_window' => 3600,
    ])->save();

    $first = $this->factory->create('claim_release', [
      'uid' => 42,
      'subject' => 'Hi',
      'body' => 'B',
      'dedupe_context' => 'first',
    ]);
    $first->save();
    $this->dispatcher->enqueue($first);

    $rateLimited = $this->factory->create('claim_release', [
      'uid' => 42,
      'subject' => 'Hi',
      'body' => 'B',
      'dedupe_context' => 'rate-limited',
    ]);
    $rateLimited->save();
    $this->dispatcher->enqueue($rateLimited);

    self::assertSame('cancelled', $rateLimited->get('status')->value);
    $deduplicator = $this->container->get('openintranet_notifications.deduplicator');
    \assert($deduplicator instanceof Deduplicator);
    self::assertFalse(
      $deduplicator->isDuplicate((string) $rateLimited->get('dedupe_key')->value),
      'A rate-limit rejection must not suppress a later legitimate dispatch.',
    );
  }

}
