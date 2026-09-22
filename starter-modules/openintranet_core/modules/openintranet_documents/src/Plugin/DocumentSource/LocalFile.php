<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Plugin\DocumentSource;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\openintranet_documents\OiDocumentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Local file upload document source.
 *
 * @DocumentSource(
 *   id = "local_file",
 *   label = @Translation("Upload Local File"),
 *   description = @Translation("Upload a file to the server"),
 *   icon = "bi-file-earmark-arrow-up"
 * )
 */
class LocalFile extends DocumentSourceBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreview(OiDocumentInterface $document): array {
    $file = $document->getFile();
    if (!$file) {
      return ['#markup' => $this->t('No file uploaded.')];
    }

    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    $url = $file->createFileUrl(FALSE);

    // Image preview.
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
      return [
        '#theme' => 'image',
        '#uri' => $file->getFileUri(),
        '#alt' => $document->getTitle(),
        '#attributes' => [
          'class' => ['img-fluid', 'rounded-bottom'],
        ],
      ];
    }

    // PDF preview.
    if ($extension === 'pdf') {
      return [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#attributes' => [
          'src' => $url . '#view=FitH',
          'width' => '100%',
          'height' => '600px',
          'frameborder' => '0',
          'title' => $document->getTitle(),
          'class' => ['rounded-bottom'],
        ],
      ];
    }

    // No preview available for other file types.
    return [
      '#markup' => $this->t('Preview not available for this file type.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadUrl(OiDocumentInterface $document): ?string {
    $file = $document->getFile();
    if (!$file) {
      return NULL;
    }

    return Url::fromRoute('openintranet_documents.download', [
      'oi_document' => $document->id(),
    ])->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getFileMetadata(OiDocumentInterface $document): array {
    $file = $document->getFile();
    if (!$file) {
      return [];
    }

    return [
      'name' => $file->getFilename(),
      'size' => $this->formatBytes((int) $file->getSize()),
      'mime' => $file->getMimeType(),
      'extension' => pathinfo($file->getFilename(), PATHINFO_EXTENSION),
      'url' => $file->createFileUrl(FALSE),
      'source' => $this->t('Local File'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFileIcon(OiDocumentInterface $document): string {
    $file = $document->getFile();
    if (!$file) {
      return 'bi-file-earmark text-muted';
    }

    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));

    $icons = [
      // Documents.
      'pdf' => 'bi-file-earmark-pdf text-danger',
      'doc' => 'bi-file-earmark-word text-primary',
      'docx' => 'bi-file-earmark-word text-primary',
      'odt' => 'bi-file-earmark-word text-primary',
      'rtf' => 'bi-file-earmark-text text-secondary',
      'txt' => 'bi-file-earmark-text text-secondary',
      // Spreadsheets.
      'xls' => 'bi-file-earmark-excel text-success',
      'xlsx' => 'bi-file-earmark-excel text-success',
      'ods' => 'bi-file-earmark-excel text-success',
      'csv' => 'bi-file-earmark-spreadsheet text-success',
      // Presentations.
      'ppt' => 'bi-file-earmark-ppt text-warning',
      'pptx' => 'bi-file-earmark-ppt text-warning',
      'odp' => 'bi-file-earmark-ppt text-warning',
      // Images.
      'jpg' => 'bi-file-earmark-image text-info',
      'jpeg' => 'bi-file-earmark-image text-info',
      'png' => 'bi-file-earmark-image text-info',
      'gif' => 'bi-file-earmark-image text-info',
      'webp' => 'bi-file-earmark-image text-info',
      // Archives.
      'zip' => 'bi-file-earmark-zip text-secondary',
      'rar' => 'bi-file-earmark-zip text-secondary',
      '7z' => 'bi-file-earmark-zip text-secondary',
    ];

    return $icons[$extension] ?? 'bi-file-earmark text-muted';
  }

  /**
   * {@inheritdoc}
   */
  public function buildSourceForm(array $form, FormStateInterface $form_state, OiDocumentInterface $document): array {
    $file = $document->getFile();

    $form['upload_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#upload_location' => 'public://documents/' . date('Y') . '/' . date('m'),
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'pdf doc docx xls xlsx ppt pptx txt rtf odt ods odp jpg jpeg png gif',
        ],
        'FileSizeLimit' => [
          'fileLimit' => 50 * 1024 * 1024,
        ],
      ],
      '#required' => TRUE,
      '#default_value' => $file ? [$file->id()] : NULL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSourceForm(array &$form, FormStateInterface $form_state): void {
    $file = $form_state->getValue(['source_fields', 'upload_file']);
    if (empty($file)) {
      $form_state->setErrorByName('source_fields][upload_file', $this->t('Please upload a file.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitSourceForm(array &$form, FormStateInterface $form_state, OiDocumentInterface $document): void {
    $file_ids = $form_state->getValue(['source_fields', 'upload_file']);
    if (!empty($file_ids)) {
      $file_id = reset($file_ids);
      /** @var \Drupal\file\FileInterface|null $file */
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($file_id);
      if ($file instanceof FileInterface) {
        $file->setPermanent();
        $file->save();
        $document->set('file', $file_id);
      }
    }
    // Clear source_url for local files.
    $document->setSourceUrl(NULL);
  }

  /**
   * Formats bytes to human-readable string.
   *
   * @param int $bytes
   *   Number of bytes.
   *
   * @return string
   *   Formatted string.
   */
  protected function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
  }

}
