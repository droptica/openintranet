<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Condition;

/**
 * Tests the not_recently_sent ECA condition.
 *
 * @group openintranet_notifications
 */
final class NotRecentlySentConditionTest extends NotificationConditionKernelTestBase {

  /**
   * TRUE when the dedupe key was never recorded (not recently sent).
   */
  public function testTrueWhenNotRecorded(): void {
    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_not_recently_sent',
      ['dedupe_key' => 'fresh-key'],
    );
    self::assertTrue($condition->evaluate());
  }

  /**
   * FALSE when the dedupe key is already recorded within the window.
   */
  public function testFalseWhenRecorded(): void {
    $this->container->get('openintranet_notifications.deduplicator')
      ->record('seen-key', 3600);

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_not_recently_sent',
      ['dedupe_key' => 'seen-key'],
    );
    self::assertFalse($condition->evaluate());
  }

  /**
   * FALSE when the resolved key is empty (no key means we cannot vouch).
   */
  public function testFalseWhenKeyEmpty(): void {
    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_not_recently_sent',
      ['dedupe_key' => ''],
    );
    self::assertFalse($condition->evaluate());
  }

  /**
   * The negate flag flips the result.
   */
  public function testNegateFlipsResult(): void {
    $this->container->get('openintranet_notifications.deduplicator')
      ->record('seen-key', 3600);

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_not_recently_sent',
      ['dedupe_key' => 'seen-key', 'negate' => TRUE],
    );
    // Recorded → raw FALSE → negated TRUE.
    self::assertTrue($condition->evaluate());
  }

}
