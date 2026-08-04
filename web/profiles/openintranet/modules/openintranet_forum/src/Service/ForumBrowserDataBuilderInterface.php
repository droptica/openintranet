<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

/**
 * Builds grouped data for the forum category browser page.
 */
interface ForumBrowserDataBuilderInterface {

  /**
   * Builds the grouped browser data for the category browser page.
   *
   * Produces top-level groups, their children, and post items given a loaded
   * category tree and a normalized filter set.
   *
   * @param \Drupal\openintranet_forum\Service\ForumCategoryTree $tree
   *   The forum category tree DTO.
   * @param array $filters
   *   Normalized filters: ['q' => string, 'category' => int, 'tag' => int,
   *   'author' => int, 'sort' => 'recent'|'popular'|'active'].
   *
   * @return array
   *   ['groups' => array of prepared display groups].
   */
  public function buildBrowserGroups(ForumCategoryTree $tree, array $filters): array;

}
