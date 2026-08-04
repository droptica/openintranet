<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Generates engagement reports and analytics.
 */
final class OiEngagementReporter implements OiEngagementReporterInterface {

  /**
   * Constructs the OiEngagementReporter service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface $segmenter
   *   The segmenter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OiEngagementSegmenterInterface $segmenter,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getSegmentDistribution(): array {
    $query = $this->database->select('oi_engagement_score', 's')
      ->fields('s', ['segment']);
    $query->addExpression('COUNT(*)', 'count');
    $query->groupBy('s.segment');

    $results = $query->execute()->fetchAllKeyed();

    // Ensure all segments are present.
    $segments = $this->segmenter->getAllSegments();
    $distribution = [];
    foreach (array_keys($segments) as $segment) {
      $distribution[$segment] = (int) ($results[$segment] ?? 0);
    }

    return $distribution;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsersBySegment(
    string $segment,
    int $page = 0,
    int $perPage = 50,
    string $sortBy = 'total_score',
    string $sortDir = 'DESC'
  ): array {
    // Count total.
    $total = (int) $this->database->select('oi_engagement_score', 's')
      ->condition('s.segment', $segment)
      ->countQuery()
      ->execute()
      ->fetchField();

    // Get users.
    $query = $this->database->select('oi_engagement_score', 's')
      ->fields('s')
      ->condition('s.segment', $segment)
      ->orderBy('s.' . $sortBy, $sortDir)
      ->range($page * $perPage, $perPage);

    $results = $query->execute()->fetchAll();

    // Enrich with user names.
    $users = [];
    foreach ($results as $row) {
      $user = $this->entityTypeManager->getStorage('user')->load($row->user_id);
      if ($user) {
        $users[] = [
          'user_id' => (int) $row->user_id,
          'name' => $user->getDisplayName(),
          'email' => $user->getEmail(),
          'segment' => $row->segment,
          'recency_score' => (int) $row->recency_score,
          'frequency_score' => (int) $row->frequency_score,
          'value_score' => (int) $row->value_score,
          'total_score' => (int) $row->total_score,
          'last_activity' => (int) $row->last_activity,
          'event_count' => (int) $row->event_count,
        ];
      }
    }

    return [
      'users' => $users,
      'total' => $total,
      'page' => $page,
      'per_page' => $perPage,
      'pages' => (int) ceil($total / $perPage),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTopUsers(int $limit = 10): array {
    $query = $this->database->select('oi_engagement_score', 's')
      ->fields('s')
      ->orderBy('s.total_score', 'DESC')
      ->orderBy('s.event_count', 'DESC')
      ->range(0, $limit);

    $results = $query->execute()->fetchAll();

    $users = [];
    foreach ($results as $row) {
      $user = $this->entityTypeManager->getStorage('user')->load($row->user_id);
      if ($user) {
        $users[] = [
          'user_id' => (int) $row->user_id,
          'name' => $user->getDisplayName(),
          'segment' => $row->segment,
          'recency_score' => (int) $row->recency_score,
          'frequency_score' => (int) $row->frequency_score,
          'value_score' => (int) $row->value_score,
          'total_score' => (int) $row->total_score,
          'last_activity' => (int) $row->last_activity,
        ];
      }
    }

    return $users;
  }

  /**
   * {@inheritdoc}
   */
  public function getActivityTrends(int $days = 30): array {
    $since = $this->time->getRequestTime() - ($days * 86400);

    $query = $this->database->select('oi_engagement_event', 'e')
      ->condition('e.created', $since, '>=');
    $query->addExpression("DATE(FROM_UNIXTIME(e.created))", 'date');
    $query->addExpression('COUNT(*)', 'event_count');
    $query->addExpression('COUNT(DISTINCT e.user_id)', 'unique_users');
    $query->addExpression('SUM(e.event_value)', 'total_value');
    $query->groupBy('date');
    $query->orderBy('date', 'ASC');

    $results = $query->execute()->fetchAll();

    $trends = [];
    foreach ($results as $row) {
      $trends[$row->date] = [
        'date' => $row->date,
        'event_count' => (int) $row->event_count,
        'unique_users' => (int) $row->unique_users,
        'total_value' => (int) $row->total_value,
      ];
    }

    return $trends;
  }

