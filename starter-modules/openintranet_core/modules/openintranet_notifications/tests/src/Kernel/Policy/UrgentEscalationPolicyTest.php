<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Policy;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyInterface;
use Drupal\user\Entity\User;

/**
 * Tests the urgent_escalation delivery policy (priority-tiered selection).
 *
 * @group openintranet_notifications
 */
final class UrgentEscalationPolicyTest extends KernelTestBase {

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
    $this->deleteShippedNotificationTypes();

    /** @var \Drupal\openintranet_notifications\Policy\DeliveryPolicyManager $manager */
    $manager = $this->container->get('plugin.manager.notification_delivery_policy');
    $this->policy = $manager->createInstance('urgent_escalation');
  }

  /**
   * Deletes the notification types shipped in the module's config/install.
   */
  private function deleteShippedNotificationTypes(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $storage->delete($storage->loadMultiple());
  }

  /**
   * Creates a notification type with the given default priority and channels.
   *
   * @param string $priority
   *   The default priority.
   * @param string[] $defaultChannels
   *   The default channel ids.
   * @param string[] $forcedChannels
   *   The forced channel ids.
   */
  private function createType(string $priority, array $defaultChannels, array $forcedChannels = []): NotificationType {
    $type = NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_priority' => $priority,
      'default_channels' => $defaultChannels,
      'forced_channels' => $forcedChannels,
      'delivery_policy' => 'urgent_escalation',
    ]);
    $type->save();
    return $type;
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
   * Enables the given channels globally.
   *
   * @param string[] $channels
   *   The channel ids to enable.
   */
  private function enableChannels(array $channels): void {
    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', $channels)
      ->save();
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
   * An urgent priority hits the full usable candidate set (ignores prefs).
   */
  public function testUrgentSelectsFullUsableSet(): void {
    $type = $this->createType('urgent', ['inbox', 'log_only']);
    $this->enableChannels(['inbox', 'log_only']);

    $account = $this->createActiveUser(70);
    // Even with every channel preference off, urgent escalates to all of them.
    $this->setPrefs(70, ['inbox' => FALSE, 'log_only' => FALSE]);
    $recipient = new NotificationRecipient(type: 'user', id: 70, account: $account);

    $channels = $this->policy->selectChannels($type, $recipient, []);
    sort($channels);
    self::assertSame(['inbox', 'log_only'], $channels);
  }

  /**
   * A context priority of urgent overrides the type's default priority.
   */
  public function testUrgentViaContextOverridesType(): void {
    $type = $this->createType('normal', ['inbox', 'log_only']);
    $this->enableChannels(['inbox', 'log_only']);

    $account = $this->createActiveUser(71);
    $this->setPrefs(71, ['inbox' => FALSE, 'log_only' => FALSE]);
    $recipient = new NotificationRecipient(type: 'user', id: 71, account: $account);

    $channels = $this->policy->selectChannels($type, $recipient, ['priority' => 'urgent']);
    sort($channels);
    self::assertSame(['inbox', 'log_only'], $channels);
  }

  /**
   * A normal priority stays restrained: a non-preferred channel is dropped.
   */
  public function testNormalDropsNonPreferredChannel(): void {
    $type = $this->createType('normal', ['inbox', 'log_only']);
    $this->enableChannels(['inbox', 'log_only']);

    $account = $this->createActiveUser(72);
    // Inbox preferred, log_only not: restrained mode drops log_only.
    $this->setPrefs(72, ['inbox' => TRUE, 'log_only' => FALSE]);
    $recipient = new NotificationRecipient(type: 'user', id: 72, account: $account);

    self::assertSame(['inbox'], $this->policy->selectChannels($type, $recipient, []));
  }

  /**
   * A forced channel is kept in restrained mode even when not preferred.
   */
  public function testNormalKeepsForcedChannel(): void {
    $type = $this->createType('normal', ['inbox', 'log_only'], ['log_only']);
    $this->enableChannels(['inbox', 'log_only']);

    $account = $this->createActiveUser(73);
    $this->setPrefs(73, ['inbox' => FALSE, 'log_only' => FALSE]);
    $recipient = new NotificationRecipient(type: 'user', id: 73, account: $account);

    // Inbox is not preferred and not forced → dropped; log_only forced → kept.
    self::assertSame(['log_only'], $this->policy->selectChannels($type, $recipient, []));
  }

  /**
   * A blocked user receives nothing, even at urgent priority.
   */
  public function testBlockedUserGetsNothing(): void {
    $type = $this->createType('urgent', ['inbox', 'log_only']);
    $this->enableChannels(['inbox', 'log_only']);

    $account = User::create([
      'uid' => 74,
      'name' => 'blocked',
      'status' => 0,
    ]);
    $account->save();
    $recipient = new NotificationRecipient(type: 'user', id: 74, account: $account);

    self::assertSame([], $this->policy->selectChannels($type, $recipient, []));
  }

}
