<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\block\BlockRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\views\ViewExecutable;

/**
 * Default implementation of the forum page context service.
 *
 * Provides section titles, breadcrumb and tab render arrays, and forum-region
 * block rendering helpers for the forum listing and detail pages.
 */
final class ForumPageContext implements ForumPageContextInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BlockRepositoryInterface $blockRepository,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isForumPageView(ViewExecutable $view): bool {
    return in_array($view->storage->id(), [
      'forum_latest_posts',
      'forum_popular',
      'forum_active',
      'forum_unanswered',
      'forum_my_posts',
      'forum_category',
      'forum_tag',
      'forum_search',
    ], TRUE) && str_starts_with((string) $view->current_display, 'page_');
  }

  /**
   * {@inheritdoc}
   */
  public function getForumPageSectionTitle(ViewExecutable $view): MarkupInterface {
    $view_title = $view->getTitle();

    return match ($view->storage->id()) {
      'forum_latest_posts' => $this->t('Latest forum posts'),
      'forum_popular' => $this->t('Most popular posts'),
      'forum_active' => $this->t('Your activity on other posts'),
      'forum_unanswered' => $this->t('Unanswered posts'),
      'forum_my_posts' => $this->t('Your forum posts'),
      'forum_search' => $this->t('Filtered forum posts'),
      default => $view_title !== '' ? Markup::create($view_title) : $this->t('Forum posts'),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getForumPageTitle(): TranslatableMarkup {
    $site_name = trim((string) $this->configFactory->get('system.site')->get('name'));

    return $site_name !== ''
      ? $this->t('@site Forum', ['@site' => $site_name])
      : $this->t('Your Forum');
  }

  /**
   * {@inheritdoc}
   */
  public function buildRegionRenderArray(string $region): array {
    $cacheable_metadata = [];
    $regions = $this->blockRepository->getVisibleBlocksPerRegion($cacheable_metadata);
    $build = [];

    if (isset($cacheable_metadata[$region])) {
      $cacheable_metadata[$region]->applyTo($build);
    }

    $blocks = $regions[$region] ?? [];
    if ($blocks === []) {
      return $build;
    }

    $build['#sorted'] = TRUE;
    $view_builder = $this->entityTypeManager->getViewBuilder('block');

    foreach ($blocks as $key => $block) {
      $build[$key] = $view_builder->view($block);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function regionHasBlocks(array $build): bool {
    foreach (array_keys($build) as $key) {
      if (!str_starts_with((string) $key, '#')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildListingBreadcrumb(ViewExecutable $view): array {
    $items = $this->buildBreadcrumbRoot();
    $cache_tags = [];

    $view_id = $view->storage->id();

    $labels = [
      'forum_latest_posts' => $this->t('Latest'),
      'forum_popular' => $this->t('Most popular'),
      'forum_active' => $this->t('Your activity'),
      'forum_unanswered' => $this->t('Unanswered'),
      'forum_my_posts' => $this->t('Your posts'),
      'forum_search' => $this->t('Filtered results'),
    ];

    if (isset($labels[$view_id])) {
      $items[] = [
        'title' => $labels[$view_id],
        'url' => NULL,
      ];
    }
    elseif (in_array($view_id, ['forum_category', 'forum_tag'], TRUE)) {
      $term_id = $view->args[0] ?? NULL;
      if ($term_id !== NULL && is_numeric($term_id)) {
        $term = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $term_id);
        if ($term instanceof TermInterface) {
          if ($view_id === 'forum_category') {
            foreach ($this->categoryChainCrumbs($term, FALSE, $cache_tags) as $crumb) {
              $items[] = $crumb;
            }
          }
          else {
            $cache_tags[] = 'taxonomy_term:' . $term->id();
            $items[] = [
              'title' => $term->label(),
              'url' => NULL,
            ];
          }
        }
      }
    }

    return $this->buildBreadcrumbComponent($items, $cache_tags);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPostBreadcrumb(NodeInterface $node): array {
    $items = $this->buildBreadcrumbRoot();
    $cache_tags = [];

    if ($node->hasField('field_forum_category') && !$node->get('field_forum_category')->isEmpty()) {
      $term = $node->get('field_forum_category')->entity;
      if ($term instanceof TermInterface) {
        foreach ($this->categoryChainCrumbs($term, TRUE, $cache_tags) as $crumb) {
          $items[] = $crumb;
        }
      }
    }

    $items[] = [
      'title' => $node->label(),
      'url' => NULL,
    ];

    return $this->buildBreadcrumbComponent($items, $cache_tags);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForumPageTabs(string $activeViewId): array {
    return [
      [
        'title' => $this->t('Latest'),
        'url' => Url::fromUri('internal:/forum/latest')->toString(),
        'is_active' => $activeViewId === 'forum_latest_posts',
      ],
      [
        'title' => $this->t('Most popular'),
        'url' => Url::fromUri('internal:/forum/popular')->toString(),
        'is_active' => $activeViewId === 'forum_popular',
      ],
      [
        'title' => $this->t('Your posts'),
        'url' => Url::fromUri('internal:/forum/your-posts')->toString(),
        'is_active' => $activeViewId === 'forum_my_posts',
      ],
      [
        'title' => $this->t('Your activity'),
        'url' => Url::fromUri('internal:/forum/active')->toString(),
        'is_active' => $activeViewId === 'forum_active',
      ],
    ];
  }

  /**
   * Builds breadcrumb crumbs for a category term and all of its ancestors.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The (leaf) forum category term.
   * @param bool $link_leaf
   *   TRUE to link the leaf term (post pages); FALSE to render it as the
   *   current, non-clickable crumb (category listing pages).
   * @param string[] $cache_tags
   *   Collects the cache tag of every term in the chain, by reference, so the
   *   breadcrumb is rebuilt when a category is renamed or re-parented.
   *
   * @return array
   *   Crumbs ordered from the root category down to the term itself, each a
   *   {title, url|NULL} array. Category links target the forum category
   *   listing view (/forum/category/{tid}); the canonical taxonomy term page
   *   is disabled site-wide and would 404.
   */
  private function categoryChainCrumbs(TermInterface $term, bool $link_leaf, array &$cache_tags): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $chain = array_reverse($storage->loadAllParents((int) $term->id()), TRUE);
    $leaf_tid = (int) $term->id();

    $crumbs = [];
    foreach ($chain as $tid => $ancestor) {
      $cache_tags[] = 'taxonomy_term:' . $tid;
      $is_leaf = (int) $tid === $leaf_tid;
      $crumbs[] = [
        'title' => $ancestor->label(),
        'url' => ($is_leaf && !$link_leaf)
          ? NULL
          : Url::fromUri('internal:/forum/category/' . $tid)->toString(),
      ];
    }

    return $crumbs;
  }

  /**
   * Returns the root "Forum" breadcrumb crumb shared by all forum breadcrumbs.
   *
   * @return array
   *   A single-item list with the root crumb.
   */
  private function buildBreadcrumbRoot(): array {
    return [
      [
        'title' => $this->t('Forum'),
        'url' => Url::fromUri('internal:/forum/feed')->toString(),
      ],
    ];
  }

  /**
   * Wraps breadcrumb items in the forum breadcrumb component render array.
   *
   * @param array $items
   *   The breadcrumb items ({title, url|NULL}).
   * @param string[] $cache_tags
   *   Cache tags to attach to the render array.
   *
   * @return array
   *   A component render array.
   */
  private function buildBreadcrumbComponent(array $items, array $cache_tags = []): array {
    $build = [
      '#type' => 'component',
      '#component' => 'openintranet_forum:breadcrumb',
      '#props' => [
        'items' => $items,
      ],
    ];

    if ($cache_tags !== []) {
      $build['#cache']['tags'] = $cache_tags;
    }

    return $build;
  }

}
