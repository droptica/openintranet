<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Tracks user events for engagement analysis.
 */
final class OiEngagementTracker implements OiEngagementTrackerInterface {

  /**
   * Constructs the OiEngagementTracker service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementConfigInterface $config
   *   The config service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
    private readonly OiEngagementConfigInterface $config,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function track(string $eventType, ?AccountInterface $account = NULL, array $context = []): void {
    $account = $account ?? $this->currentUser->getAccount();

    // Skip anonymous users (unless explicitly configured).
    if ($account->isAnonymous() && !$this->config->shouldTrackAnonymous()) {
      return;
    }

    // Skip if tracking globally disabled.
    if (!$this->config->isTrackingEnabled()) {
      return;
    }

    // Get value from context or calculate from config.
    $value = $context['value'] ?? $this->getEventValue($eventType);

    // Skip if value is 0 (disabled).
    if ($value === 0) {
      return;
    }

    $this->database->insert('oi_engagement_event')
      ->fields([
        'user_id' => (int) $account->id(),
        'event_type' => $eventType,
        'event_value' => $value,
        'entity_type' => $context['entity_type'] ?? NULL,
        'entity_id' => $context['entity_id'] ?? NULL,
        'context' => isset($context['data']) ? json_encode($context['data']) : NULL,
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();

    // Invalidate user's score cache (mark as stale).
    $this->invalidateUserScore((int) $account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function trackMultiple(array $events): void {
    foreach ($events as $event) {
      $this->track(
        $event['type'] ?? $event['event_type'],
        $event['account'] ?? NULL,
        $event['context'] ?? []
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isTrackingEnabled(): bool {
    return $this->config->isTrackingEnabled();
  }

  /**
   * Gets event value from config.
   *
   * For entity-based events (node_create, oi_document_view, etc.),
   * the value comes from entity_types config.
   * For special events (login, search), uses default values.
   *
   * @param string $eventType
   *   The event type.
   *
   * @return int
   *   The point value for this event.
   */
  private function getEventValue(string $eventType): int {
    // Check if it's an entity-based event (format: {entity_type}_{operation}).
    if (preg_match('/^(.+)_(create|view|update|delete)$/', $eventType, $matches)) {
      $entityType = $matches[1];
      $operation = $matches[2];
      return $this->config->getEntityTypeValue($entityType, $operation);
    }

    // Special events (login, search, etc.) - use default values.
    return match ($eventType) {
      'login' => 1,
      'logout' => 0,
      'search' => 1,
      'file_download' => 3,
      default => 1,
    };
  }

  /**
   * Invalidates user's score cache.
   *
   * Marks the score as stale so it will be recalculated on next read or cron.
   *
   * @param int $userId
   *   The user ID.
   */
  private function invalidateUserScore(int $userId): void {
    $this->database->merge('oi_engagement_score')
      ->keys(['user_id' => $userId])
      ->fields(['calculated' => 0])
      ->execute();
  }

}
