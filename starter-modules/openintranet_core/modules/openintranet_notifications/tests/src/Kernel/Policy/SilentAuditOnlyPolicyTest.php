<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Policy;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyInterface;
use Drupal\user\Entity\User;

/**
 * Tests the silent_audit_only delivery policy.
 *
 * @group openintranet_notifications
 */
final class SilentAuditOnlyPolicyTest extends KernelTestBase {

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
    $this->policy = $manager->createInstance('silent_audit_only');
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
   * Selects nothing even when every candidate channel is usable.
   */
  public function testAlwaysSelectsNothing(): void {
    $type = NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox'],
      'delivery_policy' => 'silent_audit_only',
    ]);
    $type->save();

    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['inbox', 'log_only'])
      ->save();

    $account = User::create([
      'uid' => 60,
      'name' => 'user60',
      'status' => 1,
    ]);
    $account->save();
    $recipient = new NotificationRecipient(type: 'user', id: 60, account: $account);

    self::assertSame([], $this->policy->selectChannels($type, $recipient, []));
  }

}
