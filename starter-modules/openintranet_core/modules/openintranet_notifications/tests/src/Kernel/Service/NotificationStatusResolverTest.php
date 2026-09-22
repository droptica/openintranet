<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Service\NotificationStatusResolver;

/**
 * Tests rolling the parent notification status up from its delivery rows.
 *
 * @group openintranet_notifications
 */
final class NotificationStatusResolverTest extends KernelTestBase {

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
   * The resolver under test.
   */
  private NotificationStatusResolver $resolver;

  /**
   * The notification storage.
   */
  private EntityStorageInterface $notificationStorage;

  /**
   * The delivery storage.
   */
  private EntityStorageInterface $deliveryStorage;

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

    $this->resolver = $this->container->get('openintranet_notifications.notification_status_resolver');
    $this->notificationStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification');
    $this->deliveryStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
  }

  /**
   * Creates a queued notification.
   */
  private function createNotification(): NotificationInterface {
    $notification = $this->notificationStorage->create([
      'type' => 'default',
      'subject' => 'Hi',
      'status' => 'queued',
    ]);
    $notification->save();
    \assert($notification instanceof NotificationInterface);
    return $notification;
  }

  /**
   * Creates a delivery row for a notification in the given status.
   */
  private function createDelivery(NotificationInterface $notification, string $status): void {
    $this->deliveryStorage->create([
      'notification_id' => $notification->id(),
      'channel' => 'inbox',
      'address' => 'inbox:1',
      'recipient_type' => 'user',
      'recipient_id' => 1,
      'status' => $status,
    ])->save();
  }

  /**
   * Re-reads the persisted status of a notification.
   */
  private function reloadStatus(NotificationInterface $notification): string {
    $this->notificationStorage->resetCache([$notification->id()]);
    $reloaded = $this->notificationStorage->load($notification->id());
    \assert($reloaded instanceof NotificationInterface);
    return (string) $reloaded->get('status')->value;
  }

  /**
   * Two sent deliveries roll the parent up to delivered.
   */
  public function testAllSentBecomesDelivered(): void {
    $n = $this->createNotification();
    $this->createDelivery($n, 'sent');
    $this->createDelivery($n, 'delivered');

    $this->resolver->rollUpNotificationStatus($n);

    self::assertSame('delivered', $this->reloadStatus($n));
  }

  /**
   * One sent and one failed delivery roll the parent up to partial.
   */
  public function testSomeSentSomeFailedBecomesPartial(): void {
    $n = $this->createNotification();
    $this->createDelivery($n, 'sent');
    $this->createDelivery($n, 'failed');

    $this->resolver->rollUpNotificationStatus($n);

    self::assertSame('partial', $this->reloadStatus($n));
  }

  /**
   * All failed deliveries roll the parent up to failed.
   */
  public function testAllFailedBecomesFailed(): void {
    $n = $this->createNotification();
    $this->createDelivery($n, 'failed');
    $this->createDelivery($n, 'failed');

    $this->resolver->rollUpNotificationStatus($n);

    self::assertSame('failed', $this->reloadStatus($n));
  }

  /**
   * A still-pending delivery leaves the parent at queued (not all settled).
   */
  public function testPendingDeliveryLeavesQueued(): void {
    $n = $this->createNotification();
    $this->createDelivery($n, 'sent');
    $this->createDelivery($n, 'pending');

    $this->resolver->rollUpNotificationStatus($n);

    self::assertSame('queued', $this->reloadStatus($n));
  }

  /**
   * A processing delivery also defers the roll-up (not all settled).
   */
  public function testProcessingDeliveryLeavesQueued(): void {
    $n = $this->createNotification();
    $this->createDelivery($n, 'processing');

    $this->resolver->rollUpNotificationStatus($n);

    self::assertSame('queued', $this->reloadStatus($n));
  }

  /**
   * A skipped-only notification (no send succeeded) rolls up to failed.
   */
  public function testSkippedOnlyBecomesFailed(): void {
    $n = $this->createNotification();
    $this->createDelivery($n, 'skipped');

    $this->resolver->rollUpNotificationStatus($n);

    self::assertSame('failed', $this->reloadStatus($n));
  }

  /**
   * A sent + skipped mix (no failure) still counts as delivered.
   */
  public function testSentPlusSkippedBecomesDelivered(): void {
    $n = $this->createNotification();
    $this->createDelivery($n, 'sent');
    $this->createDelivery($n, 'skipped');

    $this->resolver->rollUpNotificationStatus($n);

    self::assertSame('delivered', $this->reloadStatus($n));
  }

  /**
   * A notification with no deliveries is left untouched.
   */
  public function testNoDeliveriesLeavesStatusUnchanged(): void {
    $n = $this->createNotification();

    $this->resolver->rollUpNotificationStatus($n);

    self::assertSame('queued', $this->reloadStatus($n));
  }

}
