<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\openintranet_forum\Service\ForumTrendingScorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ForumTrendingScorer.
 *
 * @covers \Drupal\openintranet_forum\Service\ForumTrendingScorer
 */
#[CoversClass(ForumTrendingScorer::class)]
final class ForumTrendingScorerTest extends TestCase {

  /**
   * Builds a config factory mock returning the given trending_days value.
   */
  private function mockConfigFactory(?int $trendingDays): ConfigFactoryInterface {
    $config = $this->createMock(Config::class);
    $config->method('get')->with('trending_days')->willReturn($trendingDays);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('openintranet_forum.settings')
      ->willReturn($config);

    return $configFactory;
  }

  /**
   * Builds score SQL interpolating table aliases and the request time.
   */
  #[Test]
  public function buildScoreSqlInterpolatesAliasesAndRequestTime(): void {
    $database = $this->createMock(Connection::class);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $scorer = new ForumTrendingScorer($database, $time, $this->mockConfigFactory(7));

    $sql = $scorer->buildScoreSql('n', 'nfv');

    self::assertStringContainsString('SUM(vr.value)', $sql);
    self::assertStringContainsString("function = 'vote_sum'", $sql);
    self::assertStringContainsString("entity_type = 'node'", $sql);

    self::assertStringContainsString('* 3 +', $sql);

    self::assertStringContainsString('GREATEST(0, 7 - FLOOR((', $sql);
    self::assertStringContainsString('1700000000', $sql);
    self::assertStringContainsString(') / 86400)) * 10', $sql);

    self::assertStringContainsString('n.nid', $sql);
    self::assertStringContainsString('nfv.field_forum_views_value', $sql);
  }

  /**
   * Builds score SQL matching the full expected template string.
   */
  #[Test]
  public function buildScoreSqlMatchesExpectedTemplate(): void {
    $database = $this->createMock(Connection::class);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $scorer = new ForumTrendingScorer($database, $time, $this->mockConfigFactory(7));

    $expected = "(COALESCE((SELECT SUM(vr.value) FROM {votingapi_result} vr WHERE vr.entity_id = n.nid AND vr.entity_type = 'node' AND vr.function = 'vote_sum'), 0) * 3 + COALESCE(nfv.field_forum_views_value, 0) + (GREATEST(0, 7 - FLOOR((1700000000 - n.created) / 86400)) * 10))";

    self::assertSame($expected, $scorer->buildScoreSql('n', 'nfv'));
  }

  /**
   * Uses the configured trending_days window in the recency expression.
   */
  #[Test]
  public function buildScoreSqlUsesConfiguredTrendingDays(): void {
    $database = $this->createMock(Connection::class);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $scorer = new ForumTrendingScorer($database, $time, $this->mockConfigFactory(30));

    self::assertStringContainsString('GREATEST(0, 30 - FLOOR((', $scorer->buildScoreSql('n', 'nfv'));
  }

  /**
   * Falls back to the default window when configuration is missing or invalid.
   */
  #[Test]
  public function buildScoreSqlFallsBackToDefaultDaysWhenUnset(): void {
    $database = $this->createMock(Connection::class);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $scorer = new ForumTrendingScorer($database, $time, $this->mockConfigFactory(NULL));

    self::assertStringContainsString('GREATEST(0, 7 - FLOOR((', $scorer->buildScoreSql('n', 'nfv'));
  }

  /**
   * Returns an empty result for empty input without querying the database.
   */
  #[Test]
  public function sortNodeIdsByScoreReturnsEmptyForEmptyInputWithoutQueryingDatabase(): void {
    $database = $this->createMock(Connection::class);
    $database->expects(self::never())->method('select');

    $time = $this->createMock(TimeInterface::class);

    $scorer = new ForumTrendingScorer($database, $time, $this->mockConfigFactory(7));

    self::assertSame([], $scorer->sortNodeIdsByScore([]));
  }

}
