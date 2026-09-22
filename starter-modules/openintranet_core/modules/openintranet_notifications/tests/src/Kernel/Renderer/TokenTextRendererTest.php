<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Renderer;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Renderer\TemplateRendererManager;
use Drupal\user\Entity\User;

/**
 * Tests the token_text template renderer.
 *
 * @group openintranet_notifications
 */
final class TokenTextRendererTest extends KernelTestBase {

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
    // The user token set renders date tokens (last-login, created), which need
    // the default date formats installed.
    $this->installConfig(['system']);
    $this->manager = $this->container->get('plugin.manager.notification_template_renderer');
  }

  /**
   * Subject, body and summary are token-replaced from the type templates.
   */
  public function testRendersTemplatesWithTokens(): void {
    $user = User::create(['uid' => 51, 'name' => 'alice', 'status' => 1]);
    $user->save();

    $type = NotificationType::create([
      'id' => 'token_text_type',
      'label' => 'Token text',
      'subject_template' => 'Hi [user:name]',
      'body_template' => 'Body for [user:name]',
      'summary_template' => 'Summary [user:name]',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('token_text', []);
    $message = $renderer->render($type, 'email_core', ['user' => $user]);

    self::assertInstanceOf(NotificationMessage::class, $message);
    self::assertSame('Hi alice', $message->subject);
    self::assertSame('Body for alice', $message->body);
    self::assertSame('Summary alice', $message->summary);
    self::assertSame([], $message->payload);
  }

  /**
   * The per-channel template map overrides the body template for a channel.
   */
  public function testTemplateMapOverridesBody(): void {
    $user = User::create(['uid' => 52, 'name' => 'bob', 'status' => 1]);
    $user->save();

    $type = NotificationType::create([
      'id' => 'token_text_map_type',
      'label' => 'Token text map',
      'subject_template' => 'Subject [user:name]',
      'body_template' => 'Default body',
      'template_map' => [
        'sms_core' => 'SMS body for [user:name]',
      ],
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('token_text', []);

    $sms = $renderer->render($type, 'sms_core', ['user' => $user]);
    self::assertSame('SMS body for bob', $sms->body);

    // A channel without a map entry falls back to the body template.
    $other = $renderer->render($type, 'email_core', ['user' => $user]);
    self::assertSame('Default body', $other->body);
  }

  /**
   * Unreplaced tokens are cleared.
   */
  public function testUnknownTokensAreCleared(): void {
    $type = NotificationType::create([
      'id' => 'token_text_clear_type',
      'label' => 'Token text clear',
      'subject_template' => 'Subject [user:name]',
      'body_template' => 'Body',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('token_text', []);
    $message = $renderer->render($type, 'email_core', []);

    self::assertSame('Subject ', $message->subject);
  }

}
