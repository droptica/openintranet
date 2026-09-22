<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\openintranet_forum\Service\ForumCategoryTreeServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base class for the forum category sidebar accordion blocks.
 *
 * Shares construction, the active-category query parameter, the browser URL
 * and the cache contract between the full categories and popular categories
 * blocks.
 */
abstract class ForumCategorySidebarBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  /**
   * Constructs a forum category sidebar block.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\openintranet_forum\Service\ForumCategoryTreeServiceInterface $categoryTreeService
   *   The forum category tree service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly RequestStack $requestStack,
    protected readonly ForumCategoryTreeServiceInterface $categoryTreeService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return static::createInstanceAutowired($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return array_merge(parent::getCacheContexts(), [
      'url.query_args:category',
      'languages:language_interface',
    ]);
  }

  /**
   * Returns the active category id from the request query.
   */
  protected function activeCategoryId(): int {
    return max(0, (int) $this->requestStack->getCurrentRequest()->query->get('category', 0));
  }

  /**
   * Returns the URL of the category browser page.
   */
  protected function categoriesBrowserUrl(): string {
    return Url::fromRoute('openintranet_forum.category_browser')->toString();
  }

  /**
   * Returns the shared #cache metadata for category sidebar blocks.
   *
   * @return array
   *   A render-array #cache fragment.
   */
  protected function categoryCacheMetadata(): array {
    return [
      'contexts' => ['url.query_args:category', 'languages:language_interface'],
      'tags' => ['taxonomy_term_list:forum_category', 'node_list'],
    ];
  }

}
