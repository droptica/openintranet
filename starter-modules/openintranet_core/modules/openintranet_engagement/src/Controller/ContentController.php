<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_engagement\Service\OiEngagementReporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for content performance reports.
 */
final class ContentController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the ContentController.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementReporterInterface $reporter
   *   The reporter service.
   */
  public function __construct(
    private readonly OiEngagementReporterInterface $reporter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('openintranet_engagement.reporter'),
    );
  }

  /**
   * Displays the content performance report.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   The render array.
   */
  public function performance(Request $request): array {
    $days = (int) $request->query->get('days', 30);

    // Most viewed content.
    $mostViewed = $this->reporter->getMostViewedContent(20, $days);

    // Event breakdown by type.
    $eventBreakdown = $this->reporter->getEventTypeBreakdown($days);

    // Filter to content-related events.
    $contentEvents = array_filter($eventBreakdown, function ($event) {
      return str_contains($event['event_type'], '_view') ||
             str_contains($event['event_type'], '_create') ||
             str_contains($event['event_type'], '_update');
    });

    return [
      '#theme' => 'oi_engagement_content_performance',
      '#most_viewed' => $mostViewed,
      '#event_breakdown' => $contentEvents,
      '#days' => $days,
      '#attached' => [
        'library' => ['openintranet_engagement/dashboard'],
      ],
    ];
  }

}
