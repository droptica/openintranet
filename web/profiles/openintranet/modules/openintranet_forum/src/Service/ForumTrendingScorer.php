<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Default implementation of the forum trending/popular scorer.
 */
final class ForumTrendingScorer implements ForumTrendingScorerInterface {

  /**
   * Fallback recency window (in days) when no configuration is available.
   */
  private const DEFAULT_TRENDING_DAYS = 7;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function trendingDays(): int {
    $days = (int) ($this->configFactory
      ->get('openintranet_forum.settings')
      ->get('trending_days') ?? self::DEFAULT_TRENDING_DAYS);

    return $days < 1 ? self::DEFAULT_TRENDING_DAYS : $days;
  }

  /**
   * {@inheritdoc}
   */
  public function buildScoreSql(string $base_alias, string $views_alias): string {
    return sprintf(
      '(COALESCE((SELECT SUM(vr.value) FROM {votingapi_result} vr WHERE vr.entity_id = %1$s.nid AND vr.entity_type = \'node\' AND vr.function = \'vote_sum\'), 0) * 3 + COALESCE(%2$s.field_forum_views_value, 0) + (GREATEST(0, %4$d - FLOOR((%3$d - %1$s.created) / 86400)) * 10))',
      $base_alias,
      $views_alias,
      $this->time->getRequestTime(),
      $this->trendingDays(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function sortNodeIdsByScore(array $nodeIds): array {
    $nodeIds = array_values(array_map('intval', $nodeIds));
    if ($nodeIds === []) {
      return [];
    }

    $select = $this->database->select('node_field_data', 'n');
    $select->leftJoin('node__field_forum_views', 'nfv', 'nfv.entity_id = n.nid');
    $select->condition('n.nid', $nodeIds, 'IN');
    $select->addField('n', 'nid');
    $score_alias = $select->addExpression(
      $this->buildScoreSql('n', 'nfv'),
      'openintranet_forum_trending_score',
    );
    $select->orderBy($score_alias, 'DESC');
    $select->orderBy('n.created', 'DESC');

    return array_map('intval', $select->execute()->fetchCol());
  }

}
