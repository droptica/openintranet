<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Resolver;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Resolver\RecipientResolverManager;
use Drupal\user\Entity\User;

/**
 * Tests the entity_field_users recipient resolver.
 *
 * @group openintranet_notifications
 */
final class EntityFieldUsersResolverTest extends KernelTestBase {

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

    FieldStorageConfig::create([
      'field_name' => 'field_watchers',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'user'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_watchers',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

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
   * Each user referenced by the field becomes a recipient.
   */
  public function testResolvesReferencedUsers(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test',
      'uid' => 41,
      'field_watchers' => [['target_id' => 42], ['target_id' => 43]],
    ]);
    $node->save();

    $resolver = $this->manager->createInstance('entity_field_users', ['field_name' => 'field_watchers']);
    $recipients = $resolver->resolve(['entity' => $node]);

    self::assertSame([42, 43], $this->uids($recipients));
    foreach ($recipients as $recipient) {
      self::assertTrue($recipient->isUser());
      self::assertNotNull($recipient->account);
    }
  }

  /**
   * Blocked referenced users are excluded.
   */
  public function testBlockedReferencedUsersExcluded(): void {
    User::load(42)->set('status', 0)->save();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test',
      'uid' => 41,
      'field_watchers' => [['target_id' => 42], ['target_id' => 43]],
    ]);
    $node->save();

    $resolver = $this->manager->createInstance('entity_field_users', ['field_name' => 'field_watchers']);
    self::assertSame([43], $this->uids($resolver->resolve(['entity' => $node])));
  }

  /**
   * A custom entity_key is honoured.
   */
  public function testCustomEntityKey(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test',
      'uid' => 41,
      'field_watchers' => [['target_id' => 42]],
    ]);
    $node->save();

    $resolver = $this->manager->createInstance('entity_field_users', [
      'entity_key' => 'source',
      'field_name' => 'field_watchers',
    ]);
    self::assertSame([42], $this->uids($resolver->resolve(['source' => $node])));
  }

  /**
   * A missing entity or empty field yields no recipients.
   */
  public function testMissingEntityOrFieldYieldsNoRecipients(): void {
    $resolver = $this->manager->createInstance('entity_field_users', ['field_name' => 'field_watchers']);
    self::assertSame([], $resolver->resolve([]));

    $node = Node::create(['type' => 'article', 'title' => 'Empty', 'uid' => 41]);
    $node->save();
    self::assertSame([], $resolver->resolve(['entity' => $node]));

    // Missing field_name config.
    $resolver2 = $this->manager->createInstance('entity_field_users', []);
    self::assertSame([], $resolver2->resolve(['entity' => $node]));
  }

}
