<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Form\NotificationSettingsForm;

/**
 * Tests the global notification settings form (§ settings, Chunk 4F).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Form\NotificationSettingsForm
 */
final class NotificationSettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['openintranet_notifications']);
  }

  /**
   * Submitting the form writes managed keys and preserves unmanaged ones.
   *
   * @covers ::buildForm
   * @covers ::submitForm
   * @covers ::create
   */
  public function testSubmitWritesManagedKeysAndPreservesOthers(): void {
    $form_object = NotificationSettingsForm::create($this->container);

    $form_state = new FormState();
    // Checkboxes submit only checked keys (key => key); unchecked are absent.
    $form_state->setValues([
      'enabled_channels' => ['inbox' => 'inbox'],
      'enabled_types' => ['default' => 'default'],
      'retention_default_days' => 30,
      'kill_switch' => ['log_only' => 'log_only'],
    ]);

    $this->container->get('form_builder')->submitForm($form_object, $form_state);

    self::assertEmpty(
      $form_state->getErrors(),
      implode("\n", array_map('strval', $form_state->getErrors())),
    );

    $config = $this->config('openintranet_notifications.settings');

    self::assertSame(['inbox'], $config->get('enabled_channels'));
    self::assertSame(['default'], $config->get('enabled_types'));
    self::assertSame(30, $config->get('retention.default_days'));

    $kill_switch = $config->get('kill_switch');
    self::assertTrue($kill_switch['log_only']);
    self::assertFalse($kill_switch['inbox']);

    // Unmanaged keys must survive the write untouched: the shipped
    // default_user_preferences map (a row per type) is not a form-managed key.
    self::assertSame('openintranet_notification_delivery', $config->get('queue.id'));
    self::assertSame(
      [
        'default' => ['inbox' => TRUE, 'email_core' => TRUE, 'log_only' => FALSE],
        'new_comment' => ['inbox' => TRUE, 'email_core' => TRUE],
      ],
      $config->get('default_user_preferences'),
    );
  }

  /**
   * The form pre-populates from the installed config defaults.
   *
   * @covers ::buildForm
   */
  public function testBuildFormReflectsCurrentConfig(): void {
    $form_object = NotificationSettingsForm::create($this->container);
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($form_object, $form_state);

    self::assertSame(
      ['inbox', 'email_core', 'log_only'],
      array_values($form['enabled_channels']['#default_value']),
    );
    self::assertSame(['default', 'new_comment'], array_values($form['enabled_types']['#default_value']));
    self::assertSame(90, $form['retention_default_days']['#default_value']);
  }

}
