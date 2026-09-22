<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Resolver;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Resolver\RecipientResolverManager;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the role_users recipient resolver.
 *
 * @group openintranet_notifications
 */
final class RoleUsersResolverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'dynamic_entity_reference',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'views',
    'openintranet_notifications',
  ];

  /**
   * The resolver plugin manager.
   */
  private RecipientResolverManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->manager = $this->container->get('plugin.manager.notification_recipient_resolver');

    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();

    // 41: active editor, 42: blocked editor, 43: active non-editor.
    User::create(['uid' => 41, 'name' => 'u41', 'status' => 1, 'roles' => ['editor']])->save();
    User::create(['uid' => 42, 'name' => 'u42', 'status' => 0, 'roles' => ['editor']])->save();
    User::create(['uid' => 43, 'name' => 'u43', 'status' => 1])->save();
  }

  /**
   * Extracts the sorted uid set from a list of recipients.
   *
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient[] $recipients
   *   The recipients.
   *
   * @return int[]
   *   The sorted uids.
   */
  private function uids(array $recipients): array {
    $uids = array_map(static fn (NotificationRecipient $r): int => (int) $r->id, $recipients);
    sort($uids);
    return $uids;
  }

  /**
   * Only active users with the configured role are resolved.
   */
  public function testResolvesActiveUsersWithRole(): void {
    $resolver = $this->manager->createInstance('role_users', ['role' => 'editor']);
    $recipients = $resolver->resolve([]);

    self::assertSame([41], $this->uids($recipients));
    self::assertTrue($recipients[0]->isUser());
    self::assertNotNull($recipients[0]->account);
  }

  /**
   * A role with no active members yields no recipients.
   */
  public function testRoleWithNoActiveMembersYieldsNoRecipients(): void {
    Role::create(['id' => 'reviewer', 'label' => 'Reviewer'])->save();
    $resolver = $this->manager->createInstance('role_users', ['role' => 'reviewer']);
    self::assertSame([], $resolver->resolve([]));
  }

  /**
   * Missing role config yields no recipients.
   */
  public function testMissingRoleConfigYieldsNoRecipients(): void {
    $resolver = $this->manager->createInstance('role_users', []);
    self::assertSame([], $resolver->resolve([]));
  }

}
