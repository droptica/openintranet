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
 * Controller for department engagement analysis.
 */
final class DepartmentController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the DepartmentController.
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
   * Displays department engagement analysis.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   Render array.
   */
  public function analysis(Request $request): array {
    $days = (int) $request->query->get('days', 30);

    $departments = $this->reporter->getEngagementByDepartment($days);
    $roles = $this->reporter->getEngagementByRole($days);
    $segments = $this->segmenter->getAllSegments();

    // Calculate health status for departments.
    foreach ($departments as &$dept) {
      $atRiskPercent = $dept['user_count'] > 0
        ? (($dept['at_risk_count'] + $dept['dormant_count']) / $dept['user_count']) * 100
        : 0;

      if ($atRiskPercent >= 30) {
        $dept['health'] = 'critical';
        $dept['health_label'] = $this->t('Action needed');
      }
      elseif ($atRiskPercent >= 15) {
        $dept['health'] = 'warning';
        $dept['health_label'] = $this->t('Monitor');
      }
      else {
        $dept['health'] = 'good';
        $dept['health_label'] = $this->t('Healthy');
      }
    }

    // Generate insights.
    $insights = [];
    if (!empty($departments)) {
      $bestDept = $departments[0];
      $worstDept = end($departments);

      if ($bestDept['avg_score'] > 0) {
        $insights[] = [
          'type' => 'success',
          'message' => $this->t('@dept has the highest engagement with an average score of @score.', [
            '@dept' => $bestDept['department'],
            '@score' => $bestDept['avg_score'],
          ]),
        ];
      }

      if ($worstDept['avg_score'] < 8 && $worstDept !== $bestDept) {
        $insights[] = [
          'type' => 'warning',
          'message' => $this->t('@dept may need engagement initiatives (avg score: @score).', [
            '@dept' => $worstDept['department'],
            '@score' => $worstDept['avg_score'],
          ]),
        ];
      }

      // Check for departments with high at-risk users.
      foreach ($departments as $dept) {
        if ($dept['health'] === 'critical') {
          $insights[] = [
            'type' => 'danger',
            'message' => $this->t('@dept has @count at-risk/dormant users requiring attention.', [
              '@dept' => $dept['department'],
              '@count' => $dept['at_risk_count'] + $dept['dormant_count'],
            ]),
          ];
        }
      }
    }

    $hasDepartments = !empty($departments);

    return [
      '#theme' => 'oi_engagement_department_analysis',
      '#departments' => $departments,
      '#roles' => $roles,
      '#segments' => $segments,
      '#insights' => $insights,
      '#days' => $days,
      '#has_departments' => $hasDepartments,
      '#attached' => [
        'library' => ['openintranet_engagement/dashboard'],
      ],
      '#cache' => [
        'max-age' => 300,
      ],
    ];
  }

}
