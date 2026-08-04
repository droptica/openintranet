<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for forum defaults.
 */
final class ForumSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_forum_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['openintranet_forum.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openintranet_forum.settings');

    $form['feed'] = [
      '#type' => 'details',
      '#title' => $this->t('Feed'),
      '#open' => TRUE,
    ];
    $form['feed']['posts_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Posts per page'),
      '#default_value' => $config->get('posts_per_page') ?? 20,
      '#min' => 1,
    ];
    $form['feed']['default_sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Default feed sort'),
      '#default_value' => $config->get('default_sort') ?? 'last_activity',
      '#options' => [
        'last_activity' => $this->t('Last activity'),
        'newest' => $this->t('Newest first'),
        'popular' => $this->t('Most popular'),
      ],
    ];
    $form['feed']['require_category'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require category on every post'),
      '#default_value' => (bool) $config->get('require_category'),
    ];
    $form['feed']['enable_post_images'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow post images'),
      '#default_value' => (bool) $config->get('enable_post_images'),
    ];
    $form['feed']['allow_anonymous_view'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow anonymous users to view the forum'),
      '#default_value' => (bool) $config->get('allow_anonymous_view'),
    ];

    $form['trending'] = [
      '#type' => 'details',
      '#title' => $this->t('Trending'),
      '#open' => TRUE,
    ];
    $form['trending']['trending_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Trending timeframe in days'),
      '#default_value' => $config->get('trending_days') ?? 7,
      '#min' => 1,
    ];
    $form['trending']['trending_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Trending cards limit'),
      '#default_value' => $config->get('trending_limit') ?? 6,
      '#min' => 1,
    ];

    $form['replies'] = [
      '#type' => 'details',
      '#title' => $this->t('Replies'),
      '#open' => TRUE,
    ];
    $form['replies']['enable_threading'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable reply threading'),
      '#default_value' => (bool) $config->get('enable_threading'),
    ];
    $form['replies']['threading_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Threading depth'),
      '#default_value' => $config->get('threading_depth') ?? 1,
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="enable_threading"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['notifications'] = [
      '#type' => 'details',
      '#title' => $this->t('Notifications'),
      '#open' => TRUE,
    ];
    $form['notifications']['notify_on_reply'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify users about replies'),
      '#default_value' => (bool) $config->get('notify_on_reply'),
    ];
    $form['notifications']['notify_on_mention'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify users about mentions'),
      '#default_value' => (bool) $config->get('notify_on_mention'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory()->getEditable('openintranet_forum.settings')
      ->set('posts_per_page', (int) $form_state->getValue('posts_per_page'))
      ->set('default_sort', (string) $form_state->getValue('default_sort'))
      ->set('require_category', (bool) $form_state->getValue('require_category'))
      ->set('enable_post_images', (bool) $form_state->getValue('enable_post_images'))
      ->set('allow_anonymous_view', (bool) $form_state->getValue('allow_anonymous_view'))
      ->set('trending_days', (int) $form_state->getValue('trending_days'))
      ->set('trending_limit', (int) $form_state->getValue('trending_limit'))
      ->set('enable_threading', (bool) $form_state->getValue('enable_threading'))
      ->set('threading_depth', (int) $form_state->getValue('threading_depth'))
      ->set('notify_on_reply', (bool) $form_state->getValue('notify_on_reply'))
      ->set('notify_on_mention', (bool) $form_state->getValue('notify_on_mention'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
