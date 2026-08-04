<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Controller;

use Drupal\comment\CommentInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\Service\ForumStatisticsInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles like/dislike AJAX vote requests for forum comments.
 */
final class ForumCommentVoteController extends ControllerBase {

  use AutowireTrait;

  public function __construct(
    private readonly ForumStatisticsInterface $statistics,
  ) {}

  /**
   * Toggles a like or dislike vote on a forum comment.
   *
   * Accepts a POST request with a JSON body: {"direction": "up"} or
   * {"direction": "down"}. Protected by the X-CSRF-Token header (route
   * requirement _csrf_request_header_token).
   *
   * Response JSON:
   *   likes    int   Current like count.
   *   dislikes int   Current dislike count.
   *   voted    string|null  'up', 'down', or null for the current user.
   */
  public function vote(CommentInterface $comment, Request $request): JsonResponse {
    if ($comment->bundle() !== 'forum_reply') {
      return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
    }

    $commented_entity = $comment->getCommentedEntity();
    if (!$commented_entity instanceof NodeInterface || $commented_entity->bundle() !== 'forum_post') {
      return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
    }

    $account = $this->currentUser();
    if ($account->isAnonymous()) {
      return new JsonResponse(['error' => 'Login required'], Response::HTTP_FORBIDDEN);
    }

    $data = json_decode($request->getContent(), TRUE);
    $direction = $data['direction'] ?? $request->request->get('direction');

    if ($direction !== 'up' && $direction !== 'down') {
      return new JsonResponse(['error' => 'Invalid direction'], Response::HTTP_BAD_REQUEST);
    }

    $value = $direction === 'up' ? 1 : -1;
    $vote_storage = $this->entityTypeManager()->getStorage('vote');
    $uid = (int) $account->id();
    $entity_id = (int) $comment->id();

    $existing_ids = $vote_storage->getUserVotes($uid, 'vote', 'comment', $entity_id);
    $new_voted = NULL;

    if (!empty($existing_ids)) {
      /** @var \Drupal\votingapi\Entity\Vote $existing_vote */
      $existing_vote = $vote_storage->load(reset($existing_ids));
      $existing_value = (int) ($existing_vote->get('value')->value ?? 0);
      $existing_direction = $existing_value > 0 ? 'up' : 'down';

      $vote_storage->delete([$existing_vote]);

      if ($existing_direction !== $direction) {
        $this->createCommentVote($entity_id, $value, $uid);
        $new_voted = $direction;
      }
    }
    else {
      $this->createCommentVote($entity_id, $value, $uid);
      $new_voted = $direction;
    }

    $counts = $this->statistics->getCommentReactionCounts($entity_id);

    return new JsonResponse([
      'likes' => $counts['up'],
      'dislikes' => $counts['down'],
      'voted' => $new_voted,
    ]);
  }

  /**
   * Creates and saves a vote on a comment.
   *
   * @param int $entityId
   *   The comment id.
   * @param int $value
   *   The vote value (1 or -1).
   * @param int $uid
   *   The voting user id.
   */
  private function createCommentVote(int $entityId, int $value, int $uid): void {
    $vote = $this->entityTypeManager()->getStorage('vote')->create([
      'type' => 'vote',
      'entity_type' => 'comment',
      'entity_id' => $entityId,
      'value' => $value,
      'user_id' => $uid,
    ]);
    $vote->save();
  }

}
