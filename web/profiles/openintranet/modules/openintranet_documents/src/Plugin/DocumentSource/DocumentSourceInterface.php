<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Plugin\DocumentSource;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_documents\OiDocumentInterface;

/**
 * Defines the interface for document source plugins.
 *
 * Document sources define how documents are stored and displayed,
 * such as local file uploads, Google Drive, OneDrive, etc.
 */
interface DocumentSourceInterface extends PluginInspectionInterface, ConfigurableInterface {

  /**
   * Returns the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function getLabel(): string;

  /**
   * Returns the plugin description.
   *
   * @return string
   *   The plugin description.
   */
  public function getDescription(): string;

  /**
   * Returns the Bootstrap icon class for this source.
   *
   * @return string
   *   The icon class (e.g., "bi-google").
   */
  public function getIcon(): string;

  /**
   * Builds the preview render array for a document.
   *
   * @param \Drupal\openintranet_documents\OiDocumentInterface $document
   *   The document entity.
   *
   * @return array
   *   A render array for previewing the document (iframe, img, etc.).
   */
  public function buildPreview(OiDocumentInterface $document): array;

  /**
   * Returns the download URL for a document.
   *
   * @param \Drupal\openintranet_documents\OiDocumentInterface $document
   *   The document entity.
   *
   * @return string|null
   *   The download URL, or NULL if not available.
   */
  public function getDownloadUrl(OiDocumentInterface $document): ?string;

  /**
   * Returns file metadata for a document.
   *
   * @param \Drupal\openintranet_documents\OiDocumentInterface $document
   *   The document entity.
   *
   * @return array
   *   An array of metadata (may include 'name', 'size', 'type', 'source', etc.).
   */
  public function getFileMetadata(OiDocumentInterface $document): array;

  /**
   * Returns the file icon class for this source.
   *
   * @param \Drupal\openintranet_documents\OiDocumentInterface $document
   *   The document entity.
   *
   * @return string
   *   The Bootstrap icon class.
   */
  public function getFileIcon(OiDocumentInterface $document): string;

  /**
   * Builds the form elements specific to this source.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\openintranet_documents\OiDocumentInterface $document
   *   The document entity.
   *
   * @return array
   *   The form elements for this source.
   */
  public function buildSourceForm(array $form, FormStateInterface $form_state, OiDocumentInterface $document): array;

  /**
   * Validates the source form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateSourceForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Submits the source form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\openintranet_documents\OiDocumentInterface $document
   *   The document entity.
   */
  public function submitSourceForm(array &$form, FormStateInterface $form_state, OiDocumentInterface $document): void;

}
