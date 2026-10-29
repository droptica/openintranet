<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests indexes and unique keys declared by the notification storage schemas.
 *
 * @group openintranet_notifications
 */
final class NotificationStorageSchemaTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installEntitySchema('user_notification_settings');
  }

  /**
   * Notification storage has indexes for inbox, retention, and digest queries.
   */
  public function testNotificationIndexes(): void {
    $schema = $this->container->get('database')->schema();

    self::assertTrue($schema->indexExists(
      'openintranet_notification',
      'openintranet_notification__uid_created',
    ));
    self::assertTrue($schema->indexExists(
      'openintranet_notification',
      'openintranet_notification__uid_read_at',
    ));
    self::assertTrue($schema->indexExists(
      'openintranet_notification',
      'openintranet_notification__status_created',
    ));
    self::assertTrue($schema->indexExists(
      'openintranet_notification',
      'openintranet_notification__type_digested',
    ));
  }

  /**
   * Delivery storage has indexes for sibling, roll-up, and status queries.
   */
  public function testDeliveryIndexes(): void {
    $schema = $this->container->get('database')->schema();

    self::assertTrue($schema->indexExists(
      'openintranet_notif_delivery',
      'openintranet_notif_delivery__notif_status_next',
    ));
    self::assertTrue($schema->indexExists(
      'openintranet_notif_delivery',
      'openintranet_notif_delivery__status_id',
    ));
  }

  /**
   * Two deliveries cannot share an idempotency key; rows without one can.
   */
  public function testDeliveryIdempotencyKeyIsUnique(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    $values = [
      'notification_id' => 1,
      'channel' => 'inbox',
      'status' => 'pending',
      'address' => 'inbox:1',
    ];

    // Keyless rows are not constrained.
    $storage->create($values)->save();
    $storage->create($values)->save();

    $storage->create($values + ['idempotency_key' => 'same-key'])->save();
    $this->expectException(EntityStorageException::class);
    $storage->create($values + ['idempotency_key' => 'same-key'])->save();
  }

  /**
   * A user has at most one notification settings row.
   */
  public function testUserSettingsAreUniquePerUser(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('user_notification_settings');

    $storage->create(['uid' => 7])->save();
    $this->expectException(EntityStorageException::class);
    $storage->create(['uid' => 7])->save();
  }

}
