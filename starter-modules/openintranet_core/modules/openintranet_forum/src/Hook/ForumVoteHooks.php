<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\openintranet_forum\ForumEngagementTrackingTrait;
use Drupal\votingapi\VoteInterface;

/**
 * Vote entity hook implementations for the forum module.
 */
final class ForumVoteHooks {

  use ForumEngagementTrackingTrait;

  /**
   * Constructs a ForumVoteHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param object|null $engagementTracker
   *   An OiEngagementTrackerInterface instance, or NULL when the
   *   openintranet_engagement module is not installed.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ?object $engagementTracker = NULL,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for votes.
   */
  #[Hook('vote_insert')]
  public function voteInsert(VoteInterface $vote): void {
    $entity_type_id = $vote->getVotedEntityType();
    if ($this->entityTypeManager->getDefinition($entity_type_id, FALSE) === NULL) {
      return;
    }

    $entity = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->load($vote->getVotedEntityId());
    if ($entity === NULL) {
      return;
    }

    $is_forum_post = $entity->getEntityTypeId() === 'node' && $entity->bundle() === 'forum_post';
    $is_forum_reply = $entity->getEntityTypeId() === 'comment' && $entity->bundle() === 'forum_reply';
    if (!$is_forum_post && !$is_forum_reply) {
      return;
    }

    $this->trackEngagement('forum_reaction_add', $vote->getOwner(), [
      'value' => 2,
      'entity_type' => 'vote',
      'entity_id' => $vote->id(),
    ]);
  }

}
