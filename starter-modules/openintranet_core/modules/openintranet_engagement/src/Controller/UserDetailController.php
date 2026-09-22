<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_engagement\Service\OiEngagementCalculatorInterface;
use Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for user detail page.
 */
final class UserDetailController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the UserDetailController.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementCalculatorInterface $calculator
   *   The calculator service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface $segmenter
   *   The segmenter service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly OiEngagementCalculatorInterface $calculator,
    private readonly OiEngagementSegmenterInterface $segmenter,
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('openintranet_engagement.calculator'),
      $container->get('openintranet_engagement.segmenter'),
      $container->get('database'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Returns the page title.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return string
   *   The page title.
   */
  public function title(UserInterface $user): string {
    return $user->getDisplayName() . ' - ' . $this->t('Engagement Profile');
  }

  /**
   * Displays the user detail page.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return array
   *   The render array.
   */
  public function detail(UserInterface $user): array {
    $userId = (int) $user->id();
    $score = $this->calculator->getScore($userId);

    if (!$score) {
      // Calculate if not exists.
      $score = $this->calculator->calculateForUser($userId);
    }

    $segmentDef = $this->segmenter->getSegmentDefinition($score['segment']);

    // Get event breakdown.
    $breakdown = $this->getEventBreakdown($userId);

    // Get recent events.
    $recentEvents = $this->getRecentEvents($userId, 10);

    return [
      '#theme' => 'oi_engagement_user_detail',
      '#user' => $user,
      '#score' => $score,
      '#segment' => $segmentDef,
      '#events' => $recentEvents,
      '#breakdown' => $breakdown,
      '#attached' => [
        'library' => ['openintranet_engagement/dashboard'],
      ],
    ];
  }

  /**
   * Gets event breakdown for a user.
   *
   * @param int $userId
   *   The user ID.
   *
   * @return array
   *   Event breakdown data.
   */
  private function getEventBreakdown(int $userId): array {
    $thirtyDaysAgo = $this->time->getRequestTime() - (30 * 86400);

    $query = $this->database->select('oi_engagement_event', 'e')
      ->fields('e', ['event_type'])
      ->condition('e.user_id', $userId)
      ->condition('e.created', $thirtyDaysAgo, '>=');
    $query->addExpression('COUNT(*)', 'count');
    $query->addExpression('SUM(e.event_value)', 'value');
    $query->groupBy('e.event_type');
    $query->orderBy('count', 'DESC');

    $results = $query->execute()->fetchAll();

    $total = 0;
    foreach ($results as $row) {
      $total += (int) $row->count;
    }

    $breakdown = [];
    foreach ($results as $row) {
      $breakdown[] = [
        'event_type' => $row->event_type,
        'count' => (int) $row->count,
        'value' => (int) $row->value,
        'percentage' => $total > 0 ? round(((int) $row->count / $total) * 100, 1) : 0,
      ];
    }

    return $breakdown;
  }

  /**
   * Gets recent events for a user.
   *
   * @param int $userId
   *   The user ID.
   * @param int $limit
   *   Maximum number of events.
   *
   * @return array
   *   Recent events.
   */
  private function getRecentEvents(int $userId, int $limit = 10): array {
    $query = $this->database->select('oi_engagement_event', 'e')
      ->fields('e')
      ->condition('e.user_id', $userId)
      ->orderBy('e.created', 'DESC')
      ->range(0, $limit);

    $results = $query->execute()->fetchAll();

    $events = [];
    foreach ($results as $row) {
      $events[] = [
        'event_type' => $row->event_type,
        'value' => (int) $row->event_value,
        'entity_type' => $row->entity_type,
        'entity_id' => $row->entity_id,
        'created' => (int) $row->created,
        'context' => $row->context ? json_decode($row->context, TRUE) : [],
      ];
    }

    return $events;
  }

}
