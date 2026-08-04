<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Controller;

use Drupal\comment\CommentInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\Controller\ForumCommentVoteController;
use Drupal\openintranet_forum\Service\ForumStatisticsInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for forum comment voting.
 */
#[CoversClass(ForumCommentVoteController::class)]
final class ForumCommentVoteControllerTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Hides soft-deleted replies.
   */
  #[Test]
  public function voteRejectsSoftDeletedReply(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('forum_post');

    $softDeleted = $this->createMock(FieldItemListInterface::class);
    $softDeleted->method('getValue')->willReturn([['value' => 1]]);

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('forum_reply');
    $comment->method('getCommentedEntity')->willReturn($node);
    $comment->method('hasField')
      ->with('field_forum_reply_soft_deleted')
      ->willReturn(TRUE);
    $comment->method('get')
      ->with('field_forum_reply_soft_deleted')
      ->willReturn($softDeleted);

    $statistics = $this->createMock(ForumStatisticsInterface::class);
    $statistics->expects(self::never())->method('getCommentReactionCounts');

    $response = (new ForumCommentVoteController($statistics))
      ->vote($comment, Request::create('/', 'POST'));

    self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }

  /**
   * Rejects malformed JSON before attempting vote persistence.
   */
  #[Test]
  public function voteRejectsMalformedJson(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('forum_post');

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('forum_reply');
    $comment->method('getCommentedEntity')->willReturn($node);
    $comment->method('hasField')
      ->with('field_forum_reply_soft_deleted')
      ->willReturn(FALSE);

    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    \Drupal::setContainer($container);

    $statistics = $this->createMock(ForumStatisticsInterface::class);
    $statistics->expects(self::never())->method('getCommentReactionCounts');

    $request = Request::create('/', 'POST', [], [], [], [], '{');
    $response = (new ForumCommentVoteController($statistics))
      ->vote($comment, $request);

    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    self::assertSame(
      ['error' => 'Invalid JSON'],
      json_decode($response->getContent(), TRUE),
    );
  }

}
