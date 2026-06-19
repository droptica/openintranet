<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Event\NotificationCreatedEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the create_and_enqueue ECA action.
 *
 * @group openintranet_notifications
 */
final class CreateAndEnqueueActionTest extends NotificationActionKernelTestBase {

  /**
   * Explicit recipients produce one notification + deliveries + queue items.
   */
  public function testExplicitRecipientsFanOut(): void {
    $node = $this->createArticle(41);

    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'default',
      'recipients' => '[recipients]',
    ]);
    $this->tokenServices->addTokenData('recipients', [41, 42, 43]);

    $action->execute($node);

    $notifications = $this->loadAllNotifications();
    self::assertCount(3, $notifications, 'One notification per recipient.');

    $uids = array_map(static fn ($n) => (int) $n->get('uid')->target_id, $notifications);
    sort($uids);
    self::assertSame([41, 42, 43], $uids);

    // Each notification carries the node as its source entity and an actor.
    foreach ($notifications as $notification) {
      self::assertSame('node', $notification->get('source_entity')->target_type);
      self::assertSame((string) $node->id(), (string) $notification->get('source_entity')->target_id);
      self::assertSame('queued', $notification->get('status')->value);
      self::assertCount(2, $this->loadDeliveriesFor((int) $notification->id()));
    }

    // Two channels (inbox, log_only) per recipient = 6 queue items.
    self::assertSame(6, $this->queueCount());
  }

  /**
   * An empty recipients token lets the type's resolvers run.
   */
  public function testEmptyRecipientsUsesTypeResolvers(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    foreach ([41, 42] as $uid) {
      $user = User::load($uid);
      $user->addRole('editor');
      $user->save();
    }

    NotificationType::create([
      'id' => 'resolved',
      'label' => 'Resolved',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'recipient_resolvers' => [
        ['id' => 'role_users', 'configuration' => ['role' => 'editor']],
      ],
    ])->save();

    $node = $this->createArticle(41);

    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'resolved',
      'recipients' => '',
    ]);

    $action->execute($node);

    $notifications = $this->loadAllNotifications();
    self::assertCount(2, $notifications);
    $uids = array_map(static fn ($n) => (int) $n->get('uid')->target_id, $notifications);
    sort($uids);
    self::assertSame([41, 42], $uids);
  }

  /**
   * The entity_author resolver receives the source entity under 'entity'.
   */
  public function testEntityAuthorResolverReceivesSourceEntity(): void {
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

    $node = $this->createArticle(42);

    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'author',
      'recipients' => '',
    ]);

    $action->execute($node);

    $notifications = $this->loadAllNotifications();
    self::assertCount(1, $notifications);
    $notification = reset($notifications);
    self::assertSame(42, (int) $notification->get('uid')->target_id);
  }

  /**
   * A configured context token reaches the type's resolver.
   *
   * The context map injects the node (resolved from a token, preserving the
   * entity object) under 'commented_entity'; entity_author keyed on that runs
   * against it and resolves the node's author.
   */
  public function testContextTokenReachesResolver(): void {
    NotificationType::create([
      'id' => 'commented',
      'label' => 'Commented',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'recipient_resolvers' => [
        ['id' => 'entity_author', 'configuration' => ['entity_key' => 'commented_entity']],
      ],
    ])->save();

    // The action's primary object is a different entity; only the context map
    // carries the node whose author must be resolved.
    $node = $this->createArticle(43);
    $this->tokenServices->addTokenData('node', $node);

    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'commented',
      'recipients' => '',
      'context' => ['commented_entity' => '[node]'],
    ]);

    $action->execute(NULL);

    $notifications = $this->loadAllNotifications();
    self::assertCount(1, $notifications);
    $notification = reset($notifications);
    self::assertSame(43, (int) $notification->get('uid')->target_id, 'The resolver saw the node from the context token map.');
  }

  /**
   * Created/queued events fire once per recipient.
   */
  public function testEventsFirePerRecipient(): void {
    $created = 0;
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(NotificationEvents::CREATED, function (NotificationCreatedEvent $event) use (&$created): void {
      $created++;
    });

    $node = $this->createArticle(41);
    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'default',
      'recipients' => '[recipients]',
    ]);
    $this->tokenServices->addTokenData('recipients', [41, 42]);

    $action->execute($node);

    self::assertSame(2, $created);
  }

  /**
   * The action is allowed by default (access returns TRUE).
   */
  public function testAccessAllowedByDefault(): void {
    $action = $this->actionManager->createInstance('openintranet_notifications_create_and_enqueue', [
      'notification_type' => 'default',
      'recipients' => '[recipients]',
    ]);
    self::assertTrue($action->access(NULL));
  }

}
