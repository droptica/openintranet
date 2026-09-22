<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

use Drupal\Core\Session\AccountInterface;

/**
 * Interface for engagement event tracking service.
 */
interface OiEngagementTrackerInterface {

  /**
   * Tracks an event for a user.
   *
   * @param string $eventType
   *   The event type ID (e.g., 'node_create', 'login').
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account. Defaults to current user.
   * @param array $context
   *   Optional context data:
   *   - entity_type: Related entity type.
   *   - entity_id: Related entity ID.
   *   - value: Override default value.
   *   - data: Additional JSON data.
   */
  public function track(string $eventType, ?AccountInterface $account = NULL, array $context = []): void;

  /**
   * Tracks multiple events at once.
   *
   * @param array $events
   *   Array of events, each with 'type', 'account', 'context'.
   */
  public function trackMultiple(array $events): void;

  /**
   * Checks if tracking is enabled.
   *
   * @return bool
   *   TRUE if tracking is enabled.
   */
  public function isTrackingEnabled(): bool;

}
