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

}
