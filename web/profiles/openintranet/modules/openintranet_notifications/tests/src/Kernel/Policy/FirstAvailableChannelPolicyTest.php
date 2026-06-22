<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Policy;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyInterface;
use Drupal\user\Entity\User;

/**
 * Tests the first_available_channel delivery policy.
 *
 * @group openintranet_notifications
 */
final class FirstAvailableChannelPolicyTest extends KernelTestBase {

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
    $this->policy = $manager->createInstance('first_available_channel');
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
   * Creates a notification type with the given channels.
   *
   * @param string[] $defaultChannels
   *   The default channel ids.
   * @param string[] $forcedChannels
   *   The forced channel ids.
   */
  private function createType(array $defaultChannels, array $forcedChannels = []): NotificationType {
    $type = NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => $defaultChannels,
      'forced_channels' => $forcedChannels,
      'delivery_policy' => 'first_available_channel',
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
   * Returns the first usable candidate, in candidate order, as a single set.
   */
  public function testReturnsFirstUsableCandidate(): void {
    $type = $this->createType(['inbox', 'log_only']);
    $this->enableChannels(['inbox', 'log_only']);

    $account = $this->createActiveUser(50);
    $recipient = new NotificationRecipient(type: 'user', id: 50, account: $account);

    self::assertSame(['inbox'], $this->policy->selectChannels($type, $recipient, []));
  }

  /**
   * Skips an unusable earlier candidate and returns the next usable one.
   */
  public function testSkipsUnavailableEarlierCandidate(): void {
    // unavailable_stub is first but never available, so log_only wins.
    $type = $this->createType(['unavailable_stub', 'log_only']);
    $this->enableChannels(['unavailable_stub', 'log_only']);

    $account = $this->createActiveUser(51);
    $recipient = new NotificationRecipient(type: 'user', id: 51, account: $account);

    self::assertSame(['log_only'], $this->policy->selectChannels($type, $recipient, []));
  }

  /**
   * Returns nothing when no candidate is usable.
   */
  public function testReturnsEmptyWhenNoneUsable(): void {
    $type = $this->createType(['unavailable_stub']);
    $this->enableChannels(['unavailable_stub']);

    $account = $this->createActiveUser(52);
    $recipient = new NotificationRecipient(type: 'user', id: 52, account: $account);

    self::assertSame([], $this->policy->selectChannels($type, $recipient, []));
  }

  /**
   * A blocked user receives nothing.
   */
  public function testBlockedUserGetsNothing(): void {
    $type = $this->createType(['inbox', 'log_only']);
    $this->enableChannels(['inbox', 'log_only']);

    $account = User::create([
      'uid' => 53,
      'name' => 'blocked',
      'status' => 0,
    ]);
    $account->save();
    $recipient = new NotificationRecipient(type: 'user', id: 53, account: $account);

    self::assertSame([], $this->policy->selectChannels($type, $recipient, []));
  }

}
