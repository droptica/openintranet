<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

/**
 * Interface for forum statistics service.
 */
interface ForumStatisticsInterface {

  /**
   * Gets the reply count for a forum post.
   *
   * @param int $nid
   *   The node ID of the forum post.
   *
   * @return int
   *   The reply count.
   */
  public function getReplyCount(int $nid): int;

  /**
   * Refreshes the denormalized reply count field on a forum post.
   *
   * Core's comment module already tracks the newest comment timestamp on the
   * comment field (last_comment_timestamp on comment_forum), so this method
   * only keeps the custom field_forum_reply_count in sync.
   *
   * @param int $nid
   *   The node ID of the forum post.
   */
  public function updateReplyCount(int $nid): void;

  /**
   * Gets the per-direction reaction counts for a forum comment.
   *
   * @param int $commentId
   *   The comment entity ID.
   *
   * @return array{up:int,down:int}
   *   Like and dislike totals keyed by direction.
   */
  public function getCommentReactionCounts(int $commentId): array;

  /**
   * Gets the direction of the current user's vote on a comment, or NULL.
   *
   * @param int $commentId
   *   The comment entity ID.
   *
   * @return string|null
   *   'up', 'down', or NULL if the current user has not voted.
   */
  public function getCommentUserVote(int $commentId): ?string;

  /**
   * Gets the sum of votingapi_result vote_sum values for a node.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return int
   *   The aggregated vote_sum total (0 when unavailable).
   */
  public function getVoteSumTotal(int $nid): int;

  /**
   * Gets the share count for a forum post from the dedicated field table.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return int
   *   The stored share count (0 when missing).
   */
  public function getShareCount(int $nid): int;

  /**
   * Gets the number of reaction_like votes for a forum post node.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return int
   *   The count of reaction_like votes.
   */
  public function getReactionLikeCount(int $nid): int;

  /**
   * Checks whether the given user has an active 'like' reaction on a node.
   *
   * @param int $nid
   *   The node ID.
   * @param int $uid
   *   The user ID.
   *
   * @return bool
   *   TRUE when a reaction_like vote exists for the user on the node.
   */
  public function hasUserLiked(int $nid, int $uid): bool;

  /**
   * Loads vote_sum totals for multiple nodes in a single query.
   *
   * @param int[] $nodeIds
   *   The node IDs to aggregate.
   *
   * @return array<int, int>
   *   Totals keyed by node ID. Missing nodes are absent from the result.
   */
  public function loadVoteSumTotals(array $nodeIds): array;

  /**
   * Returns display names of users who authored published forum posts.
   *
   * @return array<int, string>
   *   Select-option pairs keyed by user ID, valued by display name, sorted
   *   naturally by name (case-insensitive).
   */
  public function getForumPostAuthorOptions(): array;

}
