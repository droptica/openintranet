<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Cleans up old engagement event data.
 */
final class OiEngagementCleaner {

  /**
   * Constructs the OiEngagementCleaner service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementConfigInterface $config
   *   The config service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly OiEngagementConfigInterface $config,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Cleans old events based on retention setting.
   *
   * @param int|null $days
   *   Number of days to retain. NULL uses config value.
   *
   * @return int
   *   Number of deleted rows.
   */
  public function cleanOldEvents(?int $days = NULL): int {
    $days = $days ?? $this->config->getEventLogRetention();
    $threshold = $this->time->getRequestTime() - ($days * 86400);

    return (int) $this->database->delete('oi_engagement_event')
      ->condition('created', $threshold, '<')
      ->execute();
  }

  /**
   * Cleans orphaned scores (users that no longer exist).
   *
   * @return int
   *   Number of deleted rows.
   */
  public function cleanOrphanedScores(): int {
    // Find scores for non-existent users.
    $query = $this->database->select('oi_engagement_score', 's')
      ->fields('s', ['user_id']);
    $query->leftJoin('users', 'u', 's.user_id = u.uid');
    $query->isNull('u.uid');

    $orphanedIds = $query->execute()->fetchCol();

    if (empty($orphanedIds)) {
      return 0;
    }

    return (int) $this->database->delete('oi_engagement_score')
      ->condition('user_id', $orphanedIds, 'IN')
      ->execute();
  }

  /**
   * Cleans old daily stats.
   *
   * @param int|null $days
   *   Number of days to retain. NULL uses config value.
   *
   * @return int
   *   Number of deleted rows.
   */
  public function cleanOldDailyStats(?int $days = NULL): int {
    $days = $days ?? $this->config->getEventLogRetention();
    $threshold = date('Y-m-d', $this->time->getRequestTime() - ($days * 86400));

    return (int) $this->database->delete('oi_engagement_daily_stats')
      ->condition('date', $threshold, '<')
      ->execute();
  }

  /**
   * Runs all cleanup tasks.
   *
   * @return array{events: int, scores: int, stats: int}
   *   Number of deleted rows per type.
   */
  public function cleanAll(): array {
    return [
      'events' => $this->cleanOldEvents(),
      'scores' => $this->cleanOrphanedScores(),
      'stats' => $this->cleanOldDailyStats(),
    ];
  }

}
