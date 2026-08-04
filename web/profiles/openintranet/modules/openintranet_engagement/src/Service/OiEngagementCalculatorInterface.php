<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

/**
 * Interface for RFV score calculation service.
 */
interface OiEngagementCalculatorInterface {

  /**
   * Calculates RFV scores for a user.
   *
   * @param int $userId
   *   The user ID.
   *
   * @return array{
   *   user_id: int,
   *   recency_score: int,
   *   frequency_score: int,
   *   value_score: int,
   *   total_score: int,
   *   segment: string,
   *   last_activity: int,
   *   event_count: int,
   *   total_value: int,
   *   first_activity: int,
   *   calculated: int
   * }
   *   Array with all score data.
   */
  public function calculateForUser(int $userId): array;

  /**
   * Recalculates scores for all users.
   *
   * Used by cron or drush command.
   *
   * @return int
   *   Number of users recalculated.
   */
  public function recalculateAll(): int;

  /**
   * Recalculates scores for users with stale data.
   *
   * @return int
   *   Number of users recalculated.
   */
  public function recalculateStale(): int;

  /**
   * Gets cached score for user, recalculating if stale.
   *
   * @param int $userId
   *   The user ID.
   *
   * @return array|null
   *   Score data or NULL if user has no activity.
   */
  public function getScore(int $userId): ?array;

}
