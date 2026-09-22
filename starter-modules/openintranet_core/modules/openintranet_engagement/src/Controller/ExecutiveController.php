<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_engagement\Service\OiEngagementReporterInterface;
use Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for executive summary page.
 */
final class ExecutiveController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the ExecutiveController.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementReporterInterface $reporter
   *   The reporter service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface $segmenter
   *   The segmenter service.
   */
  public function __construct(
    private readonly OiEngagementReporterInterface $reporter,
    private readonly OiEngagementSegmenterInterface $segmenter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('openintranet_engagement.reporter'),
      $container->get('openintranet_engagement.segmenter'),
    );
  }

  /**
   * Displays the executive summary.
   *
   * @return array
   *   The render array.
   */
  public function summary(): array {
    $summary = $this->reporter->getExecutiveSummary();

    // Build KPI cards.
    $kpis = [
      [
        'title' => $this->t('Adoption Rate'),
        'value' => $summary['adoption_rate'] . '%',
        'icon' => '📈',
        'description' => $this->t('@active of @total users active in 30 days', [
          '@active' => $summary['active_users_30d'],
          '@total' => $summary['total_users'],
        ]),
        'status' => $summary['adoption_rate'] >= 70 ? 'good' : ($summary['adoption_rate'] >= 50 ? 'warning' : 'danger'),
      ],
      [
        'title' => $this->t('Engagement Rate'),
        'value' => round($summary['engagement_rate'], 1),
        'icon' => '⚡',
        'description' => $this->t('Events per active user (30d)'),
        'status' => 'neutral',
      ],
      [
        'title' => $this->t('At Risk Users'),
        'value' => $summary['at_risk_percentage'] . '%',
        'icon' => '⚠️',
        'description' => $this->t('Users declining or inactive'),
        'status' => $summary['at_risk_percentage'] <= 15 ? 'good' : ($summary['at_risk_percentage'] <= 25 ? 'warning' : 'danger'),
      ],
      [
        'title' => $this->t('Champions'),
        'value' => $summary['champion_percentage'] . '%',
        'icon' => '🏆',
        'description' => $this->t('Most engaged power users'),
        'status' => $summary['champion_percentage'] >= 10 ? 'good' : ($summary['champion_percentage'] >= 5 ? 'warning' : 'neutral'),
      ],
    ];

    // Segment health data.
    $segmentDistribution = $this->reporter->getSegmentDistribution();
    $segments = [];
    $total = array_sum($segmentDistribution);

    foreach ($segmentDistribution as $key => $count) {
      $def = $this->segmenter->getSegmentDefinition($key);
      $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;

      $segments[$key] = [
        'label' => $def['label'],
        'icon' => $def['icon'],
        'color' => $def['color'],
        'count' => $count,
        'percentage' => $percentage,
      ];
    }

    // Activity trends.
    $trends = $this->reporter->getActivityTrends(30);

    // Most viewed content.
    $topContent = $this->reporter->getMostViewedContent(5, 30);

    return [
      '#theme' => 'oi_engagement_executive_summary',
      '#kpis' => $kpis,
      '#segments' => $segments,
      '#trends' => $trends,
      '#top_content' => $topContent,
      '#summary' => $summary,
      '#attached' => [
        'library' => ['openintranet_engagement/dashboard'],
      ],
    ];
  }

}
