<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\openintranet_engagement\Service\OiEngagementCalculatorInterface;
use Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface;

/**
 * User-related hooks for engagement tracking.
 */
final class UserHooks {

  /**
   * Constructs the UserHooks class.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementCalculatorInterface $calculator
   *   The calculator service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface $segmenter
   *   The segmenter service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly OiEngagementCalculatorInterface $calculator,
    private readonly OiEngagementSegmenterInterface $segmenter,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Cleans up user's engagement data when account is cancelled.
   *
   * @param array $edit
   *   The form edit array.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account being cancelled.
   * @param string $method
   *   The cancellation method.
   */
  #[Hook('user_cancel')]
  public function onUserCancel(array $edit, AccountInterface $account, string $method): void {
    $userId = (int) $account->id();

    // Delete user's events.
    $this->database->delete('oi_engagement_event')
      ->condition('user_id', $userId)
      ->execute();

    // Delete user's score.
    $this->database->delete('oi_engagement_score')
      ->condition('user_id', $userId)
      ->execute();

    // Delete user's daily stats.
    $this->database->delete('oi_engagement_daily_stats')
      ->condition('user_id', $userId)
      ->execute();
  }

  /**
   * Adds engagement badge to user profile view.
   *
   * @param array &$build
   *   The render array.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The user entity.
   * @param mixed $display
   *   The display.
   * @param string $view_mode
   *   The view mode.
   */
  #[Hook('user_view')]
  public function onUserView(array &$build, EntityInterface $entity, $display, $view_mode): void {
    // Only show badge on full user profile pages, not in compact views.
    if ($view_mode !== 'full') {
      return;
    }

    // Only show for users with permission.
    if (!$this->currentUser->hasPermission('view engagement scores') &&
        !($this->currentUser->hasPermission('view own engagement score') &&
          $this->currentUser->id() == $entity->id())) {
      return;
    }

    $score = $this->calculator->getScore((int) $entity->id());

    if ($score) {
      $segmentDef = $this->segmenter->getSegmentDefinition($score['segment']);

      $build['engagement_score'] = [
        '#theme' => 'oi_engagement_user_badge',
        '#score' => $score,
        '#segment' => $segmentDef,
        '#weight' => 100,
      ];
    }
  }

}
