<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;

/**
 * Tests the notification_type add form saves a config entity (§4.1, Chunk 4A).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Form\NotificationTypeForm
 */
final class NotificationTypeFormTest extends KernelTestBase {

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
   * Submitting the add form saves a notification type with the chosen values.
   *
   * @covers ::form
   * @covers ::save
   * @covers ::create
   */
  public function testAddFormSavesType(): void {
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('openintranet_notification_type', 'add');
    $form_object->setEntity(NotificationType::create([]));

    $form_state = new FormState();
    $form_state->setValues([
      'label' => 'Mention',
      'id' => 'mention',
      'description' => 'Someone mentioned you.',
      'category' => 'social',
      'default_priority' => 'high',
      'default_channels' => ['inbox' => 'inbox', 'email_core' => 'email_core'],
      'forced_channels' => ['inbox' => 'inbox'],
      'recipient_resolvers' => "- id: entity_author\n  configuration:\n    entity_key: commented_entity\n",
      'template_renderer' => 'token_text',
      'subject_template' => 'You were mentioned by [user:name]',
      'body_template' => '[user:name] mentioned you.',
      'summary_template' => 'New mention',
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 600,
      'rate_limit' => 25,
      'rate_limit_window' => 900,
      'user_can_override' => 1,
      'audit_retention_days' => 30,
      'status' => 1,
    ]);
    // Mirror pressing the primary action: a programmatic submit has no
    // triggering element, so wire the entity-form submit handlers explicitly.
    $form_state->setSubmitHandlers(['::submitForm', '::save']);

    $this->container->get('form_builder')->submitForm($form_object, $form_state);

    self::assertEmpty($form_state->getErrors(), implode("\n", array_map('strval', $form_state->getErrors())));

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $storage->resetCache();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $saved */
    $saved = $storage->load('mention');

    self::assertNotNull($saved, 'The notification type was saved.');
    self::assertSame('Mention', $saved->label());
    self::assertSame('Someone mentioned you.', $saved->getDescription());
    self::assertSame('social', $saved->getCategory());
    self::assertSame('high', $saved->getDefaultPriority());
    self::assertSame(['inbox', 'email_core'], array_values($saved->getDefaultChannels()));
    self::assertSame(['inbox'], array_values($saved->getForcedChannels()));
    self::assertSame('token_text', $saved->getTemplateRenderer());
    self::assertSame('user_preferences', $saved->getDeliveryPolicy());
    self::assertSame('You were mentioned by [user:name]', $saved->getSubjectTemplate());
    self::assertSame('[user:name] mentioned you.', $saved->getBodyTemplate());
    self::assertSame('New mention', $saved->getSummaryTemplate());
    self::assertSame(600, $saved->getDedupeWindow());
    self::assertSame(25, $saved->getRateLimit());
    self::assertSame(900, $saved->getRateLimitWindow());
    self::assertSame(30, $saved->getAuditRetentionDays());
    self::assertTrue($saved->userCanOverride());
    self::assertTrue($saved->status());
    self::assertSame(
      [['id' => 'entity_author', 'configuration' => ['entity_key' => 'commented_entity']]],
      $saved->getRecipientResolvers(),
    );
  }

  /**
   * Invalid YAML in the recipient_resolvers textarea is rejected.
   *
   * @covers ::validateForm
   */
  public function testInvalidResolverYamlIsRejected(): void {
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('openintranet_notification_type', 'add');
    $form_object->setEntity(NotificationType::create([]));

    $form_state = new FormState();
    $form_state->setValues([
      'label' => 'Broken',
      'id' => 'broken',
      'default_priority' => 'normal',
      'default_channels' => [],
      'forced_channels' => [],
      // A scalar string is not a list of resolver mappings.
      'recipient_resolvers' => 'just a string',
      'template_renderer' => 'token_text',
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
      'audit_retention_days' => 0,
    ]);
    $form_state->setSubmitHandlers(['::submitForm', '::save']);

    $this->container->get('form_builder')->submitForm($form_object, $form_state);

    self::assertNotEmpty($form_state->getErrors(), 'Invalid resolver YAML produces a form error.');
  }

}
