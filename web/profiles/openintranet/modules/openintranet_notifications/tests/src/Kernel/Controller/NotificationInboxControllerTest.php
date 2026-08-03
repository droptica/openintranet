<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Controller;

use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Controller\NotificationInboxController;
use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
    $this->container->get('router.request_context')
      ->setCompleteBaseUrl('https://intranet.example');
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
   * Entering the inbox marks the listed notifications read (§10).
   *
   * §10 ("mark-as-read po wejściu"): visiting /notifications marks the listed
   * unread notifications read (read_at set) so the bell unread count drops.
   * Read implies seen, an already-read one is not re-stamped, and another
   * user's notifications are untouched (own-only).
   *
   * @covers ::inbox
   */
  public function testInboxMarksListedNotificationsRead(): void {
    $userA = $this->makeUser('a');
    $userB = $this->makeUser('b');

    $a1 = $this->makeNotification($userA, 'A first');
    $a2 = $this->makeNotification($userA, 'A second');
    $b1 = $this->makeNotification($userB, 'B only');

    // One of user A's notifications is already read; entering must not re-stamp
    // its read_at (idempotent).
    $a2->setRead();
    $a2->save();
    $a2Stamp = (int) $this->reload($a2)->get('read_at')->value;
    self::assertGreaterThan(0, $a2Stamp, 'A2 starts read.');
    self::assertFalse($a1->isRead(), 'A1 starts unread.');

    $this->setCurrentUser($userA);
    $this->controller->inbox();

    // The previously-unread listed notification is now read and (read implies
    // seen) also seen.
    $a1Reloaded = $this->reload($a1);
    self::assertTrue($a1Reloaded->isRead(), 'Entering the inbox marks A1 read.');
    self::assertTrue($a1Reloaded->isSeen(), 'Read implies seen for A1.');

    // The already-read notification keeps its original read_at (no re-stamp).
    self::assertSame(
      $a2Stamp,
      (int) $this->reload($a2)->get('read_at')->value,
      'An already-read notification is not re-stamped on entry.',
    );

    // Another user's notification is untouched (own-only).
    self::assertFalse($this->reload($b1)->isRead(), 'B1 stays unread (own-only).');
    self::assertFalse($this->reload($b1)->isSeen(), 'B1 stays unseen (own-only).');
  }

  /**
   * The inbox listing and its mark-seen side effect are bounded.
   *
   * Creating more than one page proves the query is paged and that only the
   * listed (most recent) notifications are marked seen.
   *
   * @covers ::inbox
   */
  public function testInboxListingIsBounded(): void {
    $user = $this->makeUser('a');
    $this->setCurrentUser($user);

    // Create more than one page (page size 25). The oldest one falls outside
    // the first page's listing window and must stay unseen.
    $created = [];
    for ($i = 0; $i < 55; $i++) {
      $created[] = $this->makeNotification($user, 'N' . $i);
    }
    $oldest = $created[0];
    self::assertNull($oldest->get('seen_at')->value, 'The oldest starts unseen.');

    $build = $this->controller->inbox();
    self::assertCount(25, $build['list']['#items'], 'The listing is capped at the page size.');

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
   * Viewing an already-read notification does not re-save it.
   *
   * @covers ::view
   */
  public function testViewIsIdempotentForReadNotification(): void {
    $userA = $this->makeUser('a');
    $notification = $this->makeNotification($userA, 'Read once');
    $this->setCurrentUser($userA);

    // First view stamps read_at.
    $this->controller->view($notification);
    $stamp = (int) $this->reload($notification)->get('read_at')->value;
    self::assertGreaterThan(0, $stamp, 'The first view stamps read_at.');

    // Viewing again must not re-stamp (the entity is only saved when unread).
    $this->controller->view($this->reload($notification));
    self::assertSame(
      $stamp,
      (int) $this->reload($notification)->get('read_at')->value,
      'A second view does not re-stamp read_at.',
    );
  }

  /**
   * A notification carrying an internal target redirects there on view.
   *
   * @covers ::view
   */
  public function testViewRedirectsToInternalTargetUrl(): void {
    $userA = $this->makeUser('a');
    $notification = $this->makeNotification($userA, 'Go inside');
    $notification->set('url', '/inside/target');
    $notification->save();
    $this->setCurrentUser($userA);

    $response = $this->controller->view($notification);

    self::assertInstanceOf(LocalRedirectResponse::class, $response);
    self::assertSame('/inside/target', $response->getTargetUrl());
  }

  /**
   * A same-site absolute target remains an allowed redirect.
   *
   * @covers ::view
   */
  public function testViewRedirectsToSameSiteAbsoluteTargetUrl(): void {
    $userA = $this->makeUser('a');
    $notification = $this->makeNotification($userA, 'Go same-site');
    $notification->set('url', 'https://intranet.example/inside/target');
    $notification->save();
    $this->setCurrentUser($userA);

    $response = $this->controller->view($notification);

    self::assertInstanceOf(LocalRedirectResponse::class, $response);
    self::assertSame('https://intranet.example/inside/target', $response->getTargetUrl());
  }

  /**
   * A stored external target falls back to the notification inbox.
   *
   * @covers ::view
   */
  public function testViewRejectsExternalTargetUrl(): void {
    $userA = $this->makeUser('a');
    $notification = $this->makeNotification($userA, 'Do not leave');
    $notification->set('url', 'https://evil.example/phishing');
    $notification->save();
    $this->setCurrentUser($userA);

    $response = $this->controller->view($notification);
    $inboxUrl = Url::fromRoute(
      'openintranet_notifications.inbox',
      [],
      ['absolute' => TRUE],
    )->toString();

    self::assertInstanceOf(RedirectResponse::class, $response);
    self::assertSame($inboxUrl, $response->getTargetUrl());
  }

  /**
   * The owner may view their own notification; others and anonymous may not.
   *
   * The canonical route guards with _entity_access 'view', so this asserts the
   * entity access handler the route now relies on.
   *
   * @covers \Drupal\openintranet_notifications\Entity\Handler\NotificationAccessControlHandler::checkAccess
   */
  public function testViewAccessIsOwnOnly(): void {
    $userA = $this->makeUser('a');
    $userB = $this->makeUser('b');
    $admin = $this->makeUser('admin', ['view notification logs']);
    $notification = $this->makeNotification($userA, 'Private');

    self::assertTrue(
      $notification->access('view', $userA),
      'The owner may view their own notification.',
    );
    self::assertFalse(
      $notification->access('view', $userB),
      'A different user must NOT view another user\'s notification.',
    );
    self::assertFalse(
      $notification->access('view', User::getAnonymousUser()),
      'Anonymous must be denied.',
    );
    self::assertTrue(
      $notification->access('view', $admin),
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