  /**
   * {@inheritdoc}
   */
  public function getEventTypeBreakdown(int $days = 30): array {
    $since = $this->time->getRequestTime() - ($days * 86400);

    $query = $this->database->select('oi_engagement_event', 'e')
      ->fields('e', ['event_type'])
      ->condition('e.created', $since, '>=');
    $query->addExpression('COUNT(*)', 'count');
    $query->addExpression('SUM(e.event_value)', 'total_value');
    $query->addExpression('COUNT(DISTINCT e.user_id)', 'unique_users');
    $query->groupBy('e.event_type');
    $query->orderBy('count', 'DESC');

    $results = $query->execute()->fetchAll();

    $breakdown = [];
    foreach ($results as $row) {
      $breakdown[] = [
        'event_type' => $row->event_type,
        'count' => (int) $row->count,
        'total_value' => (int) $row->total_value,
        'unique_users' => (int) $row->unique_users,
      ];
    }

    return $breakdown;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutiveSummary(): array {
    $now = $this->time->getRequestTime();
    $sevenDaysAgo = $now - (7 * 86400);
    $thirtyDaysAgo = $now - (30 * 86400);

    // Total registered users (active accounts).
    $totalUsers = (int) $this->database->select('users_field_data', 'u')
      ->condition('u.uid', 0, '>')
      ->condition('u.status', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    // Active users in last 7 days.
    $activeUsers7d = (int) $this->database->select('oi_engagement_event', 'e')
      ->distinct()
      ->fields('e', ['user_id'])
      ->condition('e.created', $sevenDaysAgo, '>=')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Active users in last 30 days.
    $activeUsers30d = (int) $this->database->select('oi_engagement_event', 'e')
      ->distinct()
      ->fields('e', ['user_id'])
      ->condition('e.created', $thirtyDaysAgo, '>=')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Total events in 30 days.
    $totalEvents30d = (int) $this->database->select('oi_engagement_event', 'e')
      ->condition('e.created', $thirtyDaysAgo, '>=')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Average score.
    $query = $this->database->select('oi_engagement_score', 's');
    $query->addExpression('AVG(s.total_score)', 'avg');
    $avgScore = (float) $query->execute()->fetchField();

    // Segment counts.
    $segments = $this->getSegmentDistribution();
    $atRiskCount = ($segments['at_risk'] ?? 0) + ($segments['dormant'] ?? 0);
    $championCount = $segments['champion'] ?? 0;

    // Calculate rates.
    $adoptionRate = $totalUsers > 0 ? ($activeUsers30d / $totalUsers) * 100 : 0;
    $engagementRate = $activeUsers30d > 0 ? ($totalEvents30d / $activeUsers30d) : 0;
    $atRiskPercentage = $totalUsers > 0 ? ($atRiskCount / $totalUsers) * 100 : 0;
    $championPercentage = $totalUsers > 0 ? ($championCount / $totalUsers) * 100 : 0;

    return [
      'total_users' => $totalUsers,
      'active_users_7d' => $activeUsers7d,
      'active_users_30d' => $activeUsers30d,
      'total_events_30d' => $totalEvents30d,
      'avg_score' => round($avgScore, 1),
      'adoption_rate' => round($adoptionRate, 1),
      'engagement_rate' => round($engagementRate, 1),
      'at_risk_percentage' => round($atRiskPercentage, 1),
      'champion_percentage' => round($championPercentage, 1),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMostViewedContent(int $limit = 20, int $days = 30): array {
    $since = $this->time->getRequestTime() - ($days * 86400);

    $query = $this->database->select('oi_engagement_event', 'e')
      ->fields('e', ['entity_type', 'entity_id'])
      ->condition('e.event_type', '%_view', 'LIKE')
      ->condition('e.created', $since, '>=')
      ->isNotNull('e.entity_id');
    $query->addExpression('COUNT(*)', 'view_count');
    $query->addExpression('COUNT(DISTINCT e.user_id)', 'unique_viewers');
    $query->groupBy('e.entity_type');
    $query->groupBy('e.entity_id');
    $query->orderBy('view_count', 'DESC');
    $query->range(0, $limit);

    $results = $query->execute()->fetchAll();

    $content = [];
    foreach ($results as $row) {
      try {
        $entity = $this->entityTypeManager
          ->getStorage($row->entity_type)
          ->load($row->entity_id);

        if ($entity) {
          $content[] = [
            'entity_type' => $row->entity_type,
            'entity_id' => (int) $row->entity_id,
            'label' => $entity->label(),
            'url' => $entity->toUrl()->toString(),
            'view_count' => (int) $row->view_count,
            'unique_viewers' => (int) $row->unique_viewers,
          ];
        }
      }
      catch (\Exception $e) {
        // Skip if entity type doesn't exist or entity was deleted.
        continue;
      }
    }

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function exportCsv(array $filters = [], string $type = 'users'): string {
    $rows = [];

    if ($type === 'users') {
      $rows[] = ['User ID', 'Name', 'Email', 'Segment', 'R', 'F', 'V', 'Total', 'Last Activity', 'Events'];

      $query = $this->database->select('oi_engagement_score', 's')
        ->fields('s');

      if (!empty($filters['segment'])) {
        $query->condition('s.segment', $filters['segment']);
      }

      $query->orderBy('s.total_score', 'DESC');
      $results = $query->execute()->fetchAll();

      foreach ($results as $row) {
        $user = $this->entityTypeManager->getStorage('user')->load($row->user_id);
        if ($user) {
          $rows[] = [
            $row->user_id,
            $user->getDisplayName(),
            $user->getEmail(),
            $row->segment,
            $row->recency_score,
            $row->frequency_score,
            $row->value_score,
            $row->total_score,
            date('Y-m-d H:i', (int) $row->last_activity),
            $row->event_count,
          ];
        }
      }
    }

    // Convert to CSV string.
    $output = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
      fputcsv($output, $row);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
  }

  /**
   * {@inheritdoc}
   */
  public function getEngagementByDepartment(int $days = 30): array {
    // Check if department field exists on users.
    $fieldDefinitions = $this->entityTypeManager
      ->getDefinition('user')
      ->get('field_definitions') ?? [];

    // Look for common department field names.
    $departmentField = NULL;
    $possibleFields = ['field_department', 'field_user_department', 'field_division', 'field_team'];

    try {
      $fieldStorage = $this->entityTypeManager->getStorage('field_storage_config');
      foreach ($possibleFields as $fieldName) {
        $field = $fieldStorage->load('user.' . $fieldName);
        if ($field) {
          $departmentField = $fieldName;
          break;
        }
      }
    }
    catch (\Exception $e) {
      // Field config may not be available.
    }

    if (!$departmentField) {
      return [];
    }

    $since = $this->time->getRequestTime() - ($days * 86400);

    // Get all users with department field.
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple();
    $departmentData = [];

    foreach ($users as $user) {
      if (!$user->hasField($departmentField) || $user->get($departmentField)->isEmpty()) {
        continue;
      }

      $department = $user->get($departmentField)->entity
        ? $user->get($departmentField)->entity->label()
        : $user->get($departmentField)->value;

      if (!$department) {
        continue;
      }

      if (!isset($departmentData[$department])) {
        $departmentData[$department] = [
          'department' => $department,
          'user_count' => 0,
          'total_score' => 0,
          'active_users' => 0,
          'champion_count' => 0,
          'at_risk_count' => 0,
          'dormant_count' => 0,
        ];
      }

      $departmentData[$department]['user_count']++;

      // Get user's score.
      $score = $this->database->select('oi_engagement_score', 's')
        ->fields('s', ['total_score', 'segment', 'last_activity'])
        ->condition('s.user_id', $user->id())
        ->execute()
        ->fetchAssoc();

      if ($score) {
        $departmentData[$department]['total_score'] += (int) $score['total_score'];

        if ((int) $score['last_activity'] >= $since) {
          $departmentData[$department]['active_users']++;
        }

        if ($score['segment'] === 'champion') {
          $departmentData[$department]['champion_count']++;
        }
        elseif ($score['segment'] === 'at_risk') {
          $departmentData[$department]['at_risk_count']++;
        }
        elseif ($score['segment'] === 'dormant') {
          $departmentData[$department]['dormant_count']++;
        }
      }
    }

    // Calculate averages.
    foreach ($departmentData as &$dept) {
      $dept['avg_score'] = $dept['user_count'] > 0
        ? round($dept['total_score'] / $dept['user_count'], 1)
        : 0;
      unset($dept['total_score']);
    }

    // Sort by avg_score descending.
    uasort($departmentData, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);

    return array_values($departmentData);
  }

  /**
   * {@inheritdoc}
   */
  public function getEngagementByRole(int $days = 30): array {
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    $roleData = [];

    foreach ($roles as $rid => $role) {
      if ($rid === 'authenticated') {
        continue;
      }

      // Get users with this role.
      $query = $this->database->select('user__roles', 'ur')
        ->fields('ur', ['entity_id'])
        ->condition('ur.roles_target_id', $rid);
      $userIds = $query->execute()->fetchCol();

      if (empty($userIds)) {
        continue;
      }

      // Get scores for these users.
      $scoreQuery = $this->database->select('oi_engagement_score', 's')
        ->fields('s', ['segment'])
        ->condition('s.user_id', $userIds, 'IN');
      $scoreQuery->addExpression('AVG(s.total_score)', 'avg_score');
      $scoreQuery->addExpression('COUNT(*)', 'count');
      $scoreQuery->groupBy('s.segment');

      $scores = $scoreQuery->execute()->fetchAll();

      $segmentDistribution = [];
      $totalScore = 0;
      $totalUsers = 0;

      foreach ($scores as $row) {
        $segmentDistribution[$row->segment] = (int) $row->count;
        $totalUsers += (int) $row->count;
      }

      // Get overall avg score.
      $avgQuery = $this->database->select('oi_engagement_score', 's')
        ->condition('s.user_id', $userIds, 'IN');
      $avgQuery->addExpression('AVG(s.total_score)', 'avg');
      $avgScore = (float) $avgQuery->execute()->fetchField();

      $roleData[$rid] = [
        'role' => $rid,
        'role_label' => $role->label(),
        'user_count' => count($userIds),
        'avg_score' => round($avgScore, 1),
        'segment_distribution' => $segmentDistribution,
      ];
    }

    // Sort by avg_score descending.
    uasort($roleData, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);

    return array_values($roleData);
  }

  /**
   * {@inheritdoc}
   */
  public function getTopContentCreators(int $limit = 10, int $days = 30): array {
    $since = $this->time->getRequestTime() - ($days * 86400);

    $query = $this->database->select('oi_engagement_event', 'e')
      ->fields('e', ['user_id'])
      ->condition('e.event_type', '%_create', 'LIKE')
      ->condition('e.created', $since, '>=');
    $query->addExpression('COUNT(*)', 'content_count');
    $query->addExpression('SUM(e.event_value)', 'total_value');
    $query->groupBy('e.user_id');
    $query->orderBy('content_count', 'DESC');
    $query->range(0, $limit);

    $results = $query->execute()->fetchAll();

    $creators = [];
    foreach ($results as $row) {
      $user = $this->entityTypeManager->getStorage('user')->load($row->user_id);
      if ($user) {
        // Get user's score.
        $score = $this->database->select('oi_engagement_score', 's')
          ->fields('s', ['total_score', 'segment'])
          ->condition('s.user_id', $row->user_id)
          ->execute()
          ->fetchAssoc();

        $creators[] = [
          'user_id' => (int) $row->user_id,
          'name' => $user->getDisplayName(),
          'content_count' => (int) $row->content_count,
          'total_value' => (int) $row->total_value,
          'total_score' => $score ? (int) $score['total_score'] : 0,
          'segment' => $score ? $score['segment'] : 'unknown',
        ];
      }
    }

    return $creators;
  }

}
