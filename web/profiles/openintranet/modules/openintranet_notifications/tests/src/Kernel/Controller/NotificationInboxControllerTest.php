<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Access\NotificationViewAccess;
use Drupal\openintranet_notifications\Controller\NotificationInboxController;
use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the user-facing /notifications inbox + single view (Chunk 5C).
 *
 * Covers own-only isolation, mark-seen on inbox render, mark-read on view and
 * the own-only access checker (§14 security: deny other users + anonymous).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Controller\NotificationInboxController
 */
final class NotificationInboxControllerTest extends KernelTestBase {

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
    'openintranet_notifications',
  ];

  /**
   * The inbox controller under test.
   */
  private NotificationInboxController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
    $this->installConfig(['user']);
    // The 'short' date format the inbox renders lives in system config.
    $this->installConfig(['system']);
    // User 1 is the superuser; create it so later uids are real accounts.
    User::create(['name' => 'root', 'uid' => 1, 'status' => 1])->save();
    $this->controller = NotificationInboxController::create($this->container);
  }

  /**
   * The inbox lists only the current user's notifications and marks them seen.
   *
   * @covers ::inbox
   * @covers ::create
   */
  public function testInboxListsOwnOnlyAndMarksSeen(): void {
    $userA = $this->makeUser('a');
    $userB = $this->makeUser('b');

    $a1 = $this->makeNotification($userA, 'A first');
    $a2 = $this->makeNotification($userA, 'A second');
    $b1 = $this->makeNotification($userB, 'B only');

    self::assertNull($a1->get('seen_at')->value, 'Notification starts unseen.');

    $this->setCurrentUser($userA);
    $build = $this->controller->inbox();

    // The rendered markup lists only user A's subjects, never user B's.
    $html = (string) $this->container->get('renderer')->renderRoot($build);
    self::assertStringContainsString('A first', $html);
    self::assertStringContainsString('A second', $html);
    self::assertStringNotContainsString('B only', $html, 'User B notifications must not leak.');

    // The page is uncacheable because rendering it has the mark-seen side
    // effect.
    self::assertSame(0, $build['#cache']['max-age'] ?? -1);

    // After rendering the inbox, user A's listed notifications are now seen,
    // while user B's untouched notification stays unseen.
    self::assertNotNull($this->reload($a1)->get('seen_at')->value, 'A1 is seen.');
    self::assertNotNull($this->reload($a2)->get('seen_at')->value, 'A2 is seen.');
    self::assertNull($this->reload($b1)->get('seen_at')->value, 'B1 stays unseen.');
  }

  /**
   * The inbox listing and its mark-seen side effect are bounded.
   *
   * Creating more than the per-request cap proves the query is ranged and that
   * only the listed (most recent) notifications are marked seen.
   *
   * @covers ::inbox
   */
  public function testInboxListingIsBounded(): void {
    $user = $this->makeUser('a');
    $this->setCurrentUser($user);

    // Create more than the controller's INBOX_LIMIT (50). The oldest one falls
    // outside the listing window and must stay unseen.
    $created = [];
    for ($i = 0; $i < 55; $i++) {
      $created[] = $this->makeNotification($user, 'N' . $i);
    }
    $oldest = $created[0];
    self::assertNull($oldest->get('seen_at')->value, 'The oldest starts unseen.');

    $build = $this->controller->inbox();
    self::assertCount(50, $build['list']['#items'], 'The listing is capped at the limit.');

    // The oldest is outside the listing window, so it is not marked seen —
    // proving the mark-seen side effect is bounded to the listed set.
    self::assertNull(
      $this->reload($oldest)->get('seen_at')->value,
      'A notification outside the listing window is not marked seen.',
    );
    // A recent one inside the window is marked seen.
    self::assertNotNull(
      $this->reload($created[54])->get('seen_at')->value,
      'A notification inside the listing window is marked seen.',
    );
  }

  /**
   * Visiting a single notification marks it read.
   *
   * @covers ::view
   */
  public function testViewMarksRead(): void {
    $userA = $this->makeUser('a');
    $notification = $this->makeNotification($userA, 'Read me');
    self::assertFalse($notification->isRead(), 'Notification starts unread.');

    $this->setCurrentUser($userA);
    $this->controller->view($notification);

    self::assertTrue($this->reload($notification)->isRead(), 'View stamps read_at.');
  }

  /**
   * The owner may view their own notification; others and anonymous may not.
   *
   * @covers \Drupal\openintranet_notifications\Access\NotificationViewAccess::access
   */
  public function testViewAccessIsOwnOnly(): void {
    $checker = NotificationViewAccess::create($this->container);
    $userA = $this->makeUser('a');
    $userB = $this->makeUser('b');
    $admin = $this->makeUser('admin', ['view notification logs']);
    $notification = $this->makeNotification($userA, 'Private');

    self::assertTrue(
      $checker->access($userA, $notification)->isAllowed(),
      'The owner may view their own notification.',
    );
    self::assertFalse(
      $checker->access($userB, $notification)->isAllowed(),
      'A different user must NOT view another user\'s notification.',
    );
    self::assertFalse(
      $checker->access(User::getAnonymousUser(), $notification)->isAllowed(),
      'Anonymous must be denied.',
    );
    self::assertTrue(
      $checker->access($admin, $notification)->isAllowed(),
      'A "view notification logs" admin may view any notification.',
    );
  }

  /**
   * Creates a user with the given permissions.
   *
   * @param string $name
   *   The username (also used to derive a role id).
   * @param string[] $permissions
   *   The permissions to grant.
   */
  private function makeUser(string $name, array $permissions = []): User {
    $rid = NULL;
    if ($permissions !== []) {
      $role = Role::create(['id' => $name . '_role', 'label' => $name . ' role']);
      $role->save();
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
      $rid = $role->id();
    }
    $values = ['name' => $name, 'status' => 1];
    if ($rid !== NULL) {
      $values['roles'] = [$rid];
    }
    $user = User::create($values);
    $user->save();
    return $user;
  }

  /**
   * Creates an unread/unseen notification for the given recipient.
   */
  private function makeNotification(User $recipient, string $subject): NotificationInterface {
    $notification = Notification::create([
      'type' => 'default',
      'uid' => $recipient->id(),
      'subject' => $subject,
      'body' => $subject . ' body',
      'status' => 'created',
      'priority' => 'normal',
    ]);
    $notification->save();
    return $notification;
  }

  /**
   * Reloads a notification from storage so saved state is visible.
   */
  private function reload(NotificationInterface $notification): NotificationInterface {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification');
    $storage->resetCache([$notification->id()]);
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface $reloaded */
    $reloaded = $storage->load($notification->id());
    return $reloaded;
  }

  /**
   * Switches the current user the controller reads from.
   */
  private function setCurrentUser(AccountInterface $account): void {
    $this->container->get('current_user')->setAccount($account);
  }

}
