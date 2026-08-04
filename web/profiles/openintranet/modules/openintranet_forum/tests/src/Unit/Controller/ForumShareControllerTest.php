<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Controller;

use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\Controller\ForumShareController;
use Drupal\openintranet_forum\Service\ForumStatisticsInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for forum post sharing.
 */
#[CoversClass(ForumShareController::class)]
final class ForumShareControllerTest extends TestCase {

  /**
   * Returns the updated count from the statistics service.
   */
  #[Test]
  public function shareIncrementsPublishedForumPost(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('forum_post');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('id')->willReturn(12);

    $statistics = $this->createMock(ForumStatisticsInterface::class);
    $statistics->expects(self::once())
      ->method('incrementShareCount')
      ->with(12)
      ->willReturn(5);

    $response = (new ForumShareController($statistics))->share($node);

    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    self::assertSame(['count' => 5], json_decode($response->getContent(), TRUE));
  }

  /**
   * Hides unpublished forum posts.
   */
  #[Test]
  public function shareRejectsUnpublishedForumPost(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('forum_post');
    $node->method('isPublished')->willReturn(FALSE);

    $statistics = $this->createMock(ForumStatisticsInterface::class);
    $statistics->expects(self::never())->method('incrementShareCount');

    $response = (new ForumShareController($statistics))->share($node);

    self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }

}
