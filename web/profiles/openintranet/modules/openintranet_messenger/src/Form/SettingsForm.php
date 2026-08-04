<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_messenger\Channel\ChannelPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the messenger module.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * The channel plugin manager.
   *
   * @var \Drupal\openintranet_messenger\Channel\ChannelPluginManager
   */
  protected ChannelPluginManager $channelManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\openintranet_messenger\Channel\ChannelPluginManager $channel_manager
   *   The channel plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ChannelPluginManager $channel_manager,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->channelManager = $channel_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('plugin.manager.notification_channel'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_messenger_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['openintranet_messenger.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openintranet_messenger.settings');

    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General Settings'),
    ];

    // Taxonomy vocabulary selector.
    $vocabulary_options = $this->getVocabularyOptions();
    $form['general']['taxonomy_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Contact grouping vocabulary'),
      '#description' => $this->t('Select the taxonomy vocabulary used for grouping contacts (e.g., Departments, Offices, Teams, Locations). Leave empty to disable grouping.'),
      '#options' => $vocabulary_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $config->get('taxonomy_vocabulary') ?? '',
    ];

    $channel_options = $this->channelManager->getChannelOptions();
    $form['general']['default_channel'] = [
      '#type' => 'select',
      '#title' => $this->t('Default channel'),
      '#description' => $this->t('The default notification channel when no preference is set.'),
      '#options' => $channel_options,
      '#default_value' => $config->get('default_channel') ?? 'email',
    ];

    $form['general']['enabled_channels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled channels'),
      '#description' => $this->t('Select which channels are enabled for sending notifications.'),
      '#options' => $channel_options,
      '#default_value' => $config->get('enabled_channels') ?? ['email'],
    ];

    $form['user_integration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User Integration'),
    ];

    $form['user_integration']['user_phone_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User phone field'),
      '#description' => $this->t('The machine name of the field on User entity that stores phone numbers (e.g., field_phone).'),
      '#default_value' => $config->get('user_phone_field') ?? 'field_phone',
    ];

    // Channel-specific settings.
    $form['channels'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Channel Settings'),
    ];

    foreach ($this->channelManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\openintranet_messenger\Channel\ChannelPluginInterface $plugin */
      $plugin = $this->channelManager->createInstance($id);

      $form['channel_' . $id] = [
        '#type' => 'details',
        '#title' => $definition['label'] ?? $id,
        '#group' => 'channels',
      ];

      // Channel availability status.
      $status = $plugin->isAvailable()
        ? $this->t('Available')
        : $this->t('Not available (check configuration)');

      $form['channel_' . $id]['status'] = [
        '#type' => 'item',
        '#title' => $this->t('Status'),
        '#markup' => $status,
      ];

      // Get channel-specific configuration form.
      $channel_form = $plugin->buildConfigurationForm([], $form_state);
      if (!empty($channel_form)) {
        $form['channel_' . $id] += $channel_form;
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Validate channel-specific settings.
    foreach ($this->channelManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\openintranet_messenger\Channel\ChannelPluginInterface $plugin */
      $plugin = $this->channelManager->createInstance($id);
      $plugin->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('openintranet_messenger.settings');

    $config
      ->set('taxonomy_vocabulary', $form_state->getValue('taxonomy_vocabulary'))
      ->set('default_channel', $form_state->getValue('default_channel'))
      ->set('enabled_channels', array_filter($form_state->getValue('enabled_channels')))
      ->set('user_phone_field', $form_state->getValue('user_phone_field'))
      ->save();

    // Submit channel-specific settings.
    foreach ($this->channelManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\openintranet_messenger\Channel\ChannelPluginInterface $plugin */
      $plugin = $this->channelManager->createInstance($id);
      $plugin->submitConfigurationForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Gets available taxonomy vocabulary options.
   *
   * @return array
   *   Array of vocabulary labels keyed by machine name.
   */
  protected function getVocabularyOptions(): array {
    $options = [];

    $vocabularies = $this->entityTypeManager
      ->getStorage('taxonomy_vocabulary')
      ->loadMultiple();

    foreach ($vocabularies as $vocabulary) {
      $options[$vocabulary->id()] = $vocabulary->label();
    }

    asort($options);

    return $options;
  }

}
