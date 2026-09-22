<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Plugin\DocumentSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_documents\OiDocumentInterface;

/**
 * Google Drive document source.
 *
 * @DocumentSource(
 *   id = "google_drive",
 *   label = @Translation("Add from Google Drive"),
 *   description = @Translation("Embed a document from Google Drive"),
 *   icon = "bi-google"
 * )
 */
class GoogleDrive extends DocumentSourceBase {

  /**
   * {@inheritdoc}
   */
  public function buildPreview(OiDocumentInterface $document): array {
    $url = $document->getSourceUrl();
    if (!$url) {
      return ['#markup' => $this->t('No URL provided.')];
    }

    $embed_url = $this->convertToEmbedUrl($url);

    return [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => $embed_url,
        'width' => '100%',
        'height' => '600px',
        'frameborder' => '0',
        'allowfullscreen' => TRUE,
        'title' => $document->getTitle(),
        'class' => ['rounded-bottom'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadUrl(OiDocumentInterface $document): ?string {
    $url = $document->getSourceUrl();
    if (!$url) {
      return NULL;
    }

    // Extract file ID and create download URL.
    if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
      return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
    }

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileMetadata(OiDocumentInterface $document): array {
    return [
      'source' => $this->t('Google Drive'),
      'url' => $document->getSourceUrl(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSourceForm(array $form, FormStateInterface $form_state, OiDocumentInterface $document): array {
    // Example URL format: https://drive.google.com/file/d/FILE_ID/view
    $form['external_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Google Drive URL'),
      '#description' => $this->t('Paste the sharing link from Google Drive (e.g., https://drive.google.com/file/d/.../view). The file must be shared publicly or with "Anyone with the link".'),
      '#required' => TRUE,
      '#default_value' => $document->getSourceUrl(),
      '#maxlength' => 2048,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSourceForm(array &$form, FormStateInterface $form_state): void {
    $url = $form_state->getValue(['source_fields', 'external_url']);
    if (!empty($url) && !$this->isValidGoogleDriveUrl($url)) {
      $form_state->setErrorByName('source_fields][external_url', $this->t('Please enter a valid Google Drive URL (e.g., https://drive.google.com/file/d/FILE_ID/view).'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitSourceForm(array &$form, FormStateInterface $form_state, OiDocumentInterface $document): void {
    $url = $form_state->getValue(['source_fields', 'external_url']);
    $document->setSourceUrl($url);
    // Clear file for external sources.
    $document->set('file', NULL);
  }

  /**
   * Converts a Google Drive sharing URL to an embed URL.
   *
   * @param string $url
   *   The sharing URL.
   *
   * @return string
   *   The embed URL.
   */
  protected function convertToEmbedUrl(string $url): string {
    // Convert: drive.google.com/file/d/ID/view -> drive.google.com/file/d/ID/preview
    return preg_replace('/\/view(\?.*)?$/', '/preview', $url) ?? $url;
  }

  /**
   * Validates a Google Drive URL.
   *
   * @param string $url
   *   The URL to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function isValidGoogleDriveUrl(string $url): bool {
    // Accept various Google Drive URL formats.
    $patterns = [
      '/^https:\/\/drive\.google\.com\/file\/d\/[a-zA-Z0-9_-]+/',
      '/^https:\/\/docs\.google\.com\/(document|spreadsheets|presentation)\/d\/[a-zA-Z0-9_-]+/',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $url)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
