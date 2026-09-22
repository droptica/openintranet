<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;

/**
 * Provides shared page-level context for forum listing and detail pages.
 *
 * Groups navigation helpers (tabs, breadcrumbs, section titles), theme region
 * rendering, and embedded view helpers used by the forum pages.
 */
interface ForumPageContextInterface {

  /**
   * Returns TRUE when the given view should use the forum page shell.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The current view executable.
   *
   * @return bool
   *   TRUE when the view belongs to the forum listing family and is on a page
   *   display.
   */
  public function isForumPageView(ViewExecutable $view): bool;

  /**
   * Resolves the main section title for the current forum list view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The current view executable.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   A translated section title, or the view title for unknown views.
   */
  public function getForumPageSectionTitle(ViewExecutable $view): MarkupInterface;

  /**
   * Builds the site-aware forum page title ("<site> Forum").
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The forum page title.
   */
  public function getForumPageTitle(): TranslatableMarkup;

  /**
   * Builds the visible blocks assigned to a forum-only theme region.
   *
   * @param string $region
   *   The theme region machine name.
   *
   * @return array
   *   A render array containing the visible blocks for the region. Returns
   *   the cacheable metadata baseline even when no blocks exist.
   */
  public function buildRegionRenderArray(string $region): array;

  /**
   * Determines if a render array includes visible region blocks.
   *
   * @param array $build
   *   A render array, typically produced by buildRegionRenderArray().
   *
   * @return bool
   *   TRUE when at least one non-property key exists in the array.
   */
  public function regionHasBlocks(array $build): bool;

  /**
   * Builds a breadcrumb render array for a forum listing/views page.
   *
   * @return array
   *   Render array using the openintranet_forum:breadcrumb SDC with an
   *   `items` prop (list of {title, url|NULL}).
   */
  public function buildListingBreadcrumb(ViewExecutable $view): array;

  /**
   * Builds a breadcrumb render array for a single forum_post node page.
   *
   * @return array
   *   Same shape as buildListingBreadcrumb.
   */
  public function buildPostBreadcrumb(NodeInterface $node): array;

  /**
   * Builds the forum listing tabs render array.
   *
   * @return array
   *   Ordered list of {title, url, is_active} items for the 4 tabs.
   */
  public function buildForumPageTabs(string $activeViewId): array;

}
