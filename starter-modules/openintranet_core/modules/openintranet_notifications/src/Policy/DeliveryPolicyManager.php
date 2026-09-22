<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Policy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\openintranet_notifications\Attribute\NotificationDeliveryPolicy;

/**
 * Plugin manager for notification delivery policies.
 */
final class DeliveryPolicyManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/NotificationDeliveryPolicy',
      $namespaces,
      $module_handler,
      NotificationDeliveryPolicyInterface::class,
      NotificationDeliveryPolicy::class,
    );
    $this->alterInfo('notification_delivery_policy_info');
    $this->setCacheBackend($cache_backend, 'notification_delivery_policy_plugins');
  }

}
