<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\openintranet_documents\Plugin\DocumentSource\DocumentSourceInterface;

/**
 * Plugin manager for Document Source plugins.
 */
class DocumentSourceManager extends DefaultPluginManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a DocumentSourceManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct(
      'Plugin/DocumentSource',
      $namespaces,
      $module_handler,
      'Drupal\openintranet_documents\Plugin\DocumentSource\DocumentSourceInterface',
      'Drupal\openintranet_documents\Annotation\DocumentSource'
    );
    $this->alterInfo('document_source_info');
    $this->setCacheBackend($cache_backend, 'document_source_plugins');
    $this->configFactory = $config_factory;
  }

  /**
   * Returns only enabled document source definitions.
   *
   * @return array
   *   An array of plugin definitions keyed by plugin ID.
   */
  public function getEnabledDefinitions(): array {
    $config = $this->configFactory->get('openintranet_documents.settings');
    $enabled = $config->get('enabled_sources') ?? ['local_file'];

    return array_filter(
      $this->getDefinitions(),
      fn($id) => in_array($id, $enabled, TRUE),
      ARRAY_FILTER_USE_KEY
    );
  }

  /**
   * Creates a plugin instance by ID.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\openintranet_documents\Plugin\DocumentSource\DocumentSourceInterface
   *   The plugin instance.
   */
  public function createInstanceById(string $plugin_id, array $configuration = []): DocumentSourceInterface {
    return $this->createInstance($plugin_id, $configuration);
  }

  /**
   * Gets the default source plugin ID.
   *
   * @return string
   *   The default source plugin ID.
   */
  public function getDefaultSourceId(): string {
    $config = $this->configFactory->get('openintranet_documents.settings');
    return $config->get('default_source') ?? 'local_file';
  }

}
