<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the "notify all active users" gate on broadcast dispatches (§8).
 *
 * A dispatch is a broadcast when the action carries no explicit recipients AND
 * the type's resolvers include a broad resolver (role_users / all_active_users)
 * — it fans out to a whole role/all-active set. Such a dispatch is allowed only
 * when the acting account holds 'notify all active users'. A normal per-author
 * or explicit-recipient dispatch is never gated.
 *
 * @group openintranet_notifications
 */
final class BroadcastPermissionGateTest extends NotificationActionKernelTestBase {

  /**
   * Installs a role-fanning broadcast type and two editors.
   */
  private function installBroadcastType(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    foreach ([41, 42] as $uid) {
      $user = User::load($uid);
      $user->addRole('editor');
      $user->save();
    }

    NotificationType::create([
      'id' => 'broadcast',
      'label' => 'Broadcast',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'recipient_resolvers' => [
        ['id' => 'role_users', 'configuration' => ['role' => 'editor']],
      ],
    ])->save();
  }

  /**
   * Sets the current user to a non-superuser with/without the broadcast right.
   *
   * The base test runs as user 1 (superuser, which clears every permission),
   * so this overrides it with a plain authenticated account whose role grants
   * 'notify all active users' only when requested.
   */
  private function setActor(bool $withPermission): void {
    if ($withPermission && Role::load('broadcaster') === NULL) {
      Role::create(['id' => 'broadcaster', 'label' => 'Broadcaster'])
        ->grantPermission('notify all active users')
        ->save();
    }

    $actor = User::create([
      'uid' => 50,
      'name' => 'actor',
      'status' => 1,
      'roles' => $withPermission ? ['broadcaster'] : [],
    ]);
    $actor->save();
    $this->container->get('current_user')->setAccount($actor);
  }

  /**
   * Without the permission, a broadcast dispatch is denied and creates nothing.
   */
  public function testBroadcastDeniedWithoutPermission(): void {
    $this->installBroadcastType();
    $this->setActor(FALSE);

    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'broadcast',
      'recipients' => '',
    ]);

    self::assertFalse($action->access(NULL), 'access() forbids the broadcast.');

    $action->execute(NULL);
    self::assertCount(0, $this->loadAllNotifications(), 'No notifications are created.');
  }

  /**
   * With the permission, a broadcast dispatch proceeds and fans out.
   */
  public function testBroadcastProceedsWithPermission(): void {
    $this->installBroadcastType();
    $this->setActor(TRUE);

    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'broadcast',
      'recipients' => '',
    ]);

    self::assertTrue($action->access(NULL), 'access() allows the broadcast.');

    $action->execute(NULL);
    self::assertCount(2, $this->loadAllNotifications(), 'The role fan-out runs.');
  }

  /**
   * A per-author dispatch (entity_author) is never gated by the permission.
   */
  public function testPerAuthorDispatchNotGated(): void {
    NotificationType::create([
      'id' => 'author',
      'label' => 'Author',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'recipient_resolvers' => [
        ['id' => 'entity_author', 'configuration' => []],
      ],
    ])->save();
    // The actor lacks the broadcast permission, yet a per-author send proceeds.
    $this->setActor(FALSE);

    $node = $this->createArticle(42);
    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'author',
      'recipients' => '',
    ]);

    self::assertTrue($action->access(NULL), 'A per-author dispatch is not gated.');

    $action->execute($node);
    self::assertCount(1, $this->loadAllNotifications());
  }

  /**
   * Explicit recipients are never a broadcast, even with a broad-resolver type.
   *
   * The author supplies a concrete recipient list, so the type's role resolver
   * never runs and the dispatch is not a broadcast regardless of permission.
   */
  public function testExplicitRecipientsNotGated(): void {
    $this->installBroadcastType();
    $this->setActor(FALSE);

    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'broadcast',
      'recipients' => '[recipients]',
    ]);
    $this->tokenServices->addTokenData('recipients', [43]);

    self::assertTrue($action->access(NULL), 'An explicit-recipient dispatch is not gated.');

    $action->execute(NULL);
    self::assertCount(1, $this->loadAllNotifications());
  }

}
