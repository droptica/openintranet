<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Resolver;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Resolver\RecipientResolverManager;
use Drupal\user\Entity\User;

/**
 * Tests the entity_author recipient resolver.
 *
 * @group openintranet_notifications
 */
final class EntityAuthorResolverTest extends KernelTestBase {

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
    'node',
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
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->manager = $this->container->get('plugin.manager.notification_recipient_resolver');

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    foreach ([41, 42] as $uid) {
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
   * An entity owner is resolved via EntityOwnerInterface::getOwner().
   */
  public function testResolvesEntityOwner(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Test', 'uid' => 41]);
    $node->save();

    $resolver = $this->manager->createInstance('entity_author', []);
    $recipients = $resolver->resolve(['entity' => $node]);

    self::assertSame([41], $this->uids($recipients));
    self::assertTrue($recipients[0]->isUser());
    self::assertNotNull($recipients[0]->account);
  }

  /**
   * A custom entity_key is honoured.
   */
  public function testResolvesEntityOwnerFromCustomKey(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Test', 'uid' => 42]);
    $node->save();

    $resolver = $this->manager->createInstance('entity_author', ['entity_key' => 'source']);
    $recipients = $resolver->resolve(['source' => $node]);

    self::assertSame([42], $this->uids($recipients));
  }

  /**
   * A blocked author is excluded.
   */
  public function testBlockedAuthorIsExcluded(): void {
    User::load(41)->set('status', 0)->save();
    $node = Node::create(['type' => 'article', 'title' => 'Test', 'uid' => 41]);
    $node->save();

    $resolver = $this->manager->createInstance('entity_author', []);
    self::assertSame([], $resolver->resolve(['entity' => $node]));
  }

  /**
   * A missing entity in context yields no recipients.
   */
  public function testMissingEntityYieldsNoRecipients(): void {
    $resolver = $this->manager->createInstance('entity_author', []);
    self::assertSame([], $resolver->resolve([]));
    self::assertSame([], $resolver->resolve(['entity' => NULL]));
  }

}
