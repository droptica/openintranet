<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_documents\DocumentSourceManager;
use Drupal\openintranet_documents\OiFolderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the OI Document entity.
 */
class OiDocumentForm extends ContentEntityForm {

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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Add class to the form for targeting.
    $form['#attributes']['class'][] = 'oi-document-form';

    // Attach library for proper styling.
    $form['#attached']['library'][] = 'openintranet_documents/forms';
    $form['#attached']['library'][] = 'core/drupal.ajax';

    // Completely remove entity field widgets that will be handled by plugins.
    // The plugins manage source_url and file fields directly.
    // We also remove source_type widget since we use a custom select element.
    // Using unset() instead of #access = FALSE to prevent widget processing.
    unset($form['source_url']);
    unset($form['file']);
    unset($form['source_type']);

    /** @var \Drupal\openintranet_documents\OiDocumentInterface $document */
    $document = $this->entity;

    // Determine the current source type.
    $source_type = $form_state->getValue('source_type');
    if (!$source_type) {
      // Check route parameter first, then entity value.
      $route_source_type = $this->getRouteMatch()->getParameter('source_type');
      if ($route_source_type) {
        $source_type = $route_source_type;
      }
      else {
        $source_type = $document->getSourceType();
      }
    }

    // Get enabled sources.
    $enabled_definitions = $this->sourceManager->getEnabledDefinitions();

    // Build source type options.
    $source_options = [];
    foreach ($enabled_definitions as $id => $definition) {
      $source_options[$id] = (string) $definition['label'];
    }

    // Ensure we have a valid source type.
    if (!isset($source_options[$source_type])) {
      $source_type = $this->sourceManager->getDefaultSourceId();
      if (!isset($source_options[$source_type])) {
        $source_type = 'local_file';
      }
    }

    // Source type selector (only show if multiple sources enabled).
    if (count($source_options) > 1) {
      $form['source_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Document Source'),
        '#options' => $source_options,
        '#default_value' => $source_type,
        '#required' => TRUE,
        '#weight' => -15,
        '#ajax' => [
          'callback' => '::ajaxSourceTypeCallback',
          'wrapper' => 'source-fields-wrapper',
          'event' => 'change',
        ],
      ];
    }
    else {
      // Single source - hidden field.
      $form['source_type'] = [
        '#type' => 'hidden',
        '#value' => $source_type,
      ];
    }

    // Wrapper for source-specific fields.
    // #tree => TRUE is CRITICAL - ensures nested values are preserved in form_state.
    $form['source_fields'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['id' => 'source-fields-wrapper'],
      '#weight' => 5,
    ];

    // Get the plugin instance and build its form.
    try {
      $plugin = $this->sourceManager->createInstanceById($source_type);
      $form['source_fields'] = $plugin->buildSourceForm($form['source_fields'], $form_state, $document);
    }
    catch (\Exception $e) {
      $form['source_fields']['error'] = [
        '#markup' => $this->t('Error loading source plugin: @message', ['@message' => $e->getMessage()]),
      ];
    }

    // Pre-fill folder from route parameter.
    $route_match = $this->getRouteMatch();
    $folder = $route_match->getParameter('folder');

    if ($folder instanceof OiFolderInterface && $this->entity->isNew()) {
      $form['folder']['widget'][0]['target_id']['#default_value'] = $folder;
    }

    return $form;
  }

  /**
   * AJAX callback for source type change.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The source fields container.
   */
  public function ajaxSourceTypeCallback(array &$form, FormStateInterface $form_state): array {
    return $form['source_fields'];
  }

  /**
   * {@inheritdoc}
   *
   * Override to skip 'file' and 'source_url' fields that are managed by plugins.
   */
  protected function copyFormValuesToEntity($entity, array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\openintranet_documents\OiDocumentInterface $entity */
    // Get the form display to identify widget fields.
    $form_display = $this->getFormDisplay($form_state);

    // Extract values for all fields except those managed by plugins.
    foreach ($form_display->getComponents() as $name => $options) {
      // Skip fields managed by document source plugins.
      // Also skip source_type since we use a custom select instead of widget.
      if (in_array($name, ['file', 'source_url', 'source_type'], TRUE)) {
        continue;
      }

      if (isset($form[$name])) {
        $widget = $form_display->getRenderer($name);
        if ($widget) {
          $widget->extractFormValues($entity->get($name), $form, $form_state);
        }
      }
    }

    // Invoke form alter hooks to allow changes.
    $this->updateChangedTime($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $source_type = $form_state->getValue('source_type') ?? 'local_file';

    try {
      $plugin = $this->sourceManager->createInstanceById($source_type);
      $plugin->validateSourceForm($form, $form_state);
    }
    catch (\Exception $e) {
      $form_state->setError($form, $this->t('Invalid source type.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\openintranet_documents\OiDocumentInterface $entity */
    $entity = $this->entity;

    // Get source type and set it on entity.
    $source_type = $form_state->getValue('source_type') ?? 'local_file';
    $entity->setSourceType($source_type);

    // Let plugin handle its form submission.
    try {
      $plugin = $this->sourceManager->createInstanceById($source_type);
      $plugin->submitSourceForm($form, $form_state, $entity);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error saving document: @message', ['@message' => $e->getMessage()]));
    }

    $result = parent::save($form, $form_state);

    $message_args = ['%label' => $entity->getTitle()];
    $logger_args = ['%label' => $entity->label()];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New document %label has been created.', $message_args));
        $this->logger('openintranet_documents')->notice('New document %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The document %label has been updated.', $message_args));
        $this->logger('openintranet_documents')->notice('The document %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    // Redirect to folder listing where the document is located.
    $folder = $entity->getFolder();
    if ($folder) {
      $form_state->setRedirect('openintranet_documents.folder.view', ['oi_folder' => $folder->id()]);
    }
    else {
      $form_state->setRedirect('openintranet_documents.browser');
    }

    return $result;
  }

}
