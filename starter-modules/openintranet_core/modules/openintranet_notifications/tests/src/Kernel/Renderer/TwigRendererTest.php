<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Renderer;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Renderer\TemplateRendererManager;

/**
 * Tests the twig template renderer.
 *
 * @group openintranet_notifications
 */
final class TwigRendererTest extends KernelTestBase {

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
    $this->manager = $this->container->get('plugin.manager.notification_template_renderer');
  }

  /**
   * Subject, body and summary are rendered as inline Twig with token data.
   */
  public function testRendersInlineTwig(): void {
    $type = NotificationType::create([
      'id' => 'twig_type',
      'label' => 'Twig',
      'subject_template' => 'Hi {{ name }}',
      'body_template' => '{% if vip %}VIP {% endif %}{{ name }}',
      'summary_template' => 'Sum {{ name }}',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('twig', []);
    $message = $renderer->render($type, 'email_core', ['name' => 'alice', 'vip' => TRUE]);

    self::assertInstanceOf(NotificationMessage::class, $message);
    self::assertSame('Hi alice', $message->subject);
    self::assertSame('VIP alice', $message->body);
    self::assertSame('Sum alice', $message->summary);
    self::assertSame([], $message->payload);
  }

  /**
   * An empty template renders to an empty string.
   */
  public function testEmptyTemplate(): void {
    $type = NotificationType::create([
      'id' => 'twig_empty_type',
      'label' => 'Twig empty',
      'subject_template' => 'Subject',
      'body_template' => '',
    ]);
    $type->save();

    $renderer = $this->manager->createInstance('twig', []);
    $message = $renderer->render($type, 'email_core', []);

    self::assertSame('Subject', $message->subject);
    self::assertSame('', $message->body);
    self::assertSame('', $message->summary);
  }

}
