<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Global settings form for the notifications subsystem (Chunk 4F).
 *
 * Edits the managed keys of openintranet_notifications.settings while leaving
 * the keys it does not own (queue.id, default_user_preferences) untouched.
 */
final class NotificationSettingsForm extends ConfigFormBase {

  private const SETTINGS = 'openintranet_notifications.settings';

  /**
   * The channel plugin manager.
   */
  private ChannelPluginManager $channelManager;

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->channelManager = $container->get('plugin.manager.openintranet_notification_channel');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_notifications_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);
    $channel_options = $this->channelOptions();

    $kill_switch = (array) $config->get('kill_switch');
    $killed = array_keys(array_filter($kill_switch));

    $form['enabled_channels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled channels'),
      '#description' => $this->t('Channels that are globally available for delivery.'),
      '#options' => $channel_options,
      '#default_value' => (array) $config->get('enabled_channels'),
    ];
    $form['enabled_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled notification types'),
      '#description' => $this->t('Notification types that are globally active.'),
      '#options' => $this->typeOptions(),
      '#default_value' => (array) $config->get('enabled_types'),
    ];
    $form['retention_default_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Default audit retention (days)'),
      '#description' => $this->t('Fallback retention for notification records when a type does not set its own.'),
      '#min' => 0,
      '#default_value' => (int) $config->get('retention.default_days'),
    ];
    $form['kill_switch'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Channel kill switch'),
      '#description' => $this->t('Checked channels are blocked: no deliveries are attempted regardless of type or user preference.'),
      '#options' => $channel_options,
      '#default_value' => $killed,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(self::SETTINGS);

    $config->set('enabled_channels', array_values(array_filter((array) $form_state->getValue('enabled_channels'))));
    $config->set('enabled_types', array_values(array_filter((array) $form_state->getValue('enabled_types'))));
    $config->set('retention.default_days', (int) $form_state->getValue('retention_default_days'));

    // Build a complete channel => bool map so disabling a kill switch persists.
    $killed = array_filter((array) $form_state->getValue('kill_switch'));
    $kill_switch = [];
    foreach (array_keys($this->channelOptions()) as $channel_id) {
      $kill_switch[$channel_id] = isset($killed[$channel_id]);
    }
    $config->set('kill_switch', $kill_switch);

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Builds the channel checkbox options from the channel plugin manager.
   *
   * @return array<string, string>
   *   Channel id => label.
   */
  private function channelOptions(): array {
    $options = [];
    foreach ($this->channelManager->getSelectableDefinitions() as $id => $definition) {
      $options[$id] = (string) ($definition['label'] ?? $id);
    }
    asort($options);
    return $options;
  }

  /**
   * Builds the notification-type checkbox options.
   *
   * @return array<string, string>
   *   Type id => label.
   */
  private function typeOptions(): array {
    $options = [];
    $types = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->loadMultiple();
    foreach ($types as $id => $type) {
      $options[$id] = (string) $type->label();
    }
    asort($options);
    return $options;
  }

}
