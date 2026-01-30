<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Plugin\OiAccessEntity;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\openintranet_access\Annotation\OiAccessEntityPlugin;

/**
 * Plugin manager for OI Access Entity plugins.
 */
final class OiAccessEntityPluginManager extends DefaultPluginManager {

  /**
   * Constructs the plugin manager.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/OiAccessEntity',
      $namespaces,
      $module_handler,
      OiAccessEntityPluginInterface::class,
      OiAccessEntityPlugin::class,
    );
    $this->alterInfo('oi_access_entity_plugin_info');
    $this->setCacheBackend($cache_backend, 'oi_access_entity_plugins');
  }

  /**
   * Gets the plugin for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\openintranet_access\Plugin\OiAccessEntity\OiAccessEntityPluginInterface|null
   *   The plugin or NULL if none applies.
   */
  public function getPluginForEntity(EntityInterface $entity): ?OiAccessEntityPluginInterface {
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if ($definition['entity_type'] === $entity->getEntityTypeId()) {
        /** @var \Drupal\openintranet_access\Plugin\OiAccessEntity\OiAccessEntityPluginInterface $plugin */
        $plugin = $this->createInstance($plugin_id);
        if ($plugin->applies($entity)) {
          return $plugin;
        }
      }
    }
    return NULL;
  }

  /**
   * Gets all plugins for a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\openintranet_access\Plugin\OiAccessEntity\OiAccessEntityPluginInterface[]
   *   Array of plugins.
   */
  public function getPluginsForEntityType(string $entity_type_id): array {
    $plugins = [];
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if ($definition['entity_type'] === $entity_type_id) {
        $plugins[$plugin_id] = $this->createInstance($plugin_id);
      }
    }
    return $plugins;
  }

}
