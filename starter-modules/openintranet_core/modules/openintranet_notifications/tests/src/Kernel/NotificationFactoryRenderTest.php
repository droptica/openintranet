<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Service\NotificationFactory;
use Drupal\user\Entity\User;

/**
 * Tests the json_payload and render_array renderers through the real factory.
 *
 * The renderer unit tests hand-build the token data (including the generic
 * 'entity' key the renderers read). These tests exercise the production path —
 * NotificationFactory::create() builds the token data — so they catch the case
 * where the factory fails to expose the source entity / payload to the renderer
 * (the empty-render blocker, FIX 2).
 *
 * @group openintranet_notifications
 */
final class NotificationFactoryRenderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'filter',
    'options',
    'text',
    'token',
    'key',
    'language',
    'content_translation',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'dynamic_entity_reference',
    'openintranet_notifications',
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
    $this->installEntitySchema('openintranet_notification');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter', 'openintranet_notifications']);

    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    User::create(['uid' => 71, 'name' => 'alice', 'status' => 1])->save();

    $this->factory = $this->container->get('openintranet_notifications.notification_factory');
  }

  /**
   * The json_payload renderer sees the source entity and payload via create().
   *
   * The factory must expose the source entity under the generic 'entity' key
   * the renderer reads, and surface the caller payload from the context.
   */
  public function testJsonPayloadRendersSourceEntityAndPayload(): void {
    NotificationType::create([
      'id' => 'json_type',
      'label' => 'JSON',
      'template_renderer' => 'json_payload',
      'subject_template' => 'Title [node:title]',
      'body_template' => 'Body [node:title]',
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Headline', 'uid' => 71]);
    $node->save();

    $notification = $this->factory->create('json_type', [
      'uid' => 71,
      'source_entity' => $node,
      'context' => ['payload' => ['custom' => 'value']],
    ]);

    // The subject/body render the source title: the factory token data carried
    // the source under [node], and the caller payload under 'payload'.
    self::assertSame('Title Headline', (string) $notification->get('subject')->value);
    self::assertSame('Body Headline', (string) $notification->get('body')->value, 'The body is not empty: the source entity reached the renderer.');
  }

  /**
   * The render_array renderer renders the source entity body via create().
   *
   * The render_array renderer reads the generic 'entity' key; before FIX 2 the
   * factory never set it, so the body came back empty.
   */
  public function testRenderArrayRendersSourceEntityBody(): void {
    NotificationType::create([
      'id' => 'render_type',
      'label' => 'Render',
      'template_renderer' => 'render_array',
      'subject_template' => 'New: [node:title]',
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'My headline', 'uid' => 71]);
    $node->save();

    $notification = $this->factory->create('render_type', [
      'uid' => 71,
      'source_entity' => $node,
    ]);

    self::assertSame('New: My headline', (string) $notification->get('subject')->value);
    // render_array renders the node through its view builder: the body must be
    // non-empty markup referencing the author (empty before FIX 2).
    $body = (string) $notification->get('body')->value;
    self::assertNotSame('', $body, 'The render_array body is not empty: the source entity reached the renderer.');
    self::assertStringContainsString('alice', $body, 'The rendered node markup contains the author submission line.');
  }

  /**
   * The factory renders [node:title] in the recipient's preferred langcode.
   *
   * §9: we respect preferred_langcode. The recipient account carries the
   * langcode; the factory must thread it into the token replacement so a
   * translated source field resolves in the recipient's language, not the
   * site default.
   */
  public function testRendersInRecipientPreferredLangcode(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    NotificationType::create([
      'id' => 'lang_type',
      'label' => 'Lang',
      'template_renderer' => 'token_text',
      'subject_template' => '[node:title]',
      'body_template' => '[node:title]',
    ])->save();

    // English source title with a French translation.
    $node = Node::create([
      'type' => 'article',
      'title' => 'English title',
      'uid' => 71,
      'langcode' => 'en',
    ]);
    $node->save();
    $node->addTranslation('fr', ['title' => 'Titre francais'])->save();

    // A recipient whose preferred langcode is French.
    $recipient = User::create([
      'uid' => 72,
      'name' => 'pierre',
      'status' => 1,
      'preferred_langcode' => 'fr',
    ]);
    $recipient->save();

    $notification = $this->factory->create('lang_type', [
      'uid' => 72,
      'recipient_account' => $recipient,
      'source_entity' => $node,
    ]);

    self::assertSame('Titre francais', (string) $notification->get('subject')->value, 'The subject resolved [node:title] in the recipient French translation.');
    self::assertSame('Titre francais', (string) $notification->get('body')->value, 'The body resolved [node:title] in the recipient French translation.');
  }

}
