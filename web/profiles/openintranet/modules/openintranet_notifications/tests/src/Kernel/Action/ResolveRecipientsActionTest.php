<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Action;

use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the resolve_recipients ECA action.
 *
 * @group openintranet_notifications
 */
final class ResolveRecipientsActionTest extends NotificationActionKernelTestBase {

  /**
   * A single resolver id + configuration writes the uid list to a token.
   */
  public function testResolverIdWritesUidsToToken(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    foreach ([41, 42] as $uid) {
      $user = User::load($uid);
      $user->addRole('editor');
      $user->save();
    }

    $action = $this->actionManager->createInstance('openintranet_notifications_resolve_recipients', [
      'resolver_id' => 'role_users',
      'configuration' => ['role' => 'editor'],
      'token_name' => 'resolved_uids',
    ]);

    $action->execute(NULL);

    self::assertSame([41, 42], $this->readUidToken('resolved_uids'));
  }

  /**
   * With no resolver id, the type's own resolvers run against the context.
   */
  public function testTypeResolversRunAgainstSourceEntity(): void {
    NotificationType::create([
      'id' => 'author',
      'label' => 'Author',
      'recipient_resolvers' => [
        ['id' => 'entity_author', 'configuration' => []],
      ],
    ])->save();

    $node = $this->createArticle(43);

    $action = $this->actionManager->createInstance('openintranet_notifications_resolve_recipients', [
      'notification_type' => 'author',
      'token_name' => 'resolved_uids',
    ]);

    $action->execute($node);

    self::assertSame([43], $this->readUidToken('resolved_uids'));
  }

}
