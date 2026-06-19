<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyInterface;
use Drupal\user\Entity\User;

/**
 * Tests the user_preferences delivery policy (channel-set arithmetic).
 *
 * @group openintranet_notifications
 */
final class UserPreferencesPolicyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'openintranet_notifications',
    'openintranet_notifications_test',
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
   * The policy under test.
   */
  private NotificationDeliveryPolicyInterface $policy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installConfig(['openintranet_notifications']);

    /** @var \Drupal\openintranet_notifications\Policy\DeliveryPolicyManager $manager */
    $manager = $this->container->get('plugin.manager.notification_delivery_policy');
    $this->policy = $manager->createInstance('user_preferences');
  }

  /**
   * Stores a per-type channel preference map for a user.
   *
   * @param int $uid
   *   The user id.
   * @param array<string, bool> $channels
   *   Channel id => enabled map.
   */
  private function setPrefs(int $uid, array $channels): void {
    $this->container->get('entity_type.manager')
      ->getStorage('user_notification_settings')
      ->create([
        'uid' => $uid,
        'preferences' => ['default' => $channels],
      ])
      ->save();
  }

  /**
   * Creates an active user with the given id.
   */
  private function createActiveUser(int $uid): User {
    $user = User::create([
      'uid' => $uid,
      'name' => 'user' . $uid,
      'status' => 1,
    ]);
    $user->save();
    return $user;
  }

  /**
   * The selected set is the intersection of forced/pref and availability.
   */
  public function testChannelSetIsIntersectionWithForcedAndAvailability(): void {
    $type = NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => ['inbox', 'log_only', 'unavailable_stub'],
      'forced_channels' => ['inbox'],
      'delivery_policy' => 'user_preferences',
    ]);
    $type->save();

    // All three globally enabled.
    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['inbox', 'log_only', 'unavailable_stub'])
      ->save();

    $account = $this->createActiveUser(42);
    $this->setPrefs(42, ['log_only' => TRUE, 'unavailable_stub' => TRUE]);
    $recipient = new NotificationRecipient(type: 'user', id: 42, account: $account);

    $channels = $this->policy->selectChannels($type, $recipient, []);
    sort($channels);

    // unavailable_stub dropped on availability despite the pref being on.
    self::assertSame(['inbox', 'log_only'], $channels);
  }

  /**
   * A forced channel survives even when the user disabled it.
   */
  public function testForcedChannelKeptEvenWhenUserDisabled(): void {
    $type = NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => ['inbox'],
      'forced_channels' => ['inbox'],
      'delivery_policy' => 'user_preferences',
    ]);
    $type->save();

    $account = $this->createActiveUser(43);
    $this->setPrefs(43, ['inbox' => FALSE]);
    $recipient = new NotificationRecipient(type: 'user', id: 43, account: $account);

    $channels = $this->policy->selectChannels($type, $recipient, []);
    self::assertContains('inbox', $channels);
  }

  /**
   * A blocked user receives nothing.
   */
  public function testBlockedUserGetsNoChannels(): void {
    $type = NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox'],
      'delivery_policy' => 'user_preferences',
    ]);
    $type->save();

    $account = User::create([
      'uid' => 44,
      'name' => 'blocked',
      'status' => 0,
    ]);
    $account->save();
    $recipient = new NotificationRecipient(type: 'user', id: 44, account: $account);

    self::assertSame([], $this->policy->selectChannels($type, $recipient, []));
  }

  /**
   * The per-channel kill switch drops a channel even when the pref is on.
   */
  public function testKillSwitchDropsChannel(): void {
    $type = NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox'],
      'delivery_policy' => 'user_preferences',
    ]);
    $type->save();

    $this->config('openintranet_notifications.settings')
      ->set('kill_switch', ['log_only' => TRUE])
      ->save();

    $account = $this->createActiveUser(45);
    $this->setPrefs(45, ['log_only' => TRUE]);
    $recipient = new NotificationRecipient(type: 'user', id: 45, account: $account);

    $channels = $this->policy->selectChannels($type, $recipient, []);
    self::assertNotContains('log_only', $channels);
    self::assertContains('inbox', $channels);
  }

}
