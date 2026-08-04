<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Aggregates daily engagement statistics.
 */
final class OiEngagementDailyAggregator {

  /**
   * Constructs the OiEngagementDailyAggregator service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Aggregates events from yesterday into daily stats.
   *
   * @return int
   *   Number of aggregated rows.
   */
  public function aggregateYesterday(): int {
    $yesterday = date('Y-m-d', $this->time->getRequestTime() - 86400);
    return $this->aggregateDate($yesterday);
  }

  /**
   * Aggregates events for a specific date.
   *
   * @param string $date
   *   Date in Y-m-d format.
   *
   * @return int
   *   Number of aggregated rows.
   */
  public function aggregateDate(string $date): int {
    $startTimestamp = strtotime($date . ' 00:00:00');
    $endTimestamp = strtotime($date . ' 23:59:59');

    // Get aggregated data from events.
    $query = $this->database->select('oi_engagement_event', 'e')
      ->fields('e', ['user_id', 'event_type'])
      ->condition('e.created', $startTimestamp, '>=')
      ->condition('e.created', $endTimestamp, '<=');
    $query->addExpression('COUNT(*)', 'event_count');
    $query->addExpression('SUM(e.event_value)', 'total_value');
    $query->groupBy('e.user_id');
    $query->groupBy('e.event_type');

    $results = $query->execute()->fetchAll();

    $count = 0;
    foreach ($results as $row) {
      // Use merge to handle duplicates gracefully.
      $this->database->merge('oi_engagement_daily_stats')
        ->keys([
          'date' => $date,
          'user_id' => $row->user_id,
          'event_type' => $row->event_type,
        ])
        ->fields([
          'event_count' => $row->event_count,
          'total_value' => $row->total_value,
        ])
        ->execute();
      $count++;
    }

    return $count;
  }

  /**
   * Backfills daily stats for a date range.
   *
   * @param string $startDate
   *   Start date in Y-m-d format.
   * @param string $endDate
   *   End date in Y-m-d format.
   *
   * @return int
   *   Total number of aggregated rows.
   */
  public function backfill(string $startDate, string $endDate): int {
    $count = 0;
    $current = strtotime($startDate);
    $end = strtotime($endDate);

    while ($current <= $end) {
      $date = date('Y-m-d', $current);
      $count += $this->aggregateDate($date);
      $current += 86400;
    }

    return $count;
  }

}
