<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_engagement\Service\OiEngagementReporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for activity patterns reports.
 */
final class PatternsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the PatternsController.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementReporterInterface $reporter
   *   The reporter service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly OiEngagementReporterInterface $reporter,
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('openintranet_engagement.reporter'),
      $container->get('database'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Displays activity patterns report.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   The render array.
   */
  public function patterns(Request $request): array {
    $days = (int) $request->query->get('days', 30);

    // Get activity trends.
    $trends = $this->reporter->getActivityTrends($days);

    // Get hourly distribution.
    $hourly = $this->getHourlyDistribution($days);

    // Get daily distribution (day of week).
    $daily = $this->getDailyDistribution($days);

    // Find peak hours.
    $peakHour = !empty($hourly) ? array_search(max($hourly), $hourly) : 10;

    // Find best day.
    $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $bestDay = !empty($daily) ? $dayNames[array_search(max($daily), $daily)] ?? 'Tuesday' : 'Tuesday';

    return [
      '#theme' => 'oi_engagement_activity_patterns',
      '#trends' => $trends,
      '#hourly' => $hourly,
      '#daily' => $daily,
      '#peak_hour' => $peakHour,
      '#best_day' => $bestDay,
      '#days' => $days,
      '#day_names' => $dayNames,
      '#attached' => [
        'library' => ['openintranet_engagement/dashboard'],
      ],
    ];
  }

  /**
   * Gets hourly activity distribution.
   *
   * @param int $days
   *   Number of days to include.
   *
   * @return array<int, int>
   *   Hour (0-23) => event count.
   */
  private function getHourlyDistribution(int $days): array {
    $since = $this->time->getRequestTime() - ($days * 86400);

    $query = $this->database->select('oi_engagement_event', 'e')
      ->condition('e.created', $since, '>=');
    $query->addExpression("HOUR(FROM_UNIXTIME(e.created))", 'hour');
    $query->addExpression('COUNT(*)', 'count');
    $query->groupBy('hour');

    $results = $query->execute()->fetchAllKeyed();

    // Ensure all hours are present.
    $hourly = [];
    for ($h = 0; $h < 24; $h++) {
      $hourly[$h] = (int) ($results[$h] ?? 0);
    }

    return $hourly;
  }

  /**
   * Gets daily activity distribution (day of week).
   *
   * @param int $days
   *   Number of days to include.
   *
   * @return array<int, int>
   *   Day of week (1=Monday, 7=Sunday) => event count.
   */
  private function getDailyDistribution(int $days): array {
    $since = $this->time->getRequestTime() - ($days * 86400);

    $query = $this->database->select('oi_engagement_event', 'e')
      ->condition('e.created', $since, '>=');
    $query->addExpression("DAYOFWEEK(FROM_UNIXTIME(e.created))", 'dow');
    $query->addExpression('COUNT(*)', 'count');
    $query->groupBy('dow');

    $results = $query->execute()->fetchAllKeyed();

    // Convert MySQL DAYOFWEEK (1=Sunday) to ISO (1=Monday).
    $daily = [];
    for ($d = 1; $d <= 7; $d++) {
      // MySQL: 1=Sun, 2=Mon...7=Sat -> ISO: 1=Mon...7=Sun.
      $mysqlDay = $d == 7 ? 1 : $d + 1;
      $daily[$d] = (int) ($results[$mysqlDay] ?? 0);
    }

    return $daily;
  }

}
