<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\Service\ForumStatistics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for forum statistics storage.
 */
#[CoversClass(ForumStatistics::class)]
final class ForumStatisticsTest extends TestCase {

  /**
   * Increments a share count through the entity API under a lock.
   */
  #[Test]
  public function incrementShareCountUsesLockAndEntityApi(): void {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['value' => 4]]);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('forum_post');
    $node->method('hasField')
      ->with('field_forum_share_count')
      ->willReturn(TRUE);
    $node->method('get')
      ->with('field_forum_share_count')
      ->willReturn($field);
    $node->method('getChangedTime')->willReturn(1700000000);
    $node->expects(self::once())
      ->method('set')
      ->with('field_forum_share_count', 5)
      ->willReturnSelf();
    $node->expects(self::once())
      ->method('setNewRevision')
      ->with(FALSE)
      ->willReturnSelf();
    $node->expects(self::once())
      ->method('setChangedTime')
      ->with(1700000000)
      ->willReturnSelf();
    $node->expects(self::once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadUnchanged')->with(12)->willReturn($node);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())
      ->method('acquire')
      ->with('openintranet_forum.share_count.12', 5.0)
      ->willReturn(TRUE);
    $lock->expects(self::once())
      ->method('release')
      ->with('openintranet_forum.share_count.12');

    $statistics = new ForumStatistics(
      $entityTypeManager,
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $lock,
    );

    self::assertSame(5, $statistics->incrementShareCount(12));
  }

}
