<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_engagement\Service\EntityTypeDiscoveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for tracked entity types.
 */
final class EntityTypesForm extends ConfigFormBase {

  /**
   * Constructs the EntityTypesForm.
   *
   * @param \Drupal\openintranet_engagement\Service\EntityTypeDiscoveryInterface $discovery
   *   The entity type discovery service.
   */
  public function __construct(
    private readonly EntityTypeDiscoveryInterface $discovery,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('openintranet_engagement.entity_type_discovery'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_engagement_entity_types';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['openintranet_engagement.entity_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openintranet_engagement.entity_types');
    $savedTypes = $config->get('entity_types') ?? [];
    $savedTypesIndexed = [];
    foreach ($savedTypes as $type) {
      $savedTypesIndexed[$type['entity_type']] = $type;
    }

    $form['description'] = [
      '#markup' => '<p>' . $this->t('Configure which entity types to track and their point values for each operation.') . '</p>',
    ];

    $form['entity_types'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Entity Type'),
        $this->t('Enabled'),
        $this->t('Create'),
        $this->t('View'),
        $this->t('Update'),
        $this->t('Delete'),
        $this->t('Status'),
      ],
      '#empty' => $this->t('No entity types found.'),
    ];

    $trackableTypes = $this->discovery->getTrackableEntityTypes();

    foreach ($trackableTypes as $typeId => $typeInfo) {
      $saved = $savedTypesIndexed[$typeId] ?? NULL;
      $defaultValues = $typeInfo['default_values'];

      // Determine status.
      $status = '';
      if ($typeInfo['default_enabled']) {
        $status = $this->t('Default');
      }
      elseif ($typeInfo['auto_enabled']) {
        $status = $this->t('Auto-enabled');
      }

      $form['entity_types'][$typeId]['label'] = [
        '#plain_text' => $typeInfo['label'] . ' (' . $typeId . ')',
      ];

      $form['entity_types'][$typeId]['enabled'] = [
        '#type' => 'checkbox',
        '#default_value' => $saved ? $saved['enabled'] : ($typeInfo['default_enabled'] || $typeInfo['auto_enabled']),
      ];

      $form['entity_types'][$typeId]['create'] = [
        '#type' => 'number',
        '#default_value' => $saved['operations']['create'] ?? $defaultValues['create'] ?? 5,
        '#min' => 0,
        '#max' => 100,
        '#size' => 4,
      ];

      $form['entity_types'][$typeId]['view'] = [
        '#type' => 'number',
        '#default_value' => $saved['operations']['view'] ?? $defaultValues['view'] ?? 1,
        '#min' => 0,
        '#max' => 100,
        '#size' => 4,
      ];

      $form['entity_types'][$typeId]['update'] = [
        '#type' => 'number',
        '#default_value' => $saved['operations']['update'] ?? $defaultValues['update'] ?? 3,
        '#min' => 0,
        '#max' => 100,
        '#size' => 4,
      ];

      $form['entity_types'][$typeId]['delete'] = [
        '#type' => 'number',
        '#default_value' => $saved['operations']['delete'] ?? $defaultValues['delete'] ?? 1,
        '#min' => 0,
        '#max' => 100,
        '#size' => 4,
      ];

      $form['entity_types'][$typeId]['status'] = [
        '#plain_text' => $status,
      ];
    }

    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Help'),
      '#open' => FALSE,
    ];

    $form['help']['content'] = [
      '#markup' => '<ul>
        <li>' . $this->t('<strong>Create</strong> - Points awarded when content is created') . '</li>
        <li>' . $this->t('<strong>View</strong> - Points awarded when content is viewed (full page only)') . '</li>
        <li>' . $this->t('<strong>Update</strong> - Points awarded when content is updated') . '</li>
        <li>' . $this->t('<strong>Delete</strong> - Points awarded when content is deleted') . '</li>
        <li>' . $this->t('Set value to 0 to disable tracking for that operation') . '</li>
        <li>' . $this->t('<strong>Default</strong> - Enabled by default on module install') . '</li>
        <li>' . $this->t('<strong>Auto-enabled</strong> - Automatically enabled when source module is active') . '</li>
      </ul>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entityTypes = [];
    $values = $form_state->getValue('entity_types') ?? [];

    foreach ($values as $typeId => $typeValues) {
      $entityTypes[] = [
        'entity_type' => $typeId,
        'enabled' => (bool) $typeValues['enabled'],
        'operations' => [
          'create' => (int) $typeValues['create'],
          'view' => (int) $typeValues['view'],
          'update' => (int) $typeValues['update'],
          'delete' => (int) $typeValues['delete'],
        ],
      ];
    }

    $this->config('openintranet_engagement.entity_types')
      ->set('entity_types', $entityTypes)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
