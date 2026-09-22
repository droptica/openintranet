<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\openintranet_forum\Service\ForumCategoryTreeServiceInterface;
use Drupal\openintranet_forum\Service\ForumFilterOptionsBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a forum category browser search or filter panel block.
 */
#[Block(
  id: 'openintranet_forum_browser_filters_block',
  admin_label: new TranslatableMarkup('Forum browser filters'),
  category: new TranslatableMarkup('Open Intranet Forum'),
)]
final class ForumBrowserFiltersBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  /**
   * Constructs a ForumBrowserFiltersBlock object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\openintranet_forum\Service\ForumFilterOptionsBuilderInterface $filterOptionsBuilder
   *   The forum filter options builder.
   * @param \Drupal\openintranet_forum\Service\ForumCategoryTreeServiceInterface $categoryTreeService
   *   The forum category tree service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly ForumFilterOptionsBuilderInterface $filterOptionsBuilder,
    private readonly ForumCategoryTreeServiceInterface $categoryTreeService,
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
  public function defaultConfiguration(): array {
    return ['panel' => 'filters'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['panel'] = [
      '#type' => 'select',
      '#title' => $this->t('Panel'),
      '#options' => [
        'search' => $this->t('Search'),
        'filters' => $this->t('Filtering'),
      ],
      '#default_value' => $this->configuration['panel'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['panel'] = $form_state->getValue('panel');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $panel = $this->configuration['panel'] === 'search' ? 'search' : 'filters';
    $filters = $this->filterOptionsBuilder->readFilters();

    $build = [
      '#theme' => 'openintranet_forum_browser_filters',
      '#panel' => $panel,
      '#url' => Url::fromRoute('openintranet_forum.category_browser')->toString(),
      '#filters' => $filters,
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => ['taxonomy_term_list', 'user_list', 'node_list:forum_post'],
      ],
    ];

    if ($panel === 'search') {
      $build['#search_hidden'] = $this->filterOptionsBuilder->buildHiddenFields($filters, ['q']);

      return $build;
    }

    $tree = $this->categoryTreeService->loadTree();
    $build['#filter_options'] = [
      'category' => $this->filterOptionsBuilder->buildCategoryOptions($tree),
      'tag' => $this->filterOptionsBuilder->buildTagOptions(),
      'author' => $this->filterOptionsBuilder->buildAuthorOptions(),
      'sort' => [
        'recent' => $this->t('Newest'),
        'popular' => $this->t('Most popular'),
        'active' => $this->t('Most active'),
      ],
    ];
    $build['#filter_hidden'] = $this->filterOptionsBuilder->buildHiddenFields(
      $filters,
      ['category', 'tag', 'author', 'sort'],
    );

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

}
