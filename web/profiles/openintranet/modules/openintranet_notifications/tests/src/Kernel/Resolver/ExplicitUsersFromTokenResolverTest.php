<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Resolver;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Resolver\RecipientResolverManager;
use Drupal\user\Entity\User;

/**
 * Tests the explicit_users_from_token recipient resolver.
 *
 * @group openintranet_notifications
 */
final class ExplicitUsersFromTokenResolverTest extends KernelTestBase {

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

    foreach ([41, 42, 43] as $uid) {
      User::create(['uid' => $uid, 'name' => 'user' . $uid, 'status' => 1])->save();
    }
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
   * An array of uids in context resolves to one user recipient each.
   */
  public function testResolvesArrayOfUids(): void {
    $resolver = $this->manager->createInstance('explicit_users_from_token', []);
    $recipients = $resolver->resolve(['recipients' => [41, 42]]);

    self::assertCount(2, $recipients);
    self::assertSame([41, 42], $this->uids($recipients));
    foreach ($recipients as $recipient) {
      self::assertTrue($recipient->isUser());
      self::assertSame('user', $recipient->type);
      self::assertNotNull($recipient->account);
    }
  }

  /**
   * A single scalar uid in context resolves to one user recipient.
   */
  public function testResolvesSingleUid(): void {
    $resolver = $this->manager->createInstance('explicit_users_from_token', []);
    $recipients = $resolver->resolve(['recipients' => 43]);

    self::assertSame([43], $this->uids($recipients));
  }

  /**
   * Blocked and missing uids are skipped.
   */
  public function testSkipsBlockedAndMissingUsers(): void {
    User::load(42)->set('status', 0)->save();

    $resolver = $this->manager->createInstance('explicit_users_from_token', []);
    // 42 is blocked, 999 does not exist.
    $recipients = $resolver->resolve(['recipients' => [41, 42, 999]]);

    self::assertSame([41], $this->uids($recipients));
  }

  /**
   * Missing or empty context yields an empty recipient set.
   */
  public function testEmptyContextYieldsNoRecipients(): void {
    $resolver = $this->manager->createInstance('explicit_users_from_token', []);
    self::assertSame([], $resolver->resolve([]));
    self::assertSame([], $resolver->resolve(['recipients' => []]));
  }

}
