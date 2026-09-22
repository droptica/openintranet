<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Condition;

/**
 * Tests the below_rate_limit ECA condition.
 *
 * @group openintranet_notifications
 */
final class BelowRateLimitConditionTest extends NotificationConditionKernelTestBase {

  /**
   * Records a number of rate-limit hits for a tuple via the limiter service.
   */
  private function seedCount(int $uid, string $channel, string $type, int $hits): void {
    $limiter = $this->container->get('openintranet_notifications.rate_limiter');
    for ($i = 0; $i < $hits; $i++) {
      $limiter->allow($uid, $channel, $type, PHP_INT_MAX, 3600);
    }
  }

  /**
   * TRUE when the current count is below the configured limit.
   */
  public function testTrueWhenBelowLimit(): void {
    $this->seedCount(42, 'inbox', 'default', 1);

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_below_rate_limit',
      [
        'uid' => '42',
        'channel' => 'inbox',
        'notification_type' => 'default',
        'limit' => 3,
      ],
    );
    self::assertTrue($condition->evaluate());
  }

  /**
   * FALSE when the count has reached the limit.
   */
  public function testFalseWhenAtLimit(): void {
    $this->seedCount(42, 'inbox', 'default', 3);

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_below_rate_limit',
      [
        'uid' => '42',
        'channel' => 'inbox',
        'notification_type' => 'default',
        'limit' => 3,
      ],
    );
    self::assertFalse($condition->evaluate());
  }

  /**
   * Evaluating the condition does not consume budget (no counter mutation).
   */
  public function testEvaluateDoesNotConsumeBudget(): void {
    $this->seedCount(42, 'inbox', 'default', 2);

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_below_rate_limit',
      [
        'uid' => '42',
        'channel' => 'inbox',
        'notification_type' => 'default',
        'limit' => 3,
      ],
    );
    // Many evaluations must not push the stored count over the limit.
    self::assertTrue($condition->evaluate());
    self::assertTrue($condition->evaluate());
    self::assertTrue($condition->evaluate());

    $store = $this->container->get('keyvalue.expirable')
      ->get('openintranet_notifications.rate_limit');
    self::assertSame(2, $store->get('42:inbox:default'));
  }

  /**
   * The negate flag flips the result.
   */
  public function testNegateFlipsResult(): void {
    $this->seedCount(42, 'inbox', 'default', 3);

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_below_rate_limit',
      [
        'uid' => '42',
        'channel' => 'inbox',
        'notification_type' => 'default',
        'limit' => 3,
        'negate' => TRUE,
      ],
    );
    // At limit → raw FALSE → negated TRUE.
    self::assertTrue($condition->evaluate());
  }

}
