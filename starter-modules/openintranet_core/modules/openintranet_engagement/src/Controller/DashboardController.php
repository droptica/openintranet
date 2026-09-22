<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_engagement\Service\OiEngagementReporterInterface;
use Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for the engagement dashboard.
 */
final class DashboardController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the DashboardController.
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
   * Displays the main dashboard.
   *
   * @return array
   *   The render array.
   */
  public function dashboard(): array {
    $segmentDistribution = $this->reporter->getSegmentDistribution();
    $segments = [];
    $total = 0;

    foreach ($segmentDistribution as $segmentKey => $count) {
      $definition = $this->segmenter->getSegmentDefinition($segmentKey);
      $segments[$segmentKey] = [
        'name' => $segmentKey,
        'label' => $definition['label'],
        'icon' => $definition['icon'],
        'color' => $definition['color'],
        'count' => $count,
      ];
      $total += $count;
    }

    // Calculate percentages.
    foreach ($segments as $key => $segment) {
      $segments[$key]['percentage'] = $total > 0
        ? round(($segment['count'] / $total) * 100, 1)
        : 0;
    }

    $trends = $this->reporter->getActivityTrends(30);
    $topEvents = $this->reporter->getEventTypeBreakdown(30);
    $summary = $this->reporter->getExecutiveSummary();

    return [
      '#theme' => 'oi_engagement_dashboard',
      '#segments' => $segments,
      '#trends' => $trends,
      '#top_events' => array_slice($topEvents, 0, 5),
      '#summary' => $summary,
      '#attached' => [
        'library' => ['openintranet_engagement/dashboard'],
      ],
    ];
  }

  /**
   * Returns chart data as JSON.
   *
   * @param string $type
   *   The chart type: segments, trends, events.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with chart data.
   */
  public function chartData(string $type): JsonResponse {
    $data = match ($type) {
      'segments' => $this->reporter->getSegmentDistribution(),
      'trends' => $this->reporter->getActivityTrends(30),
      'events' => $this->reporter->getEventTypeBreakdown(30),
      default => [],
    };

    return new JsonResponse($data);
  }

}
