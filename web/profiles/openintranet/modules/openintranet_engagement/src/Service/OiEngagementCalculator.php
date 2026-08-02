<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Calculates RFV scores for users.
 */
final class OiEngagementCalculator implements OiEngagementCalculatorInterface {

  /**
   * Scoring period in days.
   */
  private const SCORING_PERIOD_DAYS = 30;

  /**
   * Constructs the OiEngagementCalculator service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface $segmenter
   *   The segmenter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementConfigInterface $config
   *   The config service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly OiEngagementSegmenterInterface $segmenter,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OiEngagementConfigInterface $config,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function calculateForUser(int $userId): array {
    $now = $this->time->getRequestTime();
    $periodStart = $now - (self::SCORING_PERIOD_DAYS * 86400);

    // Get raw metrics from event log.
    $metrics = $this->getRawMetrics($userId, $periodStart);

    // Get user registration date.
    $user = $this->entityTypeManager->getStorage('user')->load($userId);
    $registrationDate = $user ? (int) $user->getCreatedTime() : $now;

    // Calculate days.
    $daysSinceLast = $metrics['last_activity'] > 0
      ? ($now - $metrics['last_activity']) / 86400
      : 999;
    $daysSinceRegistration = (int) (($now - $registrationDate) / 86400);

    // Get thresholds from config.
    $thresholds = $this->config->getThresholds();

    // Normalize to 1-5 scores.
    $recencyScore = $this->normalizeRecency($daysSinceLast, $thresholds['recency']);
    $frequencyScore = $this->normalizeFrequency($metrics['event_count'], $thresholds['frequency']);
    $valueScore = $this->normalizeValue($metrics['total_value'], $thresholds['value']);

    $totalScore = $recencyScore + $frequencyScore + $valueScore;

    // Determine segment.
    $segment = $this->segmenter->determineSegment(
      $recencyScore,
      $frequencyScore,
      $valueScore,
      $daysSinceRegistration
    );

    $result = [
      'user_id' => $userId,
      'last_activity' => $metrics['last_activity'],
      'event_count' => $metrics['event_count'],
      'total_value' => $metrics['total_value'],
      'first_activity' => $metrics['first_activity'],
      'recency_score' => $recencyScore,
      'frequency_score' => $frequencyScore,
      'value_score' => $valueScore,
      'total_score' => $totalScore,
      'segment' => $segment,
      'calculated' => $now,
    ];

    // Save to cache table.
    $this->saveScore($result);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function recalculateAll(): int {
    $userIds = $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid'])
      ->condition('u.uid', 0, '>')
      ->condition('u.status', 1)
      ->execute()
      ->fetchCol();

    $count = 0;
    foreach ($userIds as $userId) {
      $this->calculateForUser((int) $userId);
      $count++;
    }

    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function recalculateStale(): int {
    $staleThreshold = $this->time->getRequestTime() - 3600;

    $userIds = $this->database->select('oi_engagement_score', 's')
      ->fields('s', ['user_id'])
      ->condition('s.calculated', $staleThreshold, '<')
      ->range(0, 100)
      ->execute()
      ->fetchCol();

    $count = 0;
    foreach ($userIds as $userId) {
      $this->calculateForUser((int) $userId);
      $count++;
    }

    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore(int $userId): ?array {
    $score = $this->database->select('oi_engagement_score', 's')
      ->fields('s')
      ->condition('s.user_id', $userId)
      ->execute()
      ->fetchAssoc();

    if (!$score) {
      return NULL;
    }

    // Check if stale (older than 1 hour).
    $staleThreshold = $this->time->getRequestTime() - 3600;
    if ((int) $score['calculated'] < $staleThreshold) {
      return $this->calculateForUser($userId);
    }

    return $score;
  }

  /**
   * Gets raw metrics from event log.
   *
   * @param int $userId
   *   The user ID.
   * @param int $since
   *   Timestamp to count events from.
   *
   * @return array{last_activity: int, first_activity: int, event_count: int, total_value: int}
   *   Raw metrics.
   */
  private function getRawMetrics(int $userId, int $since): array {
    $query = $this->database->select('oi_engagement_event', 'e')
      ->condition('e.user_id', $userId)
      ->condition('e.created', $since, '>=');

    $query->addExpression('MAX(e.created)', 'last_activity');
    $query->addExpression('MIN(e.created)', 'first_activity');
    $query->addExpression('COUNT(*)', 'event_count');
    $query->addExpression('COALESCE(SUM(e.event_value), 0)', 'total_value');

    $result = $query->execute()->fetchAssoc();

    return [
      'last_activity' => (int) ($result['last_activity'] ?? 0),
      'first_activity' => (int) ($result['first_activity'] ?? 0),
      'event_count' => (int) ($result['event_count'] ?? 0),
      'total_value' => (int) ($result['total_value'] ?? 0),
    ];
  }

  /**
   * Normalizes recency to 1-5 score.
   *
   * @param float $days
   *   Days since last activity.
   * @param array $thresholds
   *   Recency thresholds [1day, 7days, 14days, 30days].
   *
   * @return int
   *   Score 1-5 (5 = most recent).
   */
  private function normalizeRecency(float $days, array $thresholds): int {
    return match (TRUE) {
      $days <= ($thresholds[0] ?? 1) => 5,
      $days <= ($thresholds[1] ?? 7) => 4,
      $days <= ($thresholds[2] ?? 14) => 3,
      $days <= ($thresholds[3] ?? 30) => 2,
      default => 1,
    };
  }

  /**
   * Normalizes frequency to 1-5 score.
   *
   * @param int $count
   *   Event count in period.
   * @param array $thresholds
   *   Frequency thresholds [5, 20, 50, 100].
   *
   * @return int
   *   Score 1-5 (5 = most frequent).
   */
  private function normalizeFrequency(int $count, array $thresholds): int {
    return match (TRUE) {
      $count >= ($thresholds[3] ?? 100) => 5,
      $count >= ($thresholds[2] ?? 50) => 4,
      $count >= ($thresholds[1] ?? 20) => 3,
      $count >= ($thresholds[0] ?? 5) => 2,
      default => 1,
    };
  }

  /**
   * Normalizes value to 1-5 score.
   *
   * @param int $value
   *   Total value in period.
   * @param array $thresholds
   *   Value thresholds [20, 50, 100, 200].
   *
   * @return int
   *   Score 1-5 (5 = most valuable).
   */
  private function normalizeValue(int $value, array $thresholds): int {
    return match (TRUE) {
      $value >= ($thresholds[3] ?? 200) => 5,
      $value >= ($thresholds[2] ?? 100) => 4,
      $value >= ($thresholds[1] ?? 50) => 3,
      $value >= ($thresholds[0] ?? 20) => 2,
      default => 1,
    };
  }

  /**
   * Saves score to cache table.
   *
   * @param array $data
   *   Score data to save.
   */
  private function saveScore(array $data): void {
    $this->database->merge('oi_engagement_score')
      ->keys(['user_id' => $data['user_id']])
      ->fields($data)
      ->execute();
  }

}
