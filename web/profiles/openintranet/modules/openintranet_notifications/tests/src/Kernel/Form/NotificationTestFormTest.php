<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Form\NotificationTestForm;
use Drupal\user\Entity\User;

/**
 * Tests the test/preview/dry-run send form (Chunk 4D).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Form\NotificationTestForm
 */
final class NotificationTestFormTest extends KernelTestBase {

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
   * The target recipient user.
   */
  private User $recipient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installEntitySchema('openintranet_notification');
    $this->installEntitySchema('openintranet_notif_delivery');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['openintranet_notifications']);

    // Replace the shipped type with a fixture whose subject_template uses a
    // user token so the dry-run preview asserts real rendering, delivering on
    // inbox/log_only so no real send happens on a live submit.
    $typeStorage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification_type');
    $typeStorage->delete($typeStorage->loadMultiple());
    NotificationType::create([
      'id' => 'mention',
      'label' => 'Mention',
      'default_channels' => ['inbox', 'log_only'],
      'forced_channels' => ['inbox', 'log_only'],
      'template_renderer' => 'token_text',
      'subject_template' => 'Hi [user:display-name]',
      'body_template' => 'You have a mention.',
      'delivery_policy' => 'user_preferences',
      'dedupe_window' => 0,
    ])->save();

    $this->config('openintranet_notifications.settings')
      ->set('enabled_channels', ['inbox', 'log_only'])
      ->save();

    User::create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();
    $this->recipient = User::create(['name' => 'alice', 'status' => 1]);
    $this->recipient->save();
  }

  /**
   * Submits the form programmatically.
   *
   * @param bool $dryRun
   *   Whether to run in dry-run mode.
   *
   * @return \Drupal\Core\Form\FormState
   *   The submitted form state (carries the preview result via storage).
   */
  private function submit(bool $dryRun): FormState {
    $form_object = NotificationTestForm::create($this->container);
    $form_state = new FormState();
    // A checkbox with a TRUE #default_value cannot be un-checked by submitting
    // FALSE (the value callback would fall back to the default). A programmatic
    // submit must instead pass an explicit NULL to represent an unchecked box
    // (see FormBuilder::handleInputElement + Checkbox::valueCallback).
    $form_state->setValues([
      'notification_type' => 'mention',
      'uid' => (int) $this->recipient->id(),
      'channel' => '',
      'dry_run' => $dryRun ? 1 : NULL,
    ]);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    return $form_state;
  }

  /**
   * Counts the rows of an entity type.
   */
  private function countEntities(string $entityTypeId): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage($entityTypeId)
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  /**
   * Dry-run renders a preview and persists nothing.
   *
   * @covers ::submitForm
   */
  public function testDryRunCreatesNothingAndPreviewsRenderedSubject(): void {
    $form_state = $this->submit(TRUE);

    self::assertEmpty($form_state->getErrors(), implode("\n", array_map('strval', $form_state->getErrors())));

    // Nothing is persisted and nothing is enqueued.
    self::assertSame(0, $this->countEntities('openintranet_notification'));
    self::assertSame(0, $this->countEntities('openintranet_notif_delivery'));
    self::assertSame(0, \Drupal::queue('openintranet_notification_delivery')->numberOfItems());

    // The preview carries the rendered subject (token resolved against the
    // recipient) and the channels the policy would select.
    $preview = $form_state->get('preview');
    self::assertIsArray($preview);
    self::assertSame('Hi alice', $preview['subject']);
    self::assertContains('inbox', $preview['channels']);
    self::assertContains('log_only', $preview['channels']);
  }

  /**
   * A live submit creates a notification and its deliveries for the user.
   *
   * @covers ::submitForm
   */
  public function testLiveSubmitCreatesNotificationAndDeliveries(): void {
    $form_state = $this->submit(FALSE);

    self::assertEmpty($form_state->getErrors(), implode("\n", array_map('strval', $form_state->getErrors())));

    self::assertSame(1, $this->countEntities('openintranet_notification'));
    // One delivery per selected channel (inbox + log_only).
    self::assertSame(2, $this->countEntities('openintranet_notif_delivery'));

    $notifications = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->loadMultiple();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface $notification */
    $notification = reset($notifications);
    self::assertSame((int) $this->recipient->id(), (int) $notification->get('uid')->target_id);
    self::assertSame('Hi alice', (string) $notification->get('subject')->value);
  }

}
