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
   * Channel ids that are internal plumbing, never offered as an admin choice.
   *
   * The null channel is a no-op sink for tests and dry runs: discovered and
   * usable at runtime, but it must not appear in channel pickers.
   */
  private const INTERNAL_CHANNELS = ['null'];

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
    // Namespaced ids: openintranet_messenger ships its own channel manager with
    // the same plugin subdir; sharing the alter hook / cache key would collide.
    $this->alterInfo('openintranet_notification_channel_info');
    $this->setCacheBackend($cache_backend, 'openintranet_notification_channel_plugins');
  }

  /**
   * Channel definitions that may be offered as a user/admin choice.
   *
   * Excludes the internal channels (self::INTERNAL_CHANNELS). The read-only
   * channel-status page and all runtime delivery code keep using
   * getDefinitions()/createInstance() so an internal channel still functions
   * when a type or test references it.
   *
   * @return array<string, mixed>
   *   Plugin id => definition, minus the internal channels.
   */
  public function getSelectableDefinitions(): array {
    return array_diff_key($this->getDefinitions(), array_flip(self::INTERNAL_CHANNELS));
  }

}
