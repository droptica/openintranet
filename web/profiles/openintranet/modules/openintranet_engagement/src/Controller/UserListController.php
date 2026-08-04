<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\openintranet_engagement\Service\OiEngagementReporterInterface;
use Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the user list page.
 */
final class UserListController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the UserListController.
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
   * Displays the user list.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   The render array.
   */
  public function listing(Request $request): array {
    $segment = $request->query->get('segment', '');
    $page = (int) $request->query->get('page', 0);
    $sortBy = $request->query->get('sort', 'total_score');
    $sortDir = $request->query->get('dir', 'DESC');

    // Build segment filter options.
    $segments = $this->segmenter->getAllSegments();
    $segmentOptions = ['' => $this->t('All Segments')];
    foreach ($segments as $key => $def) {
      $segmentOptions[$key] = $def['icon'] . ' ' . $def['label'];
    }

    // Build filter form.
    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['engagement-filters']],
    ];

    $form['filters']['segment'] = [
      '#type' => 'select',
      '#title' => $this->t('Segment'),
      '#options' => $segmentOptions,
      '#default_value' => $segment,
      '#attributes' => ['onchange' => 'this.form.submit()'],
    ];

    // Get users based on filter.
    if ($segment) {
      $result = $this->reporter->getUsersBySegment($segment, $page, 50, $sortBy, $sortDir);
    }
    else {
      // Get all users - use top users method with high limit.
      $result = [
        'users' => $this->reporter->getTopUsers(1000),
        'total' => count($this->reporter->getTopUsers(10000)),
        'page' => $page,
        'per_page' => 50,
        'pages' => 1,
      ];
    }

    // Build table.
    $header = [
      ['data' => $this->t('User'), 'field' => 'name'],
      ['data' => $this->t('Segment')],
      ['data' => $this->t('R'), 'field' => 'recency_score'],
      ['data' => $this->t('F'), 'field' => 'frequency_score'],
      ['data' => $this->t('V'), 'field' => 'value_score'],
      ['data' => $this->t('Total'), 'field' => 'total_score'],
      ['data' => $this->t('Last Active'), 'field' => 'last_activity'],
      ['data' => $this->t('Actions')],
    ];

    $rows = [];
    foreach ($result['users'] as $user) {
      $segmentDef = $this->segmenter->getSegmentDefinition($user['segment']);

      $rows[] = [
        $user['name'],
        [
          'data' => $segmentDef['icon'] . ' ' . $segmentDef['label'],
          'style' => 'color: ' . $segmentDef['color'],
        ],
        $user['recency_score'],
        $user['frequency_score'],
        $user['value_score'],
        $user['total_score'],
        $user['last_activity'] ? date('Y-m-d H:i', (int) $user['last_activity']) : '-',
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('View'),
            '#url' => Url::fromRoute('openintranet_engagement.user_detail', ['user' => $user['user_id']]),
          ],
        ],
      ];
    }

    $build = [];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No users found.'),
      '#attributes' => ['class' => ['engagement-user-list']],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
