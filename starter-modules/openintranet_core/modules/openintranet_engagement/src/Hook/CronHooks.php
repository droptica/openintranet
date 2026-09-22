<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\openintranet_engagement\Service\OiEngagementCalculatorInterface;
use Drupal\openintranet_engagement\Service\OiEngagementCleaner;
use Drupal\openintranet_engagement\Service\OiEngagementDailyAggregator;

/**
 * Cron hooks for engagement maintenance tasks.
 */
final class CronHooks {

  /**
   * Constructs the CronHooks class.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementCalculatorInterface $calculator
   *   The calculator service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementDailyAggregator $aggregator
   *   The daily aggregator service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementCleaner $cleaner
   *   The cleaner service.
   */
  public function __construct(
    private readonly OiEngagementCalculatorInterface $calculator,
    private readonly OiEngagementDailyAggregator $aggregator,
    private readonly OiEngagementCleaner $cleaner,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function onCron(): void {
    // Recalculate stale scores (up to 100 per cron run).
    $this->calculator->recalculateStale();

    // Aggregate yesterday's stats.
    $this->aggregator->aggregateYesterday();

    // Clean up old event logs based on retention setting.
    $this->cleaner->cleanOldEvents();
  }

}
