<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_documents\DocumentSourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for document settings.
 */
class DocumentSettingsForm extends ConfigFormBase {

  /**
   * The document source plugin manager.
   *
   * @var \Drupal\openintranet_documents\DocumentSourceManager
   */
  protected DocumentSourceManager $sourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->sourceManager = $container->get('plugin.manager.document_source');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['openintranet_documents.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_documents_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openintranet_documents.settings');
    $definitions = $this->sourceManager->getDefinitions();
    $enabled = $config->get('enabled_sources') ?? ['local_file'];

    $form['enabled_sources'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled Document Sources'),
      '#description' => $this->t('Select which document sources are available for users.'),
      '#options' => [],
      '#default_value' => array_combine($enabled, $enabled),
    ];

    foreach ($definitions as $id => $definition) {
      $label = $definition['label'];
      $description = $definition['description'] ?? '';
      $icon = $definition['icon'] ?? 'bi-file-earmark-plus';

      $form['enabled_sources']['#options'][$id] = '<i class="bi ' . $icon . ' me-2"></i>' . $label;

      // Local file is always required - cannot be disabled.
      if ($id === 'local_file') {
        $form['enabled_sources'][$id]['#disabled'] = TRUE;
        $form['enabled_sources'][$id]['#description'] = $this->t('Local file upload is always enabled.');
      }
    }

    $form['default_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Document Source'),
      '#description' => $this->t('The default source type when adding new documents.'),
      '#options' => array_map(fn($d) => (string) $d['label'], $definitions),
      '#default_value' => $config->get('default_source') ?? 'local_file',
    ];

    $form['max_file_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum file size (MB)'),
      '#description' => $this->t('Maximum upload size for local files. Default is 50 MB.'),
      '#default_value' => $config->get('max_file_size') ?? 50,
      '#min' => 1,
      '#max' => 500,
      '#size' => 10,
    ];

    $form['#attached']['library'][] = 'openintranet_documents/documents';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $enabled = array_filter($form_state->getValue('enabled_sources'));
    // Ensure local_file is always enabled.
    $enabled['local_file'] = 'local_file';

    $this->config('openintranet_documents.settings')
      ->set('enabled_sources', array_values($enabled))
      ->set('default_source', $form_state->getValue('default_source'))
      ->set('max_file_size', (int) $form_state->getValue('max_file_size'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
