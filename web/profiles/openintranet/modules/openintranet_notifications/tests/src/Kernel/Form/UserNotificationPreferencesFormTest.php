<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\NotificationType;
use Drupal\openintranet_notifications\Entity\UserNotificationSettingsInterface;
use Drupal\openintranet_notifications\Form\UserNotificationPreferencesForm;
use Drupal\user\Entity\User;

/**
 * Tests the user notification preference matrix form (§14, Chunk 5A).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Form\UserNotificationPreferencesForm
 */
final class UserNotificationPreferencesFormTest extends KernelTestBase {

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
   * The account whose preferences are edited.
   */
  private User $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_notification_settings');
    $this->installConfig(['openintranet_notifications']);

    // An overridable type with one forced channel (inbox) and one optional
    // user-facing channel (email_core).
    NotificationType::create([
      'id' => 'mention',
      'label' => 'Mention',
      'description' => 'Notifies you when someone mentions you.',
      'default_channels' => ['inbox', 'email_core'],
      'forced_channels' => ['inbox'],
      'user_can_override' => TRUE,
      'status' => TRUE,
    ])->save();

    // A non-overridable type: it must NOT appear as a row.
    NotificationType::create([
      'id' => 'system_alert',
      'label' => 'System alert',
      'default_channels' => ['inbox'],
      'forced_channels' => ['inbox'],
      'user_can_override' => FALSE,
      'status' => TRUE,
    ])->save();

