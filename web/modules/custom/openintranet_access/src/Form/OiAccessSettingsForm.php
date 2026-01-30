<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Open Intranet Access settings.
 */
final class OiAccessSettingsForm extends ConfigFormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['openintranet_access.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_access_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openintranet_access.settings');

    // Node types.
    $form['node_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content types'),
      '#description' => $this->t('Select which content types should have access control.'),
    ];

    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($node_types as $type) {
      $options[$type->id()] = $type->label();
    }

    $form['node_types']['enabled_node_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled content types'),
      '#options' => $options,
      '#default_value' => $config->get('enabled_entity_types.node') ?? [],
      '#description' => $this->t('Leave empty to enable for all content types.'),
    ];

    // Other entity types.
    $form['other_entities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Other entity types'),
    ];

    $form['other_entities']['oi_document'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable for Documents (oi_document)'),
      '#default_value' => $config->get('enabled_entity_types.oi_document') ?? TRUE,
    ];

    $form['other_entities']['oi_folder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable for Folders (oi_folder)'),
      '#default_value' => $config->get('enabled_entity_types.oi_folder') ?? TRUE,
    ];

    // Behavior settings.
    $form['behavior'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Behavior'),
    ];

    $form['behavior']['owner_always_has_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Owner always has access'),
      '#description' => $this->t('If enabled, the content owner always has access regardless of restrictions.'),
      '#default_value' => $config->get('behavior.owner_always_has_access') ?? TRUE,
    ];

    $form['behavior']['inherit_folder_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Documents inherit folder access'),
      '#description' => $this->t('If enabled, documents inherit access restrictions from their parent folder.'),
      '#default_value' => $config->get('behavior.inherit_folder_access') ?? TRUE,
    ];

    // UI settings.
    $form['ui'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User interface'),
    ];

    $form['ui']['show_access_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show access summary'),
      '#description' => $this->t('Show a summary of who has access on the access form.'),
      '#default_value' => $config->get('ui.show_access_summary') ?? TRUE,
    ];

    $form['ui']['show_inherited_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show inherited access'),
      '#description' => $this->t('Show access inherited from parent folders.'),
      '#default_value' => $config->get('ui.show_inherited_access') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $enabled_node_types = array_filter($form_state->getValue('enabled_node_types') ?? []);

    $this->config('openintranet_access.settings')
      ->set('enabled_entity_types.node', array_values($enabled_node_types))
      ->set('enabled_entity_types.oi_document', (bool) $form_state->getValue('oi_document'))
      ->set('enabled_entity_types.oi_folder', (bool) $form_state->getValue('oi_folder'))
      ->set('behavior.owner_always_has_access', (bool) $form_state->getValue('owner_always_has_access'))
      ->set('behavior.inherit_folder_access', (bool) $form_state->getValue('inherit_folder_access'))
      ->set('ui.show_access_summary', (bool) $form_state->getValue('show_access_summary'))
      ->set('ui.show_inherited_access', (bool) $form_state->getValue('show_inherited_access'))
      ->save();

    parent::submitForm($form, $form_state);

    // Notify about node access rebuild if node types changed.
    $this->messenger()->addStatus($this->t('If you changed content types, you may need to <a href=":url">rebuild node access permissions</a>.', [
      ':url' => '/admin/reports/status/rebuild',
    ]));
  }

}
