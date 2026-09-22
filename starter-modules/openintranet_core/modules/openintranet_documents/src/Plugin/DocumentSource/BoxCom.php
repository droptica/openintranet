<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Plugin\DocumentSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_documents\OiDocumentInterface;

/**
 * Box.com document source.
 *
 * @DocumentSource(
 *   id = "box_com",
 *   label = @Translation("Add from Box"),
 *   description = @Translation("Embed a document from Box.com"),
 *   icon = "bi-box"
 * )
 */
class BoxCom extends DocumentSourceBase {

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
    // Box doesn't support direct download via URL modification.
    // Return the share link which allows download through Box UI.
    return $document->getSourceUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getFileMetadata(OiDocumentInterface $document): array {
    return [
      'source' => $this->t('Box.com'),
      'url' => $document->getSourceUrl(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSourceForm(array $form, FormStateInterface $form_state, OiDocumentInterface $document): array {
    $form['external_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Box URL'),
      '#description' => $this->t('Paste the shared link from Box. The file must be shared with a public link.'),
      '#placeholder' => 'https://app.box.com/s/abc123xyz',
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
    if (!empty($url) && !$this->isValidBoxUrl($url)) {
      $form_state->setErrorByName('source_fields][external_url', $this->t('Please enter a valid Box.com sharing URL (e.g., https://app.box.com/s/abc123xyz).'));
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
   * Converts a Box sharing URL to an embed URL.
   *
   * @param string $url
   *   The sharing URL.
   *
   * @return string
   *   The embed URL.
   */
  protected function convertToEmbedUrl(string $url): string {
    // Share: https://app.box.com/s/abc123
    // Embed: https://app.box.com/embed/s/abc123
    return str_replace('box.com/s/', 'box.com/embed/s/', $url);
  }

  /**
   * Validates a Box.com URL.
   *
   * @param string $url
   *   The URL to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function isValidBoxUrl(string $url): bool {
    // Accept Box.com share URLs.
    return (bool) preg_match('/^https:\/\/(app\.)?box\.com\/(s|shared)\//', $url);
  }

}
