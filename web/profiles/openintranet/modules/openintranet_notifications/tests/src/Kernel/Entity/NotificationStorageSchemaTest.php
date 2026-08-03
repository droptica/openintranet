<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests indexes declared by the notification storage schema handlers.
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

}
