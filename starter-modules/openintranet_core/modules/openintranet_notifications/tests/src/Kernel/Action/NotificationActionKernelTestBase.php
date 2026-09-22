<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\user\Entity\User;

/**
 * Base for ECA notification action kernel tests.
 *
 * Installs the notification stack with node support, registers test channels
 * (log_only/null/inbox) so nothing leaves the test, and provides a default
 * notification type fixture plus a few user accounts.
 */
abstract class NotificationActionKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'dynamic_entity_reference',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'node',
    'openintranet_notifications',
  ];

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * The ECA token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected $tokenServices;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['openintranet_notifications']);
    // Replace the shipped "default" type with a fixture whose policy selects
    // [inbox, log_only] for any user.
    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
    ])->save();

    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['inbox', 'log_only'])
      ->save();

    // User 1 must exist so the current user resolves to a real account.
    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();
    foreach ([41, 42, 43] as $uid) {
      User::create(['uid' => $uid, 'name' => 'user' . $uid, 'status' => 1])->save();
    }

    // Run the actions as user 1 (superuser): these fan-out tests are not about
    // the broadcast permission gate, so the acting account must clear it. The
    // gate itself is exercised by BroadcastPermissionGateTest, which overrides
    // the current user per case.
    $this->container->get('current_user')->setAccount(User::load(1));

    $this->actionManager = $this->container->get('plugin.manager.action');
    $this->tokenServices = $this->container->get('eca.token_services');
  }

  /**
   * Creates an article node owned by the given user.
   *
   * @param int $ownerUid
   *   The author uid.
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\Entity\Node
   *   The saved node.
   */
  protected function createArticle(int $ownerUid = 41, string $title = 'Hello'): Node {
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'uid' => $ownerUid,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Loads every notification entity.
   *
   * @return array<int, \Drupal\openintranet_notifications\Entity\NotificationInterface>
   *   The notification entities.
   */
  protected function loadAllNotifications(): array {
    return $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->loadMultiple();
  }

  /**
   * Loads every delivery row for a notification.
   *
   * @param int $notificationId
   *   The notification id.
   *
   * @return array<int, \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface>
   *   The delivery entities.
   */
  protected function loadDeliveriesFor(int $notificationId): array {
    return $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery')
      ->loadByProperties(['notification_id' => $notificationId]);
  }

  /**
   * Reloads a notification fresh from storage.
   *
   * @param int $id
   *   The notification id.
   *
   * @return \Drupal\openintranet_notifications\Entity\NotificationInterface
   *   The reloaded notification.
   */
  protected function reloadNotification(int $id): object {
    $storage = $this->container->get('entity_type.manager')->getStorage('openintranet_notification');
    $storage->resetCache([$id]);
    return $storage->load($id);
  }

  /**
   * The number of pending delivery queue items.
   */
  protected function queueCount(): int {
    return (int) \Drupal::queue('openintranet_notification_delivery')->numberOfItems();
  }

  /**
   * Reads an output uid-list token the way a consuming action would.
   *
   * A non-token-typed array stored via addTokenData() is wrapped in a DTO;
   * getOrReplace('[name]') returns that DTO, whose flat values are the uids.
   *
   * @param string $name
   *   The token name.
   *
   * @return array<int, int>
   *   The sorted uid list.
   */
  protected function readUidToken(string $name): array {
    $value = $this->tokenServices->getOrReplace('[' . $name . ']');
    if ($value instanceof DataTransferObject) {
      $value = $value->toArray();
    }
    $uids = array_map('intval', \is_array($value) ? $value : [$value]);
    sort($uids);
    return $uids;
  }

}
