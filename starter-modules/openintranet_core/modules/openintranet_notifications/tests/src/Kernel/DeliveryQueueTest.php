<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Service\DeliveryQueue;
use Drupal\user\Entity\User;

/**
 * Tests the DeliveryQueue service.
 *
 * @group openintranet_notifications
 */
final class DeliveryQueueTest extends KernelTestBase {

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
   * The queue service under test.
   */
  private DeliveryQueue $deliveryQueue;

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
    $this->deliveryQueue = $this->container->get('openintranet_notifications.delivery_queue');
  }

  /**
   * One delivery row per channel, each enqueued and uniquely keyed.
   */
  public function testCreateAndEnqueueBuildsDeliveriesAndQueuesItems(): void {
    $account = User::create(['name' => 'recipient', 'status' => 1]);
    $account->save();

    $notification = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->create([
        'type' => 'default',
        'uid' => (int) $account->id(),
        'subject' => 'Hi',
        'body' => 'B',
        'status' => 'queued',
      ]);
    $notification->save();

    $recipient = new NotificationRecipient(
      type: 'user',
      id: (int) $account->id(),
      account: $account,
    );

    $ids = $this->deliveryQueue->createAndEnqueue($notification, $recipient, ['inbox', 'log_only']);
    self::assertCount(2, $ids);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    $deliveries = $storage->loadMultiple($ids);
    self::assertCount(2, $deliveries);

    $keys = [];
    foreach ($deliveries as $delivery) {
      \assert($delivery instanceof NotificationDeliveryInterface);
      self::assertSame('pending', $delivery->get('status')->value);
      self::assertNotEmpty($delivery->get('address')->value);
      self::assertSame((int) $notification->id(), (int) $delivery->get('notification_id')->target_id);
      self::assertNotEmpty($delivery->get('idempotency_key')->value);
      $keys[] = $delivery->get('idempotency_key')->value;
    }
    self::assertCount(2, array_unique($keys));

    self::assertSame(2, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

  /**
   * Builds an unsaved row with the single-formula idempotency key.
   */
  public function testCreateDeliveryRowBuildsExpectedIdempotencyKey(): void {
    $account = User::create(['name' => 'recipient2', 'status' => 1]);
    $account->save();

    $notification = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->create([
        'type' => 'default',
        'uid' => (int) $account->id(),
        'subject' => 'Hi',
        'body' => 'B',
        'status' => 'queued',
      ]);
    $notification->save();

    $recipient = new NotificationRecipient(
      type: 'user',
      id: (int) $account->id(),
      account: $account,
    );

    $delivery = $this->deliveryQueue->createDeliveryRow($notification, $recipient, 'inbox');

    // Built, not persisted, and pending.
    self::assertTrue($delivery->isNew());
    self::assertSame('pending', $delivery->get('status')->value);
    self::assertSame('inbox', $delivery->get('channel')->value);

    $expectedKey = hash('sha256', $notification->id() . ':' . $account->id() . ':inbox');
    self::assertSame($expectedKey, $delivery->get('idempotency_key')->value);

    // No row was written by the builder alone.
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    self::assertCount(0, $storage->loadMultiple());
  }

  /**
   * Requeue resets a delivery for a fresh attempt and enqueues one item.
   */
  public function testRequeueResetsAndEnqueues(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    $delivery = $storage->create([
      'notification_id' => 1,
      'channel' => 'inbox',
      'status' => 'failed',
      'attempt_count' => 5,
      'next_attempt' => 9999,
      'address' => 'inbox:1',
    ]);
    $delivery->save();

    $this->deliveryQueue->requeue($delivery);

    $storage->resetCache([(int) $delivery->id()]);
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $reloaded */
    $reloaded = $storage->load((int) $delivery->id());
    self::assertSame('pending', $reloaded->get('status')->value);
    self::assertSame(0, (int) $reloaded->get('attempt_count')->value);
    self::assertSame(0, (int) $reloaded->get('next_attempt')->value);

    self::assertSame(1, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

  /**
   * Requeue clears the idempotency claim so a retry can actually send.
   *
   * A just-failed delivery may still hold its claim (TTL not yet expired).
   * Without clearing it, an immediate requeue would be swallowed by the shared
   * sender's setWithExpireIfNotExists guard. Requeue must delete the claim.
   */
  public function testRequeueClearsIdempotencyClaim(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    $delivery = $storage->create([
      'notification_id' => 1,
      'channel' => 'inbox',
      'status' => 'failed',
      'attempt_count' => 5,
      'address' => 'inbox:1',
      'idempotency_key' => 'claim-key-1',
    ]);
    $delivery->save();

    // Simulate the in-flight claim left by the just-failed send attempt.
    /** @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $claimStore */
    $claimStore = $this->container->get('keyvalue.expirable')
      ->get('openintranet_notifications.delivery_claim');
    $claimStore->setWithExpire('claim-key-1', 123, 120);
    self::assertTrue($claimStore->has('claim-key-1'));

    $this->deliveryQueue->requeue($delivery);

    // The claim is gone, so a reprocess can re-claim and send.
    self::assertFalse($claimStore->has('claim-key-1'));
  }

  /**
   * Cancel marks a delivery cancelled without enqueuing anything.
   */
  public function testCancelMarksCancelled(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    $delivery = $storage->create([
      'notification_id' => 1,
      'channel' => 'inbox',
      'status' => 'pending',
      'address' => 'inbox:1',
    ]);
    $delivery->save();

    $this->deliveryQueue->cancel($delivery);

    $storage->resetCache([(int) $delivery->id()]);
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $reloaded */
    $reloaded = $storage->load((int) $delivery->id());
    self::assertSame('cancelled', $reloaded->get('status')->value);
    self::assertSame(0, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());
  }

}
