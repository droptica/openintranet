<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Channel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for notification channel plugins.
 */
final class ChannelPluginManager extends DefaultPluginManager {

  /**
   * Constructs a ChannelPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/NotificationChannel',
      $namespaces,
      $module_handler,
      ChannelPluginInterface::class,
      NotificationChannel::class,
    );
    $this->alterInfo('notification_channel_info');
    $this->setCacheBackend($cache_backend, 'notification_channel_plugins');
  }

  /**
   * Gets available channel plugins.
   *
   * @return \Drupal\openintranet_messenger\Channel\ChannelPluginInterface[]
   *   Array of available channel plugin instances.
   */
  public function getAvailableChannels(): array {
    $channels = [];
    foreach ($this->getDefinitions() as $id => $definition) {
      /** @var \Drupal\openintranet_messenger\Channel\ChannelPluginInterface $plugin */
      $plugin = $this->createInstance($id);
      if ($plugin->isAvailable()) {
        $channels[$id] = $plugin;
      }
    }
    return $channels;
  }

  /**
   * Gets all channel plugins as options for form selects.
   *
   * @param bool $available_only
   *   If TRUE, only return available channels.
   *
   * @return array
   *   Associative array of plugin_id => label.
   */
  public function getChannelOptions(bool $available_only = FALSE): array {
    $options = [];
    foreach ($this->getDefinitions() as $id => $definition) {
      if ($available_only) {
        /** @var \Drupal\openintranet_messenger\Channel\ChannelPluginInterface $plugin */
        $plugin = $this->createInstance($id);
        if (!$plugin->isAvailable()) {
          continue;
        }
      }
      $options[$id] = $definition['label'] ?? $id;
    }
    return $options;
  }

}
