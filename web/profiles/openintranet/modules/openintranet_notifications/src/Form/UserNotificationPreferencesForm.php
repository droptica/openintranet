<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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
    $instance->channelManager = $container->get('plugin.manager.openintranet_notification_channel');
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
    $form['#attributes']['class'][] = 'notifications-preferences';

    $channels = $this->channelColumns();
    $types = $this->overridableTypes();
    $uid = (int) $user->id();
    $quiet = $this->preferenceResolver->getQuietHours($uid) ?? [];
    $quiet_hours_enabled = !empty($quiet['start']) && !empty($quiet['end']);
    $site_timezone = (string) $this->config('system.date')->get('timezone.default');
    if ($site_timezone === '') {
      $site_timezone = date_default_timezone_get();
    }

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-preferences__intro']],
      'copy' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['notifications-preferences__intro-copy']],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Choose where you receive each kind of update. You can use more than one delivery method.'),
          '#attributes' => ['class' => ['notifications-preferences__intro-text']],
        ],
      ],
      'inbox_link' => [
        '#type' => 'link',
        '#title' => $this->t('View notifications'),
        '#url' => Url::fromRoute('openintranet_notifications.inbox'),
        '#attributes' => [
          'class' => [
            'notifications-preferences__inbox-link',
            'btn',
            'btn-outline-secondary',
          ],
        ],
      ],
    ];

    $form['delivery'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-preferences__section']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Delivery methods'),
        '#attributes' => ['class' => ['notifications-preferences__section-title']],
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Turn delivery methods on or off for each notification type. Required methods cannot be disabled.'),
        '#attributes' => ['class' => ['notifications-preferences__section-description']],
      ],
    ];

    $header = [
      [
        'data' => $this->t('Notification type'),
        'scope' => 'col',
        'class' => ['notifications-preferences__type-heading'],
      ],
    ];
    foreach ($channels as $label) {
      $header[] = [
        'data' => $label,
        'scope' => 'col',
        'class' => ['notifications-preferences__channel-heading'],
      ];
    }

    $form['delivery']['pref'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No notification types are available to configure.'),
      '#attributes' => ['class' => ['notifications-preferences__matrix']],
    ];

    foreach ($types as $type_id => $type) {
      $forced = array_flip($type->getForcedChannels());
      $type_label = (string) $type->label();
      $row = [];
      $row['label'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['notifications-preferences__type']],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => Html::escape($type_label),
          '#attributes' => ['class' => ['notifications-preferences__type-label']],
        ],
      ];
      if ($type->getDescription() !== '') {
        $row['label']['description'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => Html::escape($type->getDescription()),
          '#attributes' => ['class' => ['notifications-preferences__type-description']],
        ];
      }
      foreach ($channels as $channel_id => $channel_label) {
        $is_forced = isset($forced[$channel_id]);
        $status_id = Html::getId("notification-preference-$type_id-$channel_id-status");
        $row[$channel_id] = [
          '#type' => 'checkbox',
          '#title' => $this->t('@type via @channel', [
            '@type' => $type_label,
            '@channel' => $channel_label,
          ]),
          '#title_display' => 'invisible',
          '#default_value' => $is_forced ? TRUE : $this->preferenceResolver->isEnabled($uid, $type_id, $channel_id),
          '#disabled' => $is_forced,
          '#wrapper_attributes' => [
            'class' => ['notifications-preferences__channel'],
            'data-label' => $channel_label,
          ],
        ];
        if ($is_forced) {
          $row[$channel_id]['#attributes']['aria-describedby'] = $status_id;
          $row[$channel_id]['#field_suffix'] = new FormattableMarkup(
            '<span id="@id" class="notifications-preferences__required"><span aria-hidden="true">@label</span><span class="visually-hidden">. @description</span></span>',
            [
              '@id' => $status_id,
              '@label' => $this->t('Required'),
              '@description' => $this->t('This delivery method cannot be turned off.'),
            ],
          );
        }
      }
      $form['delivery']['pref'][$type_id] = $row;
    }

    $form['quiet_hours'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'notifications-preferences__section',
          'notifications-preferences__quiet-hours',
        ],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Quiet hours'),
        '#attributes' => ['class' => ['notifications-preferences__section-title']],
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Pause email and other external deliveries during a daily time window. In-app notifications still appear immediately.'),
        '#attributes' => ['class' => ['notifications-preferences__section-description']],
      ],
    ];
    $form['quiet_hours']['quiet_hours_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause external notifications during quiet hours'),
      '#default_value' => $quiet_hours_enabled,
      '#attributes' => ['class' => ['notifications-preferences__quiet-toggle']],
      '#wrapper_attributes' => ['class' => ['notifications-preferences__quiet-toggle-wrapper']],
    ];
    $quiet_hours_state = [
      ':input[name="quiet_hours_enabled"]' => ['checked' => TRUE],
    ];
    if ($quiet_hours_enabled) {
      $form['quiet_hours']['active_summary'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('External deliveries are paused daily from @start to @end (@timezone).', [
          '@start' => $quiet['start'],
          '@end' => $quiet['end'],
          '@timezone' => $quiet['tz'] ?: $site_timezone,
        ]),
        '#attributes' => ['class' => ['notifications-preferences__quiet-summary']],
        '#states' => ['visible' => $quiet_hours_state],
      ];
    }
    $form['quiet_hours']['fields'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-preferences__quiet-fields']],
      '#states' => ['visible' => $quiet_hours_state],
    ];
    $form['quiet_hours']['fields']['quiet_hours_start'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start'),
      '#description' => $this->t('Example: 10:00. Use 24-hour time (HH:MM).'),
      '#size' => 5,
      '#maxlength' => 5,
      '#attributes' => [
        'type' => 'time',
        'step' => 60,
        'placeholder' => '10:00',
      ],
      '#wrapper_attributes' => ['class' => ['notifications-preferences__quiet-field']],
      '#states' => ['required' => $quiet_hours_state],
      '#default_value' => $quiet['start'] ?? '',
    ];
    $form['quiet_hours']['fields']['quiet_hours_end'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End'),
      '#description' => $this->t('Example: 13:00. Use 24-hour time (HH:MM). Overnight windows are supported.'),
      '#size' => 5,
      '#maxlength' => 5,
      '#attributes' => [
        'type' => 'time',
        'step' => 60,
        'placeholder' => '13:00',
      ],
      '#wrapper_attributes' => ['class' => ['notifications-preferences__quiet-field']],
      '#states' => ['required' => $quiet_hours_state],
      '#default_value' => $quiet['end'] ?? '',
    ];
    $form['quiet_hours']['fields']['quiet_hours_tz'] = [
      '#type' => 'select',
      '#title' => $this->t('Timezone'),
      '#options' => TimeZoneFormHelper::getOptionsListByRegion(),
      '#empty_option' => $this->t('Use site timezone (@timezone)', [
        '@timezone' => $site_timezone,
      ]),
      '#description' => $this->t('Times are interpreted in this timezone.'),
      '#wrapper_attributes' => [
        'class' => [
          'notifications-preferences__quiet-field',
          'notifications-preferences__quiet-field--timezone',
        ],
      ],
      '#default_value' => $quiet['tz'] ?? '',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['notifications-preferences__actions']],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save preferences'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!(bool) $form_state->getValue('quiet_hours_enabled')) {
      return;
    }

    $times = [];
    foreach (['quiet_hours_start', 'quiet_hours_end'] as $key) {
      $value = trim((string) $form_state->getValue($key));
      $times[$key] = $value;
      if ($value === '') {
        $form_state->setErrorByName($key, $this->t('Enter both a start and end time.'));
      }
      elseif (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
        $form_state->setErrorByName($key, $this->t('Enter a valid 24h time as HH:MM.'));
      }
    }

    if (
      $times['quiet_hours_start'] !== ''
      && $times['quiet_hours_start'] === $times['quiet_hours_end']
    ) {
      $form_state->setErrorByName(
        'quiet_hours_end',
        $this->t('The end time must be different from the start time.'),
      );
    }
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

    $quiet_hours_enabled = (bool) $form_state->getValue('quiet_hours_enabled');
    $settings->set('preferences', $preferences);
    $settings->set(
      'quiet_hours_start',
      $quiet_hours_enabled
        ? $this->normalizeTime((string) $form_state->getValue('quiet_hours_start'))
        : NULL,
    );
    $settings->set(
      'quiet_hours_end',
      $quiet_hours_enabled
        ? $this->normalizeTime((string) $form_state->getValue('quiet_hours_end'))
        : NULL,
    );
    $settings->set(
      'quiet_hours_tz',
      $quiet_hours_enabled
        ? $this->normalizeString((string) $form_state->getValue('quiet_hours_tz'))
        : NULL,
    );
    $settings->save();

    $this->messenger()->addStatus($this->t('Your notification preferences have been saved.'));
    $form_state->setRedirect(
      'openintranet_notifications.user_preferences',
      ['user' => $uid],
    );
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
      $definition = $definitions[$channel_id] ?? NULL;
      if (
        $definition === NULL
        || ($definition['user_configurable'] ?? TRUE) !== TRUE
      ) {
        continue;
      }
      $columns[$channel_id] = (string) ($definition['label'] ?? $channel_id);
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
