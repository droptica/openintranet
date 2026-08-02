<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\openintranet_forum\Service\ForumBrowserDataBuilderInterface;
use Drupal\openintranet_forum\Service\ForumCategoryTreeServiceInterface;
use Drupal\openintranet_forum\Service\ForumFilterOptionsBuilderInterface;
use Drupal\openintranet_forum\Service\ForumPageContextInterface;

/**
 * Builds the forum category browser page.
 */
final class ForumCategoryBrowserController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly ForumCategoryTreeServiceInterface $categoryTreeService,
    private readonly ForumPageContextInterface $pageContext,
    private readonly ForumBrowserDataBuilderInterface $browserDataBuilder,
    private readonly ForumFilterOptionsBuilderInterface $filterOptionsBuilder,
  ) {}

  /**
   * Returns the category browser page.
   */
  public function build(): array {
    $filters = $this->filterOptionsBuilder->readFilters();
    $tree = $this->categoryTreeService->loadTree();
    $browser_data = $this->browserDataBuilder->buildBrowserGroups($tree, $filters);

    $featured = $this->pageContext->buildRegionRenderArray('forum_featured_top');
    $sidebar_first = $this->pageContext->buildRegionRenderArray('forum_sidebar_first');
    $sidebar_second = $this->pageContext->buildRegionRenderArray('forum_sidebar_second');

    return [
      '#theme' => 'openintranet_forum_categories_browser',
      '#header_title' => $this->pageContext->getForumPageTitle(),
      '#breadcrumb_title' => $this->t('Categories'),
      '#page_title' => $this->t('Categories'),
      '#groups' => $browser_data['groups'],
      '#filters' => $filters,
      '#main_search_hidden' => $this->filterOptionsBuilder->buildHiddenFields($filters, ['q']),
      '#categories_browser_url' => Url::fromRoute('openintranet_forum.category_browser')->toString(),
      '#forum_page_featured' => $featured,
      '#forum_page_has_featured' => $this->pageContext->regionHasBlocks($featured),
      '#forum_page_sidebar_first' => $sidebar_first,
      '#forum_page_has_sidebar_first' => $this->pageContext->regionHasBlocks($sidebar_first),
      '#forum_page_sidebar_second' => $sidebar_second,
      '#forum_page_has_sidebar_second' => $this->pageContext->regionHasBlocks($sidebar_second),
      '#attached' => [
        'library' => [
          'openintranet_forum/forum.base',
          'openintranet_forum/forum.page',
          'openintranet_forum/forum.category-browser',
          'openintranet_forum/forum.add-post',
        ],
      ],
      '#cache' => [
        'contexts' => [
          'languages:language_interface',
          'url.query_args',
          'user.node_grants:view',
        ],
        'tags' => [
          'comment_list',
          'node_list',
          'taxonomy_term_list',
          'user_list',
        ],
        'max-age' => 0,
      ],
    ];
  }

}
