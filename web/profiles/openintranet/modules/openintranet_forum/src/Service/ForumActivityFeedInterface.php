<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

/**
 * Builds the personalized forum activity feeds.
 *
 * Two feeds are exposed:
 * - "Recent activity on your posts" (replies/reactions made by others on the
 *   current user's forum posts).
 * - "Your activity on others" (replies/reactions the given user made on
 *   other people's forum posts).
 */
interface ForumActivityFeedInterface {

  /**
   * Builds the "recent activity on your posts" feed for the current user.
   *
   * @param int $limit
   *   Maximum number of items to render.
   * @param string $variant
   *   Template variant ('sidebar' or 'page').
   * @param bool $showMore
   *   Whether to render a "show more" link when additional items exist.
   *
   * @return array
   *   A render array, or an empty array for anonymous users.
   */
  public function buildCurrentUserFeed(int $limit, string $variant = 'sidebar', bool $showMore = FALSE): array;

  /**
   * Builds the "your activity on others" feed for a given user.
   *
   * @param int $uid
   *   The user id whose activity should be loaded.
   * @param int $limit
   *   Maximum number of items to render.
   * @param string $variant
   *   Template variant ('sidebar' or 'page').
   * @param bool $showMore
   *   Whether to render a "show more" link when additional items exist.
   *
   * @return array
   *   A render array, or an empty array for invalid user ids.
   */
  public function buildUserActivityFeed(int $uid, int $limit, string $variant = 'sidebar', bool $showMore = FALSE): array;

}
