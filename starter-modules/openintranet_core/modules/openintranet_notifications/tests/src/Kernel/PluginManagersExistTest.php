<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the resolver/policy/renderer plugin managers are wired services.
 *
 * Stage 1 ships only the contracts; the managers must exist and return valid
 * (possibly empty) definition arrays before any concrete plugin lands.
 *
 * @group openintranet_notifications
 */
final class PluginManagersExistTest extends KernelTestBase {

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
   * The three contract managers are services returning definition arrays.
   */
  public function testManagersAreServicesWithDefinitionArrays(): void {
    foreach ([
      'plugin.manager.notification_recipient_resolver',
      'plugin.manager.notification_delivery_policy',
      'plugin.manager.notification_template_renderer',
    ] as $service_id) {
      $manager = $this->container->get($service_id);
      self::assertInstanceOf(DefaultPluginManager::class, $manager);
      self::assertIsArray($manager->getDefinitions());
    }
  }

}