    $this->account = User::create([
      'name' => 'preferer',
      'status' => 1,
    ]);
    $this->account->save();
  }

  /**
   * Builds and submits the preference form for the account.
   *
   * @param array<string, mixed> $values
   *   The form values to submit.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The processed form state.
   */
  private function submit(array $values): FormStateInterface {
    $form_object = UserNotificationPreferencesForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues($values);
    $form_state->setBuildInfo([
      'callback_object' => $form_object,
      'args' => [$this->account],
    ]);
    $this->container->get('form_builder')->submitForm($form_object, $form_state, $this->account);
    return $form_state;
  }

  /**
   * Loads the saved settings entity for the account.
   */
  private function loadSettings(): UserNotificationSettingsInterface {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('user_notification_settings');
    $storage->resetCache();
    $matches = $storage->loadByProperties(['uid' => $this->account->id()]);
    $settings = reset($matches);
    self::assertInstanceOf(UserNotificationSettingsInterface::class, $settings);
    return $settings;
  }

  /**
   * The form lists only overridable types as rows.
   *
   * @covers ::buildForm
   */
  public function testNonOverridableTypeIsAbsent(): void {
    // Build through the form builder so element defaults (e.g.
    // #description_display) are applied; building the form object directly
    // skips form_builder::prepareForm() and emits a #description_display
    // warning on render.
    $form = $this->container->get('form_builder')
      ->getForm(UserNotificationPreferencesForm::class, $this->account);

    // The overridable "Mention" type is a matrix row; the non-overridable
    // "System alert" type is not.
    self::assertArrayHasKey('mention', $form['delivery']['pref']);
    self::assertArrayNotHasKey('system_alert', $form['delivery']['pref']);
    self::assertArrayHasKey('inbox', $form['delivery']['pref']['mention']);
    self::assertArrayHasKey('email_core', $form['delivery']['pref']['mention']);
    self::assertArrayNotHasKey('log_only', $form['delivery']['pref']['mention']);

    $rendered = (string) $this->container->get('renderer')->renderRoot($form);
    self::assertStringContainsString('Mention', $rendered);
    self::assertStringContainsString('Notifies you when someone mentions you.', $rendered);
    self::assertStringContainsString('Mention via In-app', $rendered);
    self::assertStringContainsString('Mention via Email', $rendered);
    self::assertStringContainsString('Required', $rendered);
    self::assertStringContainsString('placeholder="10:00"', $rendered);
    self::assertStringContainsString('placeholder="13:00"', $rendered);
    self::assertStringContainsString('Use 24-hour time (HH:MM).', $rendered);
    self::assertStringNotContainsString('System alert', $rendered);
    self::assertStringNotContainsString('Log only', $rendered);
  }

  /**
   * Toggling a non-forced cell flips the stored preference.
   *
   * @covers ::submitForm
   */
  public function testToggledCellPersists(): void {
    $form_state = $this->submit([
      'pref' => [
        'mention' => [
          'inbox' => 1,
          'email_core' => 1,
        ],
      ],
      'quiet_hours_enabled' => FALSE,
      'quiet_hours_start' => '',
      'quiet_hours_end' => '',
      'quiet_hours_tz' => '',
    ]);
    self::assertEmpty($form_state->getErrors(), implode("\n", array_map('strval', $form_state->getErrors())));

    $preferences = $this->loadSettings()->getPreferences();
    self::assertTrue((bool) ($preferences['mention']['email_core'] ?? FALSE), 'The opted-in optional channel is stored TRUE.');
  }

  /**
   * A forced channel stays TRUE even when submitted unchecked.
   *
   * @covers ::submitForm
   */
  public function testForcedCellStaysOnWhenUnchecked(): void {
    $this->submit([
      'pref' => [
        'mention' => [
          // The forced inbox cell is submitted as unchecked (0): it must be
          // forced back on regardless.
          'inbox' => 0,
          'email_core' => 0,
        ],
      ],
      'quiet_hours_enabled' => FALSE,
      'quiet_hours_start' => '',
      'quiet_hours_end' => '',
      'quiet_hours_tz' => '',
    ]);

    $preferences = $this->loadSettings()->getPreferences();
    self::assertTrue((bool) ($preferences['mention']['inbox'] ?? FALSE), 'The forced channel cannot be opted out of.');
    self::assertFalse((bool) ($preferences['mention']['email_core'] ?? TRUE), 'The optional channel respects the unchecked value.');
  }

  /**
   * Quiet-hours fields persist to the settings entity.
   *
   * @covers ::submitForm
   */
  public function testQuietHoursPersist(): void {
    $this->submit([
      'pref' => [
        'mention' => [
          'inbox' => 1,
          'email_core' => 0,
        ],
      ],
      'quiet_hours_enabled' => TRUE,
      'quiet_hours_start' => '22:00',
      'quiet_hours_end' => '07:00',
      'quiet_hours_tz' => 'Europe/Warsaw',
    ]);

    $quiet = $this->loadSettings()->getQuietHours();
    self::assertSame('22:00', $quiet['start']);
    self::assertSame('07:00', $quiet['end']);
    self::assertSame('Europe/Warsaw', $quiet['tz']);
  }

  /**
   * A malformed quiet-hours time is rejected with a form error.
   *
   * @covers ::validateForm
   */
  public function testInvalidQuietHoursTimeIsRejected(): void {
    $form_state = $this->submit([
      'pref' => [
        'mention' => [
          'inbox' => 1,
          'email_core' => 0,
        ],
      ],
      'quiet_hours_enabled' => TRUE,
      'quiet_hours_start' => '25:99',
      'quiet_hours_end' => '',
      'quiet_hours_tz' => '',
    ]);

    $errors = $form_state->getErrors();
    self::assertArrayHasKey('quiet_hours_start', $errors, 'A malformed start time triggers a field error.');

    // The bad value must not have been persisted.
    $matches = $this->container->get('entity_type.manager')
      ->getStorage('user_notification_settings')
      ->loadByProperties(['uid' => $this->account->id()]);
    self::assertEmpty($matches, 'Nothing is saved when validation fails.');
  }

  /**
   * A well-formed quiet-hours time passes validation and persists.
   *
   * @covers ::validateForm
   */
  public function testValidQuietHoursTimePasses(): void {
    $form_state = $this->submit([
      'pref' => [
        'mention' => [
          'inbox' => 1,
          'email_core' => 0,
        ],
      ],
      'quiet_hours_enabled' => TRUE,
      'quiet_hours_start' => '09:30',
      'quiet_hours_end' => '17:45',
      'quiet_hours_tz' => '',
    ]);
    self::assertEmpty($form_state->getErrors(), implode("\n", array_map('strval', $form_state->getErrors())));

    $quiet = $this->loadSettings()->getQuietHours();
    self::assertSame('09:30', $quiet['start']);
    self::assertSame('17:45', $quiet['end']);
  }

  /**
   * Both bounds are required when quiet hours are enabled.
   *
   * @covers ::validateForm
   */
  public function testQuietHoursRequireBothTimes(): void {
    $form_state = $this->submit([
      'pref' => [
        'mention' => [
          'inbox' => 1,
          'email_core' => 0,
        ],
      ],
      'quiet_hours_enabled' => TRUE,
      'quiet_hours_start' => '22:00',
      'quiet_hours_end' => '',
      'quiet_hours_tz' => '',
    ]);

    self::assertArrayHasKey('quiet_hours_end', $form_state->getErrors());
  }

  /**
   * Equal quiet-hours bounds are rejected as an inactive window.
   *
   * @covers ::validateForm
   */
  public function testQuietHoursRequireDifferentTimes(): void {
    $form_state = $this->submit([
      'pref' => [
        'mention' => [
          'inbox' => 1,
          'email_core' => 0,
        ],
      ],
      'quiet_hours_enabled' => TRUE,
      'quiet_hours_start' => '22:00',
      'quiet_hours_end' => '22:00',
      'quiet_hours_tz' => '',
    ]);

    self::assertArrayHasKey('quiet_hours_end', $form_state->getErrors());
  }

  /**
   * Disabling quiet hours clears the complete persisted window.
   *
   * @covers ::submitForm
   */
  public function testDisablingQuietHoursClearsWindow(): void {
    $values = [
      'pref' => [
        'mention' => [
          'inbox' => 1,
          'email_core' => 0,
        ],
      ],
      'quiet_hours_enabled' => TRUE,
      'quiet_hours_start' => '22:00',
      'quiet_hours_end' => '07:00',
      'quiet_hours_tz' => 'Europe/Warsaw',
    ];
    $this->submit($values);

    $values['quiet_hours_enabled'] = FALSE;
    $form = [];
    $form_state = (new FormState())
      ->set('uid', (int) $this->account->id())
      ->setValues($values);
    UserNotificationPreferencesForm::create($this->container)
      ->submitForm($form, $form_state);

    self::assertNull($this->loadSettings()->getQuietHours());
  }

  /**
   * Preferences for diagnostic channels remain untouched when hidden.
   *
   * @covers ::submitForm
   */
  public function testHiddenChannelPreferenceIsPreserved(): void {
    $settings = $this->container
      ->get('openintranet_notifications.preference_resolver')
      ->loadOrCreateFor((int) $this->account->id());
    $settings->set('preferences', [
      'mention' => [
        'inbox' => TRUE,
        'email_core' => TRUE,
        'log_only' => TRUE,
      ],
    ])->save();

    $this->submit([
      'pref' => [
        'mention' => [
          'inbox' => 1,
          'email_core' => 0,
        ],
      ],
      'quiet_hours_enabled' => FALSE,
      'quiet_hours_start' => '',
      'quiet_hours_end' => '',
      'quiet_hours_tz' => '',
    ]);

    $preferences = $this->loadSettings()->getPreferences();
    self::assertTrue($preferences['mention']['log_only']);
    self::assertFalse($preferences['mention']['email_core']);
  }

}
