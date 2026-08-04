<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Commands;

use Drupal\openintranet_engagement\Service\OiEngagementCalculatorInterface;
use Drupal\openintranet_engagement\Service\OiEngagementCleaner;
use Drupal\openintranet_engagement\Service\OiEngagementDailyAggregator;
use Drupal\openintranet_engagement\Service\OiEngagementReporterInterface;
use Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for engagement management.
 */
final class EngagementCommands extends DrushCommands {

  /**
   * Constructs the EngagementCommands.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementCalculatorInterface $calculator
   *   The calculator service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementReporterInterface $reporter
   *   The reporter service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface $segmenter
   *   The segmenter service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementCleaner $cleaner
   *   The cleaner service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementDailyAggregator $aggregator
   *   The daily aggregator service.
   */
  public function __construct(
    private readonly OiEngagementCalculatorInterface $calculator,
    private readonly OiEngagementReporterInterface $reporter,
    private readonly OiEngagementSegmenterInterface $segmenter,
    private readonly OiEngagementCleaner $cleaner,
    private readonly OiEngagementDailyAggregator $aggregator,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('openintranet_engagement.calculator'),
      $container->get('openintranet_engagement.reporter'),
      $container->get('openintranet_engagement.segmenter'),
      $container->get('openintranet_engagement.cleaner'),
      $container->get('openintranet_engagement.daily_aggregator'),
    );
  }

  /**
   * Recalculates engagement scores for all users.
   */
  #[CLI\Command(name: 'engagement:recalculate', aliases: ['eng-recalc'])]
  #[CLI\Usage(name: 'engagement:recalculate', description: 'Recalculate scores for all active users')]
  public function recalculate(): void {
    $this->logger()->notice('Starting score recalculation...');

    $count = $this->calculator->recalculateAll();

    $this->logger()->success(dt('Recalculated scores for @count users.', ['@count' => $count]));
  }

  /**
   * Shows segment distribution.
   */
  #[CLI\Command(name: 'engagement:segments', aliases: ['eng-seg'])]
  #[CLI\Usage(name: 'engagement:segments', description: 'Display segment distribution')]
  public function segments(): void {
    $distribution = $this->reporter->getSegmentDistribution();
    $total = array_sum($distribution);

    $this->output()->writeln('');
    $this->output()->writeln('<info>User Segment Distribution</info>');
    $this->output()->writeln(str_repeat('-', 50));

    foreach ($distribution as $segment => $count) {
      $def = $this->segmenter->getSegmentDefinition($segment);
      $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;

      $this->output()->writeln(sprintf(
        '%s %-12s: %5d users (%5.1f%%)',
        $def['icon'],
        $def['label'],
        $count,
        $percentage
      ));
    }

    $this->output()->writeln(str_repeat('-', 50));
    $this->output()->writeln(sprintf('Total: %d users', $total));
    $this->output()->writeln('');
  }

  /**
   * Shows executive summary.
   */
  #[CLI\Command(name: 'engagement:summary', aliases: ['eng-sum'])]
  #[CLI\Usage(name: 'engagement:summary', description: 'Display executive summary')]
  public function summary(): void {
    $summary = $this->reporter->getExecutiveSummary();

    $this->output()->writeln('');
    $this->output()->writeln('<info>Engagement Executive Summary</info>');
    $this->output()->writeln(str_repeat('-', 50));

    $this->output()->writeln(sprintf('Total Users:         %d', $summary['total_users']));
    $this->output()->writeln(sprintf('Active (7d):         %d', $summary['active_users_7d']));
    $this->output()->writeln(sprintf('Active (30d):        %d', $summary['active_users_30d']));
    $this->output()->writeln(sprintf('Events (30d):        %d', $summary['total_events_30d']));
    $this->output()->writeln('');
    $this->output()->writeln(sprintf('Adoption Rate:       %.1f%%', $summary['adoption_rate']));
    $this->output()->writeln(sprintf('Engagement Rate:     %.1f', $summary['engagement_rate']));
    $this->output()->writeln(sprintf('At Risk:             %.1f%%', $summary['at_risk_percentage']));
    $this->output()->writeln(sprintf('Champions:           %.1f%%', $summary['champion_percentage']));

    $this->output()->writeln(str_repeat('-', 50));
    $this->output()->writeln('');
  }

  /**
   * Shows top engaged users.
   *
   * @param int $limit
   *   Number of users to show.
   */
  #[CLI\Command(name: 'engagement:top', aliases: ['eng-top'])]
  #[CLI\Argument(name: 'limit', description: 'Number of users to show (default 10)')]
  #[CLI\Usage(name: 'engagement:top 20', description: 'Show top 20 engaged users')]
  public function top(int $limit = 10): void {
    $users = $this->reporter->getTopUsers($limit);

    $this->output()->writeln('');
    $this->output()->writeln('<info>Top Engaged Users</info>');
    $this->output()->writeln(str_repeat('-', 70));
    $this->output()->writeln(sprintf('%-4s %-25s %-12s %3s %3s %3s %5s', '#', 'Name', 'Segment', 'R', 'F', 'V', 'Total'));
    $this->output()->writeln(str_repeat('-', 70));

    $rank = 1;
    foreach ($users as $user) {
      $def = $this->segmenter->getSegmentDefinition($user['segment']);

      $this->output()->writeln(sprintf(
        '%-4d %-25s %s %-10s %3d %3d %3d %5d',
        $rank,
        substr($user['name'], 0, 25),
        $def['icon'],
        $def['label'],
        $user['recency_score'],
        $user['frequency_score'],
        $user['value_score'],
        $user['total_score']
      ));
      $rank++;
    }

    $this->output()->writeln(str_repeat('-', 70));
    $this->output()->writeln('');
  }

  /**
   * Cleans up old event data.
   *
   * @param int|null $days
   *   Number of days to retain. Uses config if not provided.
   */
  #[CLI\Command(name: 'engagement:cleanup', aliases: ['eng-clean'])]
  #[CLI\Argument(name: 'days', description: 'Days to retain (default from config)')]
  #[CLI\Usage(name: 'engagement:cleanup 60', description: 'Delete events older than 60 days')]
  public function cleanup(?int $days = NULL): void {
    $this->logger()->notice('Starting cleanup...');

    $results = $this->cleaner->cleanAll();

    $this->logger()->success(dt('Cleanup complete:'));
    $this->logger()->success(dt('  - Events deleted: @count', ['@count' => $results['events']]));
    $this->logger()->success(dt('  - Orphaned scores deleted: @count', ['@count' => $results['scores']]));
    $this->logger()->success(dt('  - Daily stats deleted: @count', ['@count' => $results['stats']]));
  }

  /**
   * Aggregates daily statistics.
   *
   * @param string|null $date
   *   Date to aggregate (Y-m-d format). Defaults to yesterday.
   */
  #[CLI\Command(name: 'engagement:aggregate', aliases: ['eng-agg'])]
  #[CLI\Argument(name: 'date', description: 'Date to aggregate (Y-m-d, default yesterday)')]
  #[CLI\Usage(name: 'engagement:aggregate 2024-01-15', description: 'Aggregate stats for specific date')]
  public function aggregate(?string $date = NULL): void {
    if ($date) {
      $count = $this->aggregator->aggregateDate($date);
    }
    else {
      $count = $this->aggregator->aggregateYesterday();
      $date = date('Y-m-d', time() - 86400);
    }

    $this->logger()->success(dt('Aggregated @count records for @date.', [
      '@count' => $count,
      '@date' => $date,
    ]));
  }

  /**
   * Exports engagement data to CSV.
   *
   * @param string $file
   *   Output file path.
   * @param string|null $segment
   *   Filter by segment (optional).
   */
  #[CLI\Command(name: 'engagement:export', aliases: ['eng-export'])]
  #[CLI\Argument(name: 'file', description: 'Output file path')]
  #[CLI\Option(name: 'segment', description: 'Filter by segment')]
  #[CLI\Usage(name: 'engagement:export /tmp/users.csv --segment=at_risk', description: 'Export at-risk users')]
  public function export(string $file, ?string $segment = NULL): void {
    $filters = [];
    if ($segment) {
      $filters['segment'] = $segment;
    }

    $csv = $this->reporter->exportCsv($filters, 'users');

    if (file_put_contents($file, $csv) !== FALSE) {
      $this->logger()->success(dt('Exported data to @file.', ['@file' => $file]));
    }
    else {
      $this->logger()->error(dt('Failed to write to @file.', ['@file' => $file]));
    }
  }

}
