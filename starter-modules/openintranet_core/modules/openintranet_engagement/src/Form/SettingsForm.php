<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for engagement settings.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_engagement_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['openintranet_engagement.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openintranet_engagement.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];

    $form['general']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable event tracking'),
      '#default_value' => $config->get('enabled') ?? TRUE,
      '#description' => $this->t('When disabled, no new events will be tracked.'),
    ];

    $form['general']['track_anonymous'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Track anonymous users'),
      '#default_value' => $config->get('track_anonymous') ?? FALSE,
      '#description' => $this->t('Not recommended - may generate large amounts of data.'),
    ];

    $form['general']['event_log_retention'] = [
      '#type' => 'number',
      '#title' => $this->t('Event log retention (days)'),
      '#default_value' => $config->get('event_log_retention') ?? 90,
      '#min' => 7,
      '#max' => 365,
      '#description' => $this->t('Events older than this will be deleted during cron.'),
    ];

    $form['general']['recalculation_frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Score recalculation frequency'),
      '#default_value' => $config->get('recalculation_frequency') ?? 'hourly',
      '#options' => [
        'hourly' => $this->t('Hourly'),
        'daily' => $this->t('Daily'),
        'manual' => $this->t('Manual only'),
      ],
      '#description' => $this->t('How often to recalculate stale scores during cron.'),
    ];

    $form['thresholds'] = [
      '#type' => 'details',
      '#title' => $this->t('Scoring Thresholds'),
      '#open' => TRUE,
      '#description' => $this->t('Define thresholds for converting raw metrics to 1-5 scores.'),
    ];

    $thresholds = $config->get('thresholds') ?? [];

    $form['thresholds']['recency'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Recency (days since last activity)'),
    ];

    $recency = $thresholds['recency'] ?? [1, 7, 14, 30];
    $form['thresholds']['recency']['recency_5'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 5: ≤ days'),
      '#default_value' => $recency[0] ?? 1,
      '#min' => 1,
      '#field_suffix' => $this->t('days'),
    ];
    $form['thresholds']['recency']['recency_4'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 4: ≤ days'),
      '#default_value' => $recency[1] ?? 7,
      '#min' => 1,
      '#field_suffix' => $this->t('days'),
    ];
    $form['thresholds']['recency']['recency_3'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 3: ≤ days'),
      '#default_value' => $recency[2] ?? 14,
      '#min' => 1,
      '#field_suffix' => $this->t('days'),
    ];
    $form['thresholds']['recency']['recency_2'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 2: ≤ days'),
      '#default_value' => $recency[3] ?? 30,
      '#min' => 1,
      '#field_suffix' => $this->t('days'),
    ];

    $form['thresholds']['frequency'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Frequency (events per 30 days)'),
    ];

    $frequency = $thresholds['frequency'] ?? [5, 20, 50, 100];
    $form['thresholds']['frequency']['frequency_2'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 2: ≥ events'),
      '#default_value' => $frequency[0] ?? 5,
      '#min' => 1,
    ];
    $form['thresholds']['frequency']['frequency_3'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 3: ≥ events'),
      '#default_value' => $frequency[1] ?? 20,
      '#min' => 1,
    ];
    $form['thresholds']['frequency']['frequency_4'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 4: ≥ events'),
      '#default_value' => $frequency[2] ?? 50,
      '#min' => 1,
    ];
    $form['thresholds']['frequency']['frequency_5'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 5: ≥ events'),
      '#default_value' => $frequency[3] ?? 100,
      '#min' => 1,
    ];

    $form['thresholds']['value'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Value (total points per 30 days)'),
    ];

    $value = $thresholds['value'] ?? [20, 50, 100, 200];
    $form['thresholds']['value']['value_2'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 2: ≥ points'),
      '#default_value' => $value[0] ?? 20,
      '#min' => 1,
    ];
    $form['thresholds']['value']['value_3'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 3: ≥ points'),
      '#default_value' => $value[1] ?? 50,
      '#min' => 1,
    ];
    $form['thresholds']['value']['value_4'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 4: ≥ points'),
      '#default_value' => $value[2] ?? 100,
      '#min' => 1,
    ];
    $form['thresholds']['value']['value_5'] = [
      '#type' => 'number',
      '#title' => $this->t('Score 5: ≥ points'),
      '#default_value' => $value[3] ?? 200,
      '#min' => 1,
    ];

    $form['segments'] = [
      '#type' => 'details',
      '#title' => $this->t('Segment Definitions'),
      '#open' => FALSE,
    ];

    $form['segments']['new_user_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('New user threshold'),
      '#default_value' => $config->get('new_user_threshold') ?? 30,
      '#min' => 1,
      '#max' => 90,
      '#field_suffix' => $this->t('days'),
      '#description' => $this->t('Users registered within this many days are classified as "New".'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('openintranet_engagement.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('track_anonymous', (bool) $form_state->getValue('track_anonymous'))
      ->set('event_log_retention', (int) $form_state->getValue('event_log_retention'))
      ->set('recalculation_frequency', $form_state->getValue('recalculation_frequency'))
      ->set('thresholds', [
        'recency' => [
          (int) $form_state->getValue('recency_5'),
          (int) $form_state->getValue('recency_4'),
          (int) $form_state->getValue('recency_3'),
          (int) $form_state->getValue('recency_2'),
        ],
        'frequency' => [
          (int) $form_state->getValue('frequency_2'),
          (int) $form_state->getValue('frequency_3'),
          (int) $form_state->getValue('frequency_4'),
          (int) $form_state->getValue('frequency_5'),
        ],
        'value' => [
          (int) $form_state->getValue('value_2'),
          (int) $form_state->getValue('value_3'),
          (int) $form_state->getValue('value_4'),
          (int) $form_state->getValue('value_5'),
        ],
      ])
      ->set('new_user_threshold', (int) $form_state->getValue('new_user_threshold'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
