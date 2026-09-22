<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Plugin\DocumentSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_documents\OiDocumentInterface;

/**
 * Dropbox document source.
 *
 * @DocumentSource(
 *   id = "dropbox",
 *   label = @Translation("Add from Dropbox"),
 *   description = @Translation("Embed a document from Dropbox"),
 *   icon = "bi-dropbox"
 * )
 */
class Dropbox extends DocumentSourceBase {

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

    // ?dl=0 -> ?dl=1 forces download.
    return preg_replace('/\?dl=\d/', '?dl=1', $url) ?? $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileMetadata(OiDocumentInterface $document): array {
    return [
      'source' => $this->t('Dropbox'),
      'url' => $document->getSourceUrl(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSourceForm(array $form, FormStateInterface $form_state, OiDocumentInterface $document): array {
    $form['external_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Dropbox URL'),
      '#description' => $this->t('Paste the sharing link from Dropbox. The file must be shared with a public link.'),
      '#placeholder' => 'https://www.dropbox.com/s/abc123/file.pdf?dl=0',
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
    if (!empty($url) && !$this->isValidDropboxUrl($url)) {
      $form_state->setErrorByName('source_fields][external_url', $this->t('Please enter a valid Dropbox sharing URL (e.g., https://www.dropbox.com/s/abc123/file.pdf?dl=0).'));
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
   * Converts a Dropbox sharing URL to an embed URL.
   *
   * @param string $url
   *   The sharing URL.
   *
   * @return string
   *   The embed URL.
   */
  protected function convertToEmbedUrl(string $url): string {
    // Convert to embedder URL: ?dl=0 -> ?raw=1 for direct embed.
    return preg_replace('/\?dl=\d/', '?raw=1', $url) ?? $url;
  }

  /**
   * Validates a Dropbox URL.
   *
   * @param string $url
   *   The URL to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function isValidDropboxUrl(string $url): bool {
    // Accept various Dropbox URL formats.
    $patterns = [
      '/^https:\/\/(www\.)?dropbox\.com\/s\//',
      '/^https:\/\/(www\.)?dropbox\.com\/scl\//',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $url)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
