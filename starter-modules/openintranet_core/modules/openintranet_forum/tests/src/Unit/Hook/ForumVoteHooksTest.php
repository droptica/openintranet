<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_forum\Hook\ForumVoteHooks;
use Drupal\votingapi\VoteInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the forum vote hooks.
 */
#[CoversClass(ForumVoteHooks::class)]
final class ForumVoteHooksTest extends TestCase {

  /**
   * Provides forum vote target entity types and bundles.
   *
   * @return array<string, array{string, string}>
   *   Test cases.
   */
  public static function forumTargets(): array {
    return [
      'forum post' => ['node', 'forum_post'],
      'forum reply' => ['comment', 'forum_reply'],
    ];
  }

  /**
   * Tracks inserted votes on forum posts and replies.
   */
  #[Test]
  #[DataProvider('forumTargets')]
  public function voteInsertTracksForumTargets(string $entityTypeId, string $bundle): void {
    $target = $this->createMock(EntityInterface::class);
    $target->method('getEntityTypeId')->willReturn($entityTypeId);
    $target->method('bundle')->willReturn($bundle);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(42)->willReturn($target);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')
      ->with($entityTypeId, FALSE)
      ->willReturn($this->createMock(EntityTypeInterface::class));
    $entityTypeManager->method('getStorage')
      ->with($entityTypeId)
      ->willReturn($storage);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')
      ->with('openintranet_engagement')
      ->willReturn(TRUE);

    $tracker = new class() {
      /**
       * Tracked calls.
       *
       * @var array<int, array{string, mixed, array<string, mixed>}>
       */
      public array $calls = [];

      /**
       * Records a tracking call.
       */
      public function track(string $event, mixed $actor, array $metadata): void {
        $this->calls[] = [$event, $actor, $metadata];
      }

    };

    $owner = $this->createMock(AccountInterface::class);
    $vote = $this->createMock(VoteInterface::class);
    $vote->method('getVotedEntityType')->willReturn($entityTypeId);
    $vote->method('getVotedEntityId')->willReturn(42);
    $vote->method('getOwner')->willReturn($owner);
    $vote->method('id')->willReturn(7);

    (new ForumVoteHooks($entityTypeManager, $moduleHandler, $tracker))
      ->voteInsert($vote);

    self::assertSame(
      [
        [
          'forum_reaction_add',
          $owner,
          [
            'value' => 2,
            'entity_type' => 'vote',
            'entity_id' => 7,
          ],
        ],
      ],
      $tracker->calls,
    );
  }

  /**
   * Ignores votes whose target no longer exists.
   */
  #[Test]
  public function voteInsertIgnoresMissingTarget(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(42)->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')
      ->with('node', FALSE)
      ->willReturn($this->createMock(EntityTypeInterface::class));
    $entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects(self::never())->method('moduleExists');

    $vote = $this->createMock(VoteInterface::class);
    $vote->method('getVotedEntityType')->willReturn('node');
    $vote->method('getVotedEntityId')->willReturn(42);

    $tracker = new \stdClass();
    (new ForumVoteHooks($entityTypeManager, $moduleHandler, $tracker))
      ->voteInsert($vote);

    self::assertTrue(TRUE);
  }

  /**
   * Does not require the optional engagement tracker.
   */
  #[Test]
  public function voteInsertWithoutEngagementTrackerIsSafe(): void {
    $target = $this->createMock(EntityInterface::class);
    $target->method('getEntityTypeId')->willReturn('node');
    $target->method('bundle')->willReturn('forum_post');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($target);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')
      ->willReturn($this->createMock(EntityTypeInterface::class));
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects(self::never())->method('moduleExists');

    $vote = $this->createMock(VoteInterface::class);
    $vote->method('getVotedEntityType')->willReturn('node');
    $vote->method('getVotedEntityId')->willReturn(42);

    (new ForumVoteHooks($entityTypeManager, $moduleHandler))->voteInsert($vote);

    self::assertTrue(TRUE);
  }

}
