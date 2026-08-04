<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\taxonomy\TermInterface;

/**
 * Loads the forum_category tree and exposes related helpers.
 */
interface ForumCategoryTreeServiceInterface {

  /**
   * Loads the forum_category tree as an immutable value object.
   */
  public function loadTree(): ForumCategoryTree;

  /**
   * Builds a render array for a term's category icon field, or '' if missing.
   */
  public function buildIcon(TermInterface $term): array|string;

  /**
   * Returns published forum_post counts keyed by taxonomy term id.
   *
   * @param list<int> $termIds
   *   The taxonomy term ids to count posts for.
   * @param int|null $sinceTimestamp
   *   If provided, only counts posts whose created timestamp is >= this value.
   *   Pass NULL for all-time counts.
   *
   * @return array<int, int>
   *   Published forum_post counts keyed by taxonomy term id.
   */
  public function loadPostCountsPerTerm(array $termIds, ?int $sinceTimestamp = NULL): array;

}
