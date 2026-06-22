<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Service\PreferenceResolverInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The per-user notification preference matrix (type x channel, §14 Chunk 5A).
 *
 * Rows are overridable notification types; columns are the globally enabled
 * channels. Forced channels render disabled + checked and are always saved on.
 * Access is enforced by NotificationPreferencesAccess on the route.
 */
final class UserNotificationPreferencesForm extends FormBase {

  private const SETTINGS = 'openintranet_notifications.settings';

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The preference resolver.
   */
  private PreferenceResolverInterface $preferenceResolver;

  /**
   * The channel plugin manager.
   */
  private ChannelPluginManager $channelManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->preferenceResolver = $container->get(PreferenceResolverInterface::class);
    $instance->channelManager = $container->get('plugin.manager.notification_channel');
    $instance->setConfigFactory($container->get('config.factory'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_notifications_user_preferences';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    if ($user === NULL) {
      throw new \InvalidArgumentException('A user is required to build the preference form.');
    }
    $form_state->set('uid', (int) $user->id());

    $channels = $this->channelColumns();
    $types = $this->overridableTypes();
    $uid = (int) $user->id();

    $header = [$this->t('Notification type')];
    foreach ($channels as $label) {
      $header[] = $label;
    }

    $form['pref'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No notification types are available to configure.'),
    ];

    foreach ($types as $type_id => $type) {
      $forced = array_flip($type->getForcedChannels());
      $row = [];
      $row['label'] = [
        '#plain_text' => (string) $type->label(),
      ];
      foreach ($channels as $channel_id => $channel_label) {
        $is_forced = isset($forced[$channel_id]);
        $row[$channel_id] = [
          '#type' => 'checkbox',
          '#title' => $channel_label,
          '#title_display' => 'invisible',
          '#default_value' => $is_forced ? TRUE : $this->preferenceResolver->isEnabled($uid, $type_id, $channel_id),
          '#disabled' => $is_forced,
        ];
      }
      $form['pref'][$type_id] = $row;
    }

    $quiet = $this->preferenceResolver->getQuietHours($uid) ?? [];
    $form['quiet_hours'] = [
      '#type' => 'details',
      '#title' => $this->t('Quiet hours'),
      '#open' => TRUE,
    ];
    $form['quiet_hours']['quiet_hours_start'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start'),
      '#description' => $this->t('Local start of the quiet window, 24h HH:MM.'),
      '#size' => 5,
      '#maxlength' => 5,
      '#attributes' => ['type' => 'time'],
      '#default_value' => $quiet['start'] ?? '',
    ];
    $form['quiet_hours']['quiet_hours_end'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End'),
      '#description' => $this->t('Local end of the quiet window, 24h HH:MM.'),
      '#size' => 5,
      '#maxlength' => 5,
      '#attributes' => ['type' => 'time'],
      '#default_value' => $quiet['end'] ?? '',
    ];
    $form['quiet_hours']['quiet_hours_tz'] = [
      '#type' => 'select',
      '#title' => $this->t('Timezone'),
      '#options' => $this->timezoneOptions(),
      '#empty_option' => $this->t('- Site default -'),
      '#default_value' => $quiet['tz'] ?? '',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save preferences'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->get('uid');
    $settings = $this->preferenceResolver->loadOrCreateFor($uid);

    $channels = array_keys($this->channelColumns());
    $submitted = (array) $form_state->getValue('pref');

    $preferences = $settings->getPreferences();
    foreach ($this->overridableTypes() as $type_id => $type) {
      $forced = array_flip($type->getForcedChannels());
      foreach ($channels as $channel_id) {
        // Forced channels are always on; the rest take the checkbox value.
        $preferences[$type_id][$channel_id] = isset($forced[$channel_id])
          ? TRUE
          : (bool) ($submitted[$type_id][$channel_id] ?? FALSE);
      }
    }

    $settings->set('preferences', $preferences);
    $settings->set('quiet_hours_start', $this->normalizeTime((string) $form_state->getValue('quiet_hours_start')));
    $settings->set('quiet_hours_end', $this->normalizeTime((string) $form_state->getValue('quiet_hours_end')));
    $settings->set('quiet_hours_tz', $this->normalizeString((string) $form_state->getValue('quiet_hours_tz')));
    $settings->save();

    $this->messenger()->addStatus($this->t('Your notification preferences have been saved.'));
  }

  /**
   * Returns the enabled channel columns as id => label.
   *
   * @return array<string, string>
   *   Channel id => human-readable label (falls back to the id).
   */
  private function channelColumns(): array {
    $enabled = (array) $this->config(self::SETTINGS)->get('enabled_channels');
    $definitions = $this->channelManager->getDefinitions();
    $columns = [];
    foreach ($enabled as $channel_id) {
      $columns[$channel_id] = (string) ($definitions[$channel_id]['label'] ?? $channel_id);
    }
    return $columns;
  }

  /**
   * Returns the overridable notification types (the matrix rows).
   *
   * @return array<string, \Drupal\openintranet_notifications\Entity\NotificationTypeInterface>
   *   Type id => type entity, only where the user may override channels.
   */
  private function overridableTypes(): array {
    $types = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->loadMultiple();
    $overridable = [];
    foreach ($types as $id => $type) {
      if ($type instanceof NotificationTypeInterface && $type->userCanOverride()) {
        $overridable[$id] = $type;
      }
    }
    return $overridable;
  }

  /**
   * Builds the timezone select options.
   *
   * @return array<string, string>
   *   Timezone identifier => label.
   */
  private function timezoneOptions(): array {
    $zones = \DateTimeZone::listIdentifiers();
    return array_combine($zones, $zones);
  }

  /**
   * Normalizes a submitted HH:MM value, returning NULL when empty.
   */
  private function normalizeTime(string $value): ?string {
    $value = trim($value);
    return $value === '' ? NULL : substr($value, 0, 5);
  }

  /**
   * Normalizes a submitted string value, returning NULL when empty.
   */
  private function normalizeString(string $value): ?string {
    $value = trim($value);
    return $value === '' ? NULL : $value;
  }

}
