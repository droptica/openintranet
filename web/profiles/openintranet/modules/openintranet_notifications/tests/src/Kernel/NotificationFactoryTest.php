<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Service\NotificationFactory;
use Drupal\user\Entity\User;

/**
 * Tests the NotificationFactory service.
 *
 * @group openintranet_notifications
 */
final class NotificationFactoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'openintranet_notifications',
    'dynamic_entity_reference',
    'options',
    'text',
    'token',
    'key',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
  ];

  /**
   * The factory under test.
   */
  private NotificationFactory $factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['openintranet_notifications']);

    NotificationType::create([
      'id' => 'default',
      'label' => 'Default',
      'default_priority' => 'normal',
      'default_channels' => ['inbox'],
    ])->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->factory = $this->container->get('openintranet_notifications.notification_factory');
  }

  /**
   * The factory builds an unsaved notification from the type and values.
   */
  public function testCreateBuildsUnsavedNotification(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Source']);
    $node->save();
    $actor = User::create(['name' => 'actor', 'status' => 1]);
    $actor->save();

    $notification = $this->factory->create('default', [
      'uid' => 42,
      'source_entity' => $node,
      'actor' => $actor,
      'payload' => ['k' => 'v'],
      'subject' => 'S',
      'body' => 'B',
    ]);

    self::assertInstanceOf(NotificationInterface::class, $notification);
    self::assertTrue($notification->isNew());
    self::assertSame('default', $notification->get('type')->value);
    self::assertSame(42, (int) $notification->get('uid')->target_id);
    self::assertSame('S', $notification->get('subject')->value);
    self::assertSame('B', $notification->get('body')->value);
    self::assertSame(['k' => 'v'], $notification->get('payload')->first()->getValue());
    self::assertSame('node', $notification->get('source_entity')->target_type);
    self::assertSame((int) $node->id(), (int) $notification->get('source_entity')->target_id);
    self::assertSame((int) $actor->id(), (int) $notification->get('actor_uid')->target_id);
    self::assertSame('created', $notification->get('status')->value);
    self::assertSame('normal', $notification->get('priority')->value);

    $key = $notification->get('dedupe_key')->value;
    self::assertNotEmpty($key);

    // The key is stable for the same inputs.
    $again = $this->factory->create('default', [
      'uid' => 42,
      'source_entity' => $node,
      'actor' => $actor,
      'payload' => ['k' => 'v'],
      'subject' => 'S',
      'body' => 'B',
    ]);
    self::assertSame($key, $again->get('dedupe_key')->value);
  }

  /**
   * The priority falls back to the type default when none is given.
   */
  public function testPriorityFallsBackToTypeDefault(): void {
    $notification = $this->factory->create('default', ['uid' => 1]);
    self::assertSame('normal', $notification->get('priority')->value);
  }

}
