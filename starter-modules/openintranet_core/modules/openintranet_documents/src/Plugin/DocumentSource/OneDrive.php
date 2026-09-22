<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Plugin\DocumentSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_documents\OiDocumentInterface;

/**
 * Microsoft OneDrive document source.
 *
 * @DocumentSource(
 *   id = "onedrive",
 *   label = @Translation("Add from OneDrive"),
 *   description = @Translation("Embed a document from Microsoft OneDrive"),
 *   icon = "bi-microsoft"
 * )
 */
class OneDrive extends DocumentSourceBase {

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

    // Try to convert to download URL.
    return str_replace('/embed', '/download', $url);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileMetadata(OiDocumentInterface $document): array {
    return [
      'source' => $this->t('Microsoft OneDrive'),
      'url' => $document->getSourceUrl(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSourceForm(array $form, FormStateInterface $form_state, OiDocumentInterface $document): array {
    $form['external_url'] = [
      '#type' => 'url',
      '#title' => $this->t('OneDrive URL'),
      '#description' => $this->t('Paste the sharing link or embed link from OneDrive. The file must be shared publicly or with "Anyone with the link".'),
      '#placeholder' => 'https://onedrive.live.com/embed?...',
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
    if (!empty($url) && !$this->isValidOneDriveUrl($url)) {
      $form_state->setErrorByName('source_fields][external_url', $this->t('Please enter a valid OneDrive URL.'));
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
   * Converts a OneDrive sharing URL to an embed URL.
   *
   * @param string $url
   *   The sharing URL.
   *
   * @return string
   *   The embed URL.
   */
  protected function convertToEmbedUrl(string $url): string {
    // If already an embed URL, return as-is.
    if (str_contains($url, '/embed')) {
      return $url;
    }

    // Try to convert sharing URLs to embed URLs.
    return str_replace('/view', '/embed', $url);
  }

  /**
   * Validates a OneDrive URL.
   *
   * @param string $url
   *   The URL to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function isValidOneDriveUrl(string $url): bool {
    // Accept various OneDrive URL formats.
    $patterns = [
      '/^https:\/\/onedrive\.live\.com\//',
      '/^https:\/\/1drv\.ms\//',
      '/^https:\/\/.*\.sharepoint\.com\//',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $url)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
