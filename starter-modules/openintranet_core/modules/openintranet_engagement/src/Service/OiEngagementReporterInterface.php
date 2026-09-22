<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

/**
 * Interface for engagement reporting service.
 */
interface OiEngagementReporterInterface {

  /**
   * Gets segment distribution (how many users in each segment).
   *
   * @return array<string, int>
   *   Segment counts keyed by segment name.
   */
  public function getSegmentDistribution(): array;

  /**
   * Gets users by segment with pagination.
   *
   * @param string $segment
   *   Segment name (champion, loyal, at_risk, dormant, new, regular).
   * @param int $page
   *   Page number (0-indexed).
   * @param int $perPage
   *   Items per page.
   * @param string $sortBy
   *   Sort field: 'total_score', 'last_activity', 'event_count', 'name'.
   * @param string $sortDir
   *   Sort direction: 'ASC' or 'DESC'.
   *
   * @return array{users: array, total: int, page: int, per_page: int, pages: int}
   *   Paginated user list.
   */
  public function getUsersBySegment(
    string $segment,
    int $page = 0,
    int $perPage = 50,
    string $sortBy = 'total_score',
    string $sortDir = 'DESC'
  ): array;

  /**
   * Gets top users by total score.
   *
   * @param int $limit
   *   Number of users to return.
   *
   * @return array
   *   Array of user data with scores.
   */
  public function getTopUsers(int $limit = 10): array;

  /**
   * Gets activity trends over time.
   *
   * @param int $days
   *   Number of days to include.
   *
   * @return array<string, array{date: string, event_count: int, unique_users: int, total_value: int}>
   *   Trends keyed by date.
   */
  public function getActivityTrends(int $days = 30): array;

  /**
   * Gets event type breakdown.
   *
   * @param int $days
   *   Number of days to include.
   *
   * @return array
   *   Event type breakdown with counts and values.
   */
  public function getEventTypeBreakdown(int $days = 30): array;

  /**
   * Gets executive summary (KPIs for dashboard).
   *
   * @return array{
   *   total_users: int,
   *   active_users_7d: int,
   *   active_users_30d: int,
   *   total_events_30d: int,
   *   avg_score: float,
   *   adoption_rate: float,
   *   engagement_rate: float,
   *   at_risk_percentage: float,
   *   champion_percentage: float
   * }
   *   Executive summary metrics.
   */
  public function getExecutiveSummary(): array;

  /**
   * Gets most viewed content.
   *
   * @param int $limit
   *   Number of items to return.
   * @param int $days
   *   Number of days to include.
   *
   * @return array
   *   Array of content with view counts.
   */
  public function getMostViewedContent(int $limit = 20, int $days = 30): array;

  /**
   * Exports data as CSV.
   *
   * @param array $filters
   *   Optional filters: segment, date_from, date_to.
   * @param string $type
   *   Export type: 'users', 'events', 'summary'.
   *
   * @return string
   *   CSV content.
   */
  public function exportCsv(array $filters = [], string $type = 'users'): string;

  /**
   * Gets engagement by department (requires user field).
   *
   * @param int $days
   *   Number of days to include.
   *
   * @return array<string, array{
   *   department: string,
   *   user_count: int,
   *   avg_score: float,
   *   active_users: int,
   *   champion_count: int,
   *   at_risk_count: int
   * }>
   *   Department engagement data.
   */
  public function getEngagementByDepartment(int $days = 30): array;

  /**
   * Gets engagement by user role.
   *
   * @param int $days
   *   Number of days to include.
   *
   * @return array<string, array{
   *   role: string,
   *   role_label: string,
   *   user_count: int,
   *   avg_score: float,
   *   segment_distribution: array
   * }>
   *   Role engagement data.
   */
  public function getEngagementByRole(int $days = 30): array;

  /**
   * Gets top content creators.
   *
   * @param int $limit
   *   Number of users to return.
   * @param int $days
   *   Number of days to include.
   *
   * @return array
   *   Array of top creators with counts.
   */
  public function getTopContentCreators(int $limit = 10, int $days = 30): array;

}
