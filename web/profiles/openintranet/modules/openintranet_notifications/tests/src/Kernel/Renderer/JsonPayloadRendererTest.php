<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Renderer;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Renderer\TemplateRendererManager;
use Drupal\user\Entity\User;

/**
 * Tests the json_payload template renderer.
 *
 * @group openintranet_notifications
 */
final class JsonPayloadRendererTest extends KernelTestBase {

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
    'node',
    'openintranet_notifications',
  ];

  /**
   * The renderer plugin manager.
   */
  private TemplateRendererManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('openintranet_notification');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system']);
    $this->manager = $this->container->get('plugin.manager.notification_template_renderer');

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    User::create(['uid' => 71, 'name' => 'alice', 'status' => 1])->save();
  }

  /**
   * The payload carries the type, source entity ref and tokenised title/body.
   */
  public function testBuildsStructuredPayload(): void {
    $node = Node::create([
      'type' => 'article',
      'nid' => 99,
      'title' => 'Headline',
      'uid' => 71,
    ]);
    $node->save();

    $type = NotificationType::create([
      'id' => 'json_payload_type',
      'label' => 'JSON payload',
      'subject_template' => 'Title [node:title]',
      'body_template' => 'Body [node:title]',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('json_payload', []);
    $message = $renderer->render($type, 'webhook', [
      'node' => $node,
      'entity' => $node,
      'payload' => ['custom' => 'value'],
    ]);

    self::assertInstanceOf(NotificationMessage::class, $message);

    $payload = $message->payload;
    self::assertSame('json_payload_type', $payload['notification_type']);
    self::assertArrayHasKey('source_entity', $payload);
    self::assertSame('node', $payload['source_entity']['entity_type']);
    self::assertSame('99', (string) $payload['source_entity']['id']);
    self::assertSame('Title Headline', $payload['title']);
    self::assertSame('Body Headline', $payload['body']);
    self::assertArrayHasKey('recipient', $payload);
    // Extra payload data from the token data is merged in.
    self::assertSame('value', $payload['custom']);

    // subject/body mirror the tokenised strings.
    self::assertSame('Title Headline', $message->subject);
    self::assertSame('Body Headline', $message->body);
  }

  /**
   * The notification id is referenced when a notification object is present.
   */
  public function testReferencesNotificationId(): void {
    $type = NotificationType::create([
      'id' => 'json_payload_notif_type',
      'label' => 'JSON payload notif',
      'subject_template' => 'Subject',
      'body_template' => 'Body',
    ]);
    $type->save();

    $notification = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->create([
        'type' => 'json_payload_notif_type',
        'subject' => 'Subject',
        'body' => 'Body',
        'uid' => 71,
      ]);
    $notification->save();

    $renderer = $this->manager->createInstance('json_payload', []);
    $message = $renderer->render($type, 'webhook', ['notification' => $notification]);

    self::assertSame((string) $notification->id(), (string) $message->payload['notification_id']);
  }

  /**
   * No source entity is referenced when none is given.
   */
  public function testNoSourceEntity(): void {
    $type = NotificationType::create([
      'id' => 'json_payload_empty_type',
      'label' => 'JSON payload empty',
      'subject_template' => 'Subject',
      'body_template' => 'Body',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('json_payload', []);
    $message = $renderer->render($type, 'webhook', []);

    self::assertNull($message->payload['source_entity']);
    self::assertNull($message->payload['notification_id']);
    self::assertSame('Subject', $message->payload['title']);
  }

}
