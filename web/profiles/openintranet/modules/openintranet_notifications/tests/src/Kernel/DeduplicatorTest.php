<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Service\Deduplicator;

/**
 * Tests the notification-level deduplicator service.
 *
 * @group openintranet_notifications
 */
final class DeduplicatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'text',
    'token',
    'key',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * The deduplicator under test.
   */
  private Deduplicator $deduplicator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->deduplicator = $this->container->get('openintranet_notifications.deduplicator');
  }

  /**
   * The key is stable for the same inputs and detected after record().
   */
  public function testComputesStableKeyAndDetectsDuplicateWithinWindow(): void {
    $key = $this->deduplicator->computeKey('new_comment', 'node:5', 'user:42', 'thread-1');
    self::assertSame(
      $key,
      $this->deduplicator->computeKey('new_comment', 'node:5', 'user:42', 'thread-1'),
    );

    self::assertFalse($this->deduplicator->isDuplicate($key));
    $this->deduplicator->record($key, 600);
    self::assertTrue($this->deduplicator->isDuplicate($key));
  }

  /**
   * Different inputs produce different keys.
   */
  public function testDistinctInputsProduceDistinctKeys(): void {
    $a = $this->deduplicator->computeKey('new_comment', 'node:5', 'user:42');
    $b = $this->deduplicator->computeKey('new_comment', 'node:5', 'user:43');
    self::assertNotSame($a, $b);
  }

  /**
   * Only one claimant wins until the owning claimant releases the key.
   */
  public function testClaimAndReleaseSemantics(): void {
    $key = $this->deduplicator->computeKey('new_comment', 'node:5', 'user:42');

    self::assertTrue($this->deduplicator->claim($key, 600));
    self::assertFalse($this->deduplicator->claim($key, 600));
    self::assertTrue($this->deduplicator->isDuplicate($key));

    $this->deduplicator->release($key);
    self::assertFalse($this->deduplicator->isDuplicate($key));
    self::assertTrue($this->deduplicator->claim($key, 600));
  }

  /**
   * An unavailable claim lock prevents the claimant from winning.
   */
  public function testUnavailableLockRejectsClaim(): void {
    $key = $this->deduplicator->computeKey('new_comment', 'node:5', 'user:42');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(FALSE);
    $lock->method('wait')->willReturn(TRUE);
    $deduplicator = new Deduplicator(
      $this->container->get('keyvalue.expirable'),
      $this->container->get('datetime.time'),
      $lock,
    );

    self::assertFalse($deduplicator->claim($key, 600));
    self::assertFalse($deduplicator->isDuplicate($key));
  }

  /**
   * A stale owner cannot release a newer claimant's replacement claim.
   */
  public function testStaleReleasePreservesNewerClaim(): void {
    $key = $this->deduplicator->computeKey('new_comment', 'node:5', 'user:42');
    self::assertTrue($this->deduplicator->claim($key, 600));

    // Simulate the first claim expiring before its dispatcher abandons.
    $factory = $this->container->get('keyvalue.expirable');
    $factory->get('openintranet_notifications.dedupe')->delete($key);

    $newer = new Deduplicator(
      $factory,
      $this->container->get('datetime.time'),
      $this->container->get('lock'),
    );
    self::assertTrue($newer->claim($key, 600));

    $this->deduplicator->release($key);
    self::assertTrue($newer->isDuplicate($key));

    $newer->release($key);
    self::assertFalse($newer->isDuplicate($key));
  }

}
