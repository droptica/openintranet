<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Service\NotificationDispatcher;
use Drupal\openintranet_notifications\Service\NotificationFactory;
use Drupal\user\Entity\User;

/**
 * Regression guard: a clean install delivers the real types out of the box.
 *
 * Uses the shipped config UNMODIFIED — the enabled_channels list and the
 * default_user_preferences rows as installed — so it proves a default user (no
 * saved preferences) actually receives a new_comment notification on inbox and
 * email_core, instead of the dispatcher cancelling it for an empty channel set.
 *
 * @group openintranet_notifications
 */
final class OutOfBoxDeliveryTest extends KernelTestBase {

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
   * The notification factory.
   */
  private NotificationFactory $factory;

  /**
   * The dispatcher under test.
   */
  private NotificationDispatcher $dispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    // Install the shipped config as-is: enabled_channels, the default_user_
    // preferences rows and the new_comment notification type all come from
    // config/install — nothing is overridden here.
    $this->installConfig(['openintranet_notifications']);

    $this->factory = $this->container->get('openintranet_notifications.notification_factory');
    $this->dispatcher = $this->container->get('openintranet_notifications.notification_dispatcher');
  }

  /**
   * Reloads a notification fresh from storage.
   */
  private function reload(int $id): object {
    $storage = $this->container->get('entity_type.manager')->getStorage('openintranet_notification');
    $storage->resetCache([$id]);
    return $storage->load($id);
  }

  /**
   * The channel ids of every delivery row for a notification.
   *
   * @param int $notificationId
   *   The notification id.
   *
   * @return string[]
   *   The sorted channel ids.
   */
  private function deliveryChannelsFor(int $notificationId): array {
    $deliveries = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery')
      ->loadByProperties(['notification_id' => $notificationId]);
    $channels = array_map(
      static fn (NotificationDeliveryInterface $delivery) => (string) $delivery->get('channel')->value,
      $deliveries,
    );
    sort($channels);
    return $channels;
  }

  /**
   * A default user with no saved preferences still receives a new_comment.
   *
   * This is the out-of-box delivery guard: with the shipped config, a clean
   * install must NOT cancel the notification and must produce deliveries on the
   * inbox and email_core channels the default_user_preferences row enables.
   */
  public function testNewCommentDeliversToDefaultUserOutOfBox(): void {
    // A default user: active, with an email, and NO user_notification_settings
    // entity — exactly a fresh account that never touched the preferences form.
    User::create([
      'uid' => 42,
      'name' => 'recipient',
      'mail' => 'recipient@example.com',
      'status' => 1,
    ])->save();

    $n = $this->factory->create('new_comment', [
      'uid' => 42,
      'subject' => 'New comment',
      'body' => 'Body',
    ]);
    $n->save();
    $this->dispatcher->enqueue($n);

    // Not silently dropped: the notification reached the queued state.
    self::assertSame('queued', $this->reload((int) $n->id())->get('status')->value);
    // Delivered on both channels the shipped default preferences enable.
    self::assertSame(['email_core', 'inbox'], $this->deliveryChannelsFor((int) $n->id()));
  }

}
