<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

/**
 * Builds and applies the forum trending/popular score.
 *
 * Formula: (reactions × 3) + views + (GREATEST(0, 7 - days_old) × 10).
 *
 * Time windows (7 days for trending, 30 days for popular) are applied by the
 * caller via query filters — not by this service.
 */
interface ForumTrendingScorerInterface {

  /**
   * Returns the SQL expression that computes the score for ORDER BY.
   *
   * @param string $base_alias
   *   Alias of the node base table (typically 'node_field_data' or 'n').
   * @param string $views_alias
   *   Alias of the node__field_forum_views join.
   */
  public function buildScoreSql(string $base_alias, string $views_alias): string;

  /**
   * Returns the configured trending recency window in days.
   *
   * Reads openintranet_forum.settings:trending_days, clamped to a sane minimum,
   * falling back to the default window when unset or invalid.
   *
   * @return int
   *   The recency window in days (>= 1).
   */
  public function trendingDays(): int;

  /**
   * Re-orders a set of node ids by the trending score.
   *
   * @param list<int> $nodeIds
   *   The node ids to sort.
   *
   * @return list<int>
   *   The same ids sorted by score DESC, tie-broken by created DESC.
   */
  public function sortNodeIdsByScore(array $nodeIds): array;

}
