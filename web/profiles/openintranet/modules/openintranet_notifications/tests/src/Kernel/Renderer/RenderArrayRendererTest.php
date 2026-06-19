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
 * Tests the render_array template renderer.
 *
 * @group openintranet_notifications
 */
final class RenderArrayRendererTest extends KernelTestBase {

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
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter']);
    $this->manager = $this->container->get('plugin.manager.notification_template_renderer');

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    User::create(['uid' => 61, 'name' => 'alice', 'status' => 1])->save();
  }

  /**
   * The entity is rendered to markup for the body; the subject is tokenised.
   */
  public function testRendersEntityToBody(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'My headline',
      'uid' => 61,
    ]);
    $node->save();

    $type = NotificationType::create([
      'id' => 'render_array_type',
      'label' => 'Render array',
      'subject_template' => 'New: [node:title]',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('render_array', []);
    $message = $renderer->render($type, 'email_core', ['entity' => $node, 'node' => $node]);

    self::assertInstanceOf(NotificationMessage::class, $message);
    self::assertSame('New: My headline', $message->subject);
    // The default 'full' view mode renders the node body markup (the title is
    // the page heading, not part of the entity build), so assert the rendered
    // author submission line is present.
    self::assertStringContainsString('<article', $message->body);
    self::assertStringContainsString('alice', $message->body);
  }

  /**
   * A configured view_mode is honoured (the teaser links the title).
   */
  public function testHonoursViewMode(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Teaser headline',
      'uid' => 61,
    ]);
    $node->save();

    $type = NotificationType::create([
      'id' => 'render_array_teaser_type',
      'label' => 'Render array teaser',
      'subject_template' => 'Subject',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('render_array', ['view_mode' => 'teaser']);
    $message = $renderer->render($type, 'email_core', ['entity' => $node]);

    self::assertStringContainsString('Teaser headline', $message->body);
  }

  /**
   * A missing entity yields an empty body.
   */
  public function testMissingEntityYieldsEmptyBody(): void {
    $type = NotificationType::create([
      'id' => 'render_array_empty_type',
      'label' => 'Render array empty',
      'subject_template' => 'Subject',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('render_array', []);
    $message = $renderer->render($type, 'email_core', []);

    self::assertSame('Subject', $message->subject);
    self::assertSame('', $message->body);
  }

}
