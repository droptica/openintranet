<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Condition;

use Drupal\openintranet_notifications\Entity\UserNotificationSettings;

/**
 * Tests the user_channel_enabled ECA condition.
 *
 * @group openintranet_notifications
 */
final class UserChannelEnabledConditionTest extends NotificationConditionKernelTestBase {

  /**
   * The condition is TRUE when the user has the channel enabled for the type.
   */
  public function testTrueWhenEnabled(): void {
    UserNotificationSettings::create([
      'uid' => 7,
      'preferences' => ['default' => ['inbox' => TRUE]],
    ])->save();

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_user_channel_enabled',
      [
        'uid' => '7',
        'notification_type' => 'default',
        'channel' => 'inbox',
      ],
    );
    self::assertTrue($condition->evaluate());
  }

  /**
   * The condition is FALSE when the user has the channel disabled.
   */
  public function testFalseWhenDisabled(): void {
    UserNotificationSettings::create([
      'uid' => 7,
      'preferences' => ['default' => ['inbox' => FALSE]],
    ])->save();

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_user_channel_enabled',
      [
        'uid' => '7',
        'notification_type' => 'default',
        'channel' => 'inbox',
      ],
    );
    self::assertFalse($condition->evaluate());
  }

  /**
   * The negate flag flips the result.
   */
  public function testNegateFlipsResult(): void {
    UserNotificationSettings::create([
      'uid' => 7,
      'preferences' => ['default' => ['inbox' => TRUE]],
    ])->save();

    $condition = $this->conditionManager->createInstance(
      'openintranet_notifications_user_channel_enabled',
      [
        'uid' => '7',
        'notification_type' => 'default',
        'channel' => 'inbox',
        'negate' => TRUE,
      ],
    );
    self::assertFalse($condition->evaluate());
  }

}
