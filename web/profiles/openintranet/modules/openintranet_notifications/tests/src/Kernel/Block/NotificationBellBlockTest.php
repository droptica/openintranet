<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Block;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\user\Entity\User;

/**
 * Tests the notification bell block (§14, Chunk 5B).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Plugin\Block\NotificationBellBlock
 */
final class NotificationBellBlockTest extends KernelTestBase {

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
    'block',
    'openintranet_notifications',
  ];

  /**
   * The account whose bell is rendered.
   */
  private User $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');

    $this->account = User::create(['name' => 'recipient', 'status' => 1]);
    $this->account->save();

    $other = User::create(['name' => 'other', 'status' => 1]);
    $other->save();

    // Two unread + one read notification for the account.
    $this->createNotification($this->account->id(), 'First unread', FALSE);
    $this->createNotification($this->account->id(), 'Second unread', FALSE);
    $this->createNotification($this->account->id(), 'Already read', TRUE);
    $this->createNotification($this->account->id(), 'Cancelled audit row', FALSE, 'cancelled');

    // A notification belonging to ANOTHER user (must never leak).
    $this->createNotification($other->id(), 'Other user secret', FALSE);
  }

  /**
   * Builds the bell block plugin instance.
   *
   * @return array<string, mixed>
   *   The block build render array.
   */
  private function buildBell(): array {
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('openintranet_notification_bell');
    return $block->build();
  }

  /**
   * Creates and saves a notification for a recipient.
   */
  private function createNotification(int|string $uid, string $subject, bool $read, string $status = 'delivered'): void {
    $notification = Notification::create([
      'type' => 'mention',
      'uid' => $uid,
      'subject' => $subject,
      'url' => 'internal:/node/1',
      'priority' => 'normal',
      'status' => $status,
    ]);
    if ($read) {
      $notification->setRead();
    }
    $notification->save();
  }

  /**
   * The badge counts only the current user's unread notifications.
   *
   * @covers ::build
   */
  public function testCountIsOwnUnreadOnly(): void {
    $this->container->get('current_user')->setAccount($this->account);

    $build = $this->buildBell();

    self::assertSame(2, $build['#count']);
    self::assertSame('openintranet_notification_bell', $build['#theme']);
    self::assertContains('openintranet_notifications/notification_bell', $build['#attached']['library'], 'The dropdown toggle library is attached.');
  }

  /**
   * The dropdown lists only the current user's recent notifications.
   *
   * @covers ::build
   */
  public function testItemsAreOwnRecentOnly(): void {
    $this->container->get('current_user')->setAccount($this->account);

    $build = $this->buildBell();

    $subjects = array_map(static fn (array $item): string => (string) $item['subject'], $build['#items']);
    self::assertContains('First unread', $subjects);
    self::assertContains('Second unread', $subjects);
    self::assertContains('Already read', $subjects);
    self::assertNotContains('Cancelled audit row', $subjects);
    self::assertNotContains('Other user secret', $subjects);
    self::assertCount(3, $build['#items']);
    // Each item carries the expected keys.
    foreach ($build['#items'] as $item) {
      self::assertArrayHasKey('subject', $item);
      self::assertArrayHasKey('url', $item);
      self::assertArrayHasKey('created_ago', $item);
    }
  }

  /**
   * The render array is cached per user and tagged for list invalidation.
   *
   * @covers ::build
   */
  public function testCacheMetadata(): void {
    $this->container->get('current_user')->setAccount($this->account);

    $build = $this->buildBell();

    self::assertContains('user', $build['#cache']['contexts']);
    self::assertContains('openintranet_notification_list', $build['#cache']['tags']);
  }

  /**
   * An anonymous user gets an empty render with no notification data.
   *
   * @covers ::build
   */
  public function testAnonymousGetsEmptyRender(): void {
    // The kernel test bootstraps with the anonymous user by default.
    $build = $this->buildBell();

    self::assertArrayNotHasKey('#count', $build);
    self::assertArrayNotHasKey('#items', $build);
    self::assertArrayNotHasKey('#theme', $build);
    // The empty render still varies by user so it is never reused across users.
    self::assertContains('user', $build['#cache']['contexts']);
  }

  /**
   * The dropdown is capped at RECENT_LIMIT and ordered newest first.
   *
   * @covers ::build
   */
  public function testItemsAreCappedAndNewestFirst(): void {
    $capped = User::create(['name' => 'capped', 'status' => 1]);
    $capped->save();
    $this->container->get('current_user')->setAccount($capped);

    // Create more than RECENT_LIMIT (10) own notifications. They share a
    // created timestamp, so the id DESC tiebreaker decides the order.
    for ($i = 0; $i < 15; $i++) {
      $this->createNotification($capped->id(), 'Item ' . $i, FALSE);
    }

    $build = $this->buildBell();

    self::assertCount(10, $build['#items'], 'The dropdown is capped at RECENT_LIMIT.');
    // The newest (highest id, last created: "Item 14") is first; the 10th item
    // is "Item 5" (15 items, newest 10 kept).
    self::assertSame('Item 14', $build['#items'][0]['subject']);
    self::assertSame('Item 5', $build['#items'][9]['subject']);
  }

  /**
   * The build renders through Twig (the #theme hook resolves to a template).
   *
   * Asserting the build array alone never asks Twig to resolve '#theme' to a
   * file, so a hook-name vs template-filename mismatch only surfaces on a real
   * render (it crashed the live site with a Twig LoaderError). This guards it.
   *
   * @covers ::build
   */
  public function testRendersThroughTwig(): void {
    $this->container->get('current_user')->setAccount($this->account);

    $build = $this->buildBell();
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    self::assertStringContainsString('notification-bell__toggle', $html);
    self::assertStringContainsString('notification-bell__badge', $html, 'The unread count badge is rendered.');
  }

  /**
   * The badge is always rendered, showing a muted "0" when nothing is unread.
   */
  public function testEmptyBadgeRendersZeroState(): void {
    $empty = User::create(['name' => 'empty', 'status' => 1]);
    $empty->save();
    $this->container->get('current_user')->setAccount($empty);

    $build = $this->buildBell();
    self::assertSame(0, $build['#count']);

    $html = (string) $this->container->get('renderer')->renderRoot($build);
    self::assertStringContainsString('notification-bell__badge', $html, 'The badge renders even at zero.');
    self::assertStringContainsString('notification-bell__badge--empty', $html, 'The zero state gets the muted modifier.');
  }

  /**
   * Bell items link to the canonical view so clicking one marks it read.
   *
   * The bell points at /notifications/{id} (which marks read, then redirects to
   * any target) rather than straight to the target URL — otherwise opening a
   * notification from the bell would never clear it from the unread count.
   */
  public function testItemsLinkToCanonicalView(): void {
    $this->container->get('current_user')->setAccount($this->account);

    $build = $this->buildBell();

    self::assertNotEmpty($build['#items']);
    $url = $build['#items'][0]['url'];
    self::assertInstanceOf(Url::class, $url);
    self::assertSame('entity.openintranet_notification.canonical', $url->getRouteName());
  }

  /**
   * The "see all" link is built from the inbox route, not a hardcoded path.
   */
  public function testSeeAllUrlUsesInboxRoute(): void {
    $this->container->get('current_user')->setAccount($this->account);

    $url = $this->buildBell()['#see_all_url'];
    self::assertInstanceOf(Url::class, $url);
    self::assertSame('openintranet_notifications.inbox', $url->getRouteName());
  }

  /**
   * The item carries the actor's display name (LinkedIn-style author).
   */
  public function testItemCarriesActorName(): void {
    $author = User::create(['name' => 'Jane Author', 'status' => 1]);
    $author->save();
    $notification = Notification::create([
      'type' => 'mention',
      'uid' => $this->account->id(),
      'subject' => 'With actor',
      'actor_uid' => $author->id(),
      'priority' => 'normal',
      'status' => 'delivered',
    ]);
    $notification->save();
    $this->container->get('current_user')->setAccount($this->account);

    $names = array_map(
      static fn (array $item): ?string => $item['actor_name'],
      $this->buildBell()['#items'],
    );
    self::assertContains('Jane Author', $names);
  }

}
