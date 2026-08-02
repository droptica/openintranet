<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Service\RetentionPurger;

/**
 * Tests the RetentionPurger service.
 *
 * @group openintranet_notifications
 */
final class RetentionPurgerTest extends KernelTestBase {

  /**
   * One day in seconds.
   */
  private const DAY = 86400;

  /**
   * {@inheritdoc}
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
    'openintranet_notifications',
  ];

  /**
   * The purger under test.
   */
  private RetentionPurger $purger;

  /**
   * The notification storage.
   */
  private EntityStorageInterface $notificationStorage;

  /**
   * The delivery storage.
   */
  private EntityStorageInterface $deliveryStorage;

  /**
   * The request time used to age the fixtures.
   */
  private int $now;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['openintranet_notifications']);

    $this->purger = $this->container->get('openintranet_notifications.retention_purger');
    $this->notificationStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification');
    $this->deliveryStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    $this->now = $this->container->get('datetime.time')->getRequestTime();

    // A type with an explicit 30-day audit retention window.
    NotificationType::create([
      'id' => 'short',
      'label' => 'Short retention',
      'audit_retention_days' => 30,
    ])->save();

    // A type with no explicit window (0) — falls back to settings default (90).
    NotificationType::create([
      'id' => 'fallback',
      'label' => 'Fallback retention',
      'audit_retention_days' => 0,
    ])->save();
  }

  /**
   * Creates a notification with the given type, age and status.
   */
  private function createNotification(string $type, int $ageDays, string $status): int {
    $notification = $this->notificationStorage->create([
      'type' => $type,
      'subject' => $type . ':' . $ageDays . ':' . $status,
      'status' => $status,
      'created' => $this->now - ($ageDays * self::DAY),
    ]);
    $notification->save();
    return (int) $notification->id();
  }

  /**
   * Creates a delivery row attached to the given notification.
   */
  private function createDelivery(int $notificationId): int {
    $delivery = $this->deliveryStorage->create([
      'notification_id' => $notificationId,
      'channel' => 'inbox',
      'status' => 'delivered',
      'address' => 'inbox:1',
    ]);
    $delivery->save();
    return (int) $delivery->id();
  }

  /**
   * Only old terminal notifications past their window are purged with rows.
   */
  public function testPurgeRemovesOnlyExpiredTerminalNotifications(): void {
    // OLD terminal (delivered, 100 days) on the default type → purge (>90).
    $oldTerminal = $this->createNotification('fallback', 100, 'delivered');
    $oldDeliveryA = $this->createDelivery($oldTerminal);
    $oldDeliveryB = $this->createDelivery($oldTerminal);

    // OLD non-terminal (queued, 100 days) → keep (not terminal).
    $oldNonTerminal = $this->createNotification('fallback', 100, 'queued');

    // RECENT terminal (delivered, 0 days) → keep (inside window).
    $recentTerminal = $this->createNotification('fallback', 0, 'delivered');

    // Per-type: short-retention type aged 40 days, terminal → purge (>30).
    $shortType = $this->createNotification('short', 40, 'failed');

    $deleted = $this->purger->purge();
    self::assertSame(2, $deleted);

    $this->notificationStorage->resetCache();
    $this->deliveryStorage->resetCache();

    // The two expired terminal notifications are gone.
    self::assertNull($this->notificationStorage->load($oldTerminal));
    self::assertNull($this->notificationStorage->load($shortType));

    // The deliveries of the purged notification cascaded away.
    self::assertNull($this->deliveryStorage->load($oldDeliveryA));
    self::assertNull($this->deliveryStorage->load($oldDeliveryB));

    // Everything else survives.
    self::assertNotNull($this->notificationStorage->load($oldNonTerminal));
    self::assertNotNull($this->notificationStorage->load($recentTerminal));
  }

  /**
   * The short-retention type uses its own window, not the 90-day default.
   */
  public function testPerTypeWindowShorterThanDefault(): void {
    // 40 days old on the short (30) type → purge; same age on default → keep.
    $shortExpired = $this->createNotification('short', 40, 'delivered');
    $defaultKept = $this->createNotification('fallback', 40, 'delivered');

    $deleted = $this->purger->purge();
    self::assertSame(1, $deleted);

    $this->notificationStorage->resetCache();
    self::assertNull($this->notificationStorage->load($shortExpired));
    self::assertNotNull($this->notificationStorage->load($defaultKept));
  }

  /**
   * The limit caps how many notifications a single run deletes.
   */
  public function testLimitCapsDeletion(): void {
    $ids = [];
    for ($i = 0; $i < 3; $i++) {
      $ids[] = $this->createNotification('fallback', 100, 'delivered');
    }

    $deleted = $this->purger->purge(1);
    self::assertSame(1, $deleted);

    $this->notificationStorage->resetCache();
    $remaining = 0;
    foreach ($ids as $id) {
      if ($this->notificationStorage->load($id) !== NULL) {
        $remaining++;
      }
    }
    self::assertSame(2, $remaining);
  }

  /**
   * A resolved retention window of 0 means retain forever: nothing is purged.
   */
  public function testZeroWindowRetainsForever(): void {
    // Global default 0 AND a type whose own audit_retention_days is 0 → the
    // type resolves to a 0 window and must be excluded from purging entirely.
    $this->config('openintranet_notifications.settings')
      ->set('retention.default_days', 0)
      ->save();

    $old = $this->createNotification('fallback', 1000, 'delivered');
    $delivery = $this->createDelivery($old);

    $deleted = $this->purger->purge();
    self::assertSame(0, $deleted);

    $this->notificationStorage->resetCache();
    $this->deliveryStorage->resetCache();
    self::assertNotNull($this->notificationStorage->load($old));
    self::assertNotNull($this->deliveryStorage->load($delivery));
  }

  /**
   * A type with a positive window is still purged when the global default is 0.
   */
  public function testPositiveTypeWindowPurgesEvenWhenDefaultZero(): void {
    $this->config('openintranet_notifications.settings')
      ->set('retention.default_days', 0)
      ->save();

    // The short type has its own 30-day window → purged despite 0 default.
    $shortExpired = $this->createNotification('short', 40, 'delivered');
    // The fallback type falls back to the 0 default → retained forever.
    $fallbackKept = $this->createNotification('fallback', 1000, 'delivered');

    $deleted = $this->purger->purge();
    self::assertSame(1, $deleted);

    $this->notificationStorage->resetCache();
    self::assertNull($this->notificationStorage->load($shortExpired));
    self::assertNotNull($this->notificationStorage->load($fallbackKept));
  }

  /**
   * A notification newer than the most lenient window is never a candidate.
   */
  public function testRecentNotificationNeverCandidate(): void {
    // The most lenient positive window across the two types is 90 days
    // (fallback → default). A 10-day-old terminal record is too recent to be
    // expirable under any window and must survive.
    $recent = $this->createNotification('fallback', 10, 'delivered');
    // An old one on the short window is purged, proving the run did work.
    $expired = $this->createNotification('short', 40, 'delivered');

    $deleted = $this->purger->purge();
    self::assertSame(1, $deleted);

    $this->notificationStorage->resetCache();
    self::assertNotNull($this->notificationStorage->load($recent));
    self::assertNull($this->notificationStorage->load($expired));
  }

  /**
   * Cron enforces retention by purging expired notifications.
   */
  public function testCronPurges(): void {
    $expired = $this->createNotification('fallback', 100, 'delivered');

    \Drupal::moduleHandler()->invoke('openintranet_notifications', 'cron');

    $this->notificationStorage->resetCache();
    self::assertNull($this->notificationStorage->load($expired));
  }

}
