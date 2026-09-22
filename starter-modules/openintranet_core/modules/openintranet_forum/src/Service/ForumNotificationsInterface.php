<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

/**
 * Interface for forum notifications service.
 */
interface ForumNotificationsInterface {

  /**
   * Notifies followers of a forum post about a new event.
   *
   * @param int $nid
   *   The node ID of the forum post.
   * @param string $event
   *   The event type (e.g., 'new_reply').
   */
  public function notifyFollowers(int $nid, string $event): void;

}
