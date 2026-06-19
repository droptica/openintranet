<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Resolver;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\openintranet_notifications\Attribute\NotificationRecipientResolver;

/**
 * Plugin manager for notification recipient resolvers.
 */
final class RecipientResolverManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/NotificationRecipientResolver',
      $namespaces,
      $module_handler,
      NotificationRecipientResolverInterface::class,
      NotificationRecipientResolver::class,
    );
    $this->alterInfo('notification_recipient_resolver_info');
    $this->setCacheBackend($cache_backend, 'notification_recipient_resolver_plugins');
  }

}
