<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_engagement\Service\OiEngagementReporterInterface;
use Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for top contributors reports.
 */
final class ContributorsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the ContributorsController.
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
   * Displays the top contributors report.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   The render array.
   */
  public function listing(Request $request): array {
    $days = (int) $request->query->get('days', 30);

    // Top users by total score.
    $topUsers = $this->reporter->getTopUsers(10);

    // Enrich with segment info.
    foreach ($topUsers as $key => $user) {
      $topUsers[$key]['segment_info'] = $this->segmenter->getSegmentDefinition($user['segment']);
    }

    // Get champions (most engaged).
    $champions = $this->reporter->getUsersBySegment('champion', 0, 10, 'total_score', 'DESC');

    // Get new users with high engagement (rising stars).
    $newUsers = $this->reporter->getUsersBySegment('new', 0, 10, 'total_score', 'DESC');

    return [
      '#theme' => 'oi_engagement_top_contributors',
      '#top_users' => $topUsers,
      '#champions' => $champions['users'] ?? [],
      '#rising_stars' => $newUsers['users'] ?? [],
      '#days' => $days,
      '#attached' => [
        'library' => ['openintranet_engagement/dashboard'],
      ],
    ];
  }

}
