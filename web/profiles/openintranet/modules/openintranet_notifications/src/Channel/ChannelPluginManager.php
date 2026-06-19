<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Channel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;

/**
 * Plugin manager for notification channels.
 */
final class ChannelPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/NotificationChannel',
      $namespaces,
      $module_handler,
      NotificationChannelInterface::class,
      NotificationChannel::class,
    );
    $this->alterInfo('notification_channel_info');
    $this->setCacheBackend($cache_backend, 'notification_channel_plugins');
  }

}
