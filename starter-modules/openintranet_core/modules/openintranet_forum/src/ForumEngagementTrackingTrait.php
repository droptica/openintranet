<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum;

use Drupal\Core\Session\AccountInterface;

/**
 * Records forum engagement events when the engagement module is available.
 *
 * The using class must provide a nullable $engagementTracker (the optional
 * openintranet_engagement.tracker service) and a $moduleHandler property.
 */
trait ForumEngagementTrackingTrait {

  /**
   * Tracks an engagement event if the engagement module is installed.
   *
   * @param string $event
   *   The engagement event type.
   * @param \Drupal\Core\Session\AccountInterface|null $actor
   *   The user the event is attributed to, or NULL.
   * @param array $metadata
   *   The event metadata (value, entity_type, entity_id).
   */
  protected function trackEngagement(string $event, ?AccountInterface $actor, array $metadata): void {
    if ($this->engagementTracker !== NULL && $this->moduleHandler->moduleExists('openintranet_engagement')) {
      $this->engagementTracker->track($event, $actor, $metadata);
    }
  }

}
