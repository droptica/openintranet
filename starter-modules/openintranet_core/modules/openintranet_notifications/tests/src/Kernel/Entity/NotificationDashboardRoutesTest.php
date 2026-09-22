<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the dashboard collection routes from the entity route providers.
 *
 * @group openintranet_notifications
 */
final class NotificationDashboardRoutesTest extends KernelTestBase {

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
    'views',
    'openintranet_notifications',
  ];

  /**
   * Both content-entity collection routes exist with the expected paths.
   */
  public function testCollectionRoutesExist(): void {
    $route_provider = $this->container->get('router.route_provider');

    $notification = $route_provider->getRouteByName('entity.openintranet_notification.collection');
    self::assertSame('/admin/openintranet/notifications', $notification->getPath());
    self::assertSame('view notification logs', $notification->getRequirement('_permission'));

    $delivery = $route_provider->getRouteByName('entity.openintranet_notif_delivery.collection');
    self::assertSame('/admin/openintranet/notifications/deliveries', $delivery->getPath());
    self::assertSame('view notification logs', $delivery->getRequirement('_permission'));
  }

  /**
   * The canonical view route guards with the entity access handler.
   */
  public function testCanonicalRouteUsesEntityAccess(): void {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName('entity.openintranet_notification.canonical');

    self::assertSame('/notifications/{openintranet_notification}', $route->getPath());
    self::assertSame('openintranet_notification.view', $route->getRequirement('_entity_access'));
    self::assertNull($route->getRequirement('_custom_access'));
  }

}
