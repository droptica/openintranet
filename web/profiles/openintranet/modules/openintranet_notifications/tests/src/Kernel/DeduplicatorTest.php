<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

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

}
