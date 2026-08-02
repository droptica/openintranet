<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\openintranet_forum\ForumEngagementTrackingTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for forum reactions.
 */
final class ForumReactionSubscriber implements EventSubscriberInterface {

  use ForumEngagementTrackingTrait;

  /**
   * Constructs a ForumReactionSubscriber object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param object|null $engagementTracker
   *   An OiEngagementTrackerInterface instance, or NULL when the
   *   openintranet_engagement module is not installed. Typed as object so the
   *   container can inject via @?openintranet_engagement.tracker without
   *   requiring the contrib module to be present at compile time.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ?object $engagementTracker = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];

    if (class_exists('Drupal\votingapi\Event\VoteEvent')) {
      $events['votingapi.vote'] = 'onVote';
    }

    return $events;
  }

  /**
   * Responds to vote events.
   *
   * @param object $event
   *   The vote event.
   */
  public function onVote($event): void {
    $vote = $event->getVote();
    $entity = $vote->getVotedEntity();

    $is_forum_post = $entity->getEntityTypeId() === 'node' && $entity->bundle() === 'forum_post';
    $is_forum_reply = $entity->getEntityTypeId() === 'comment' && $entity->bundle() === 'forum_reply';

    if ($is_forum_post || $is_forum_reply) {
      $this->trackEngagement('forum_reaction_add', $vote->getOwner(), [
        'value' => 2,
        'entity_type' => 'vote',
        'entity_id' => $vote->id(),
      ]);
    }
  }

}
