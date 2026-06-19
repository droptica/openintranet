<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Renderer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\openintranet_notifications\Attribute\NotificationTemplateRenderer;

/**
 * Plugin manager for notification template renderers.
 */
final class TemplateRendererManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/NotificationTemplateRenderer',
      $namespaces,
      $module_handler,
      NotificationTemplateRendererInterface::class,
      NotificationTemplateRenderer::class,
    );
    $this->alterInfo('notification_template_renderer_info');
    $this->setCacheBackend($cache_backend, 'notification_template_renderer_plugins');
  }

}
