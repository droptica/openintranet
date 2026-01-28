<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\openintranet_documents\OiDocumentInterface;
use Drupal\openintranet_documents\Service\OiFolderManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for viewing OI Document entities.
 */
final class OiDocumentController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly OiFolderManagerInterface $folderManager,
    private readonly DateFormatterInterface $dateFormatter,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('openintranet_documents.folder_manager'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Returns the title for the document page.
   */
  public function title(OiDocumentInterface $oi_document): string {
    return (string) $oi_document->getTitle();
  }

  /**
   * Displays a document.
   */
  public function view(OiDocumentInterface $oi_document): array {
    // Build breadcrumbs.
    $breadcrumbs = $this->buildBreadcrumbs($oi_document);

    // Get file info.
    $file = $oi_document->getFile();
    $fileInfo = NULL;
    if ($file) {
      $fileInfo = [
        'name' => $file->getFilename(),
        'size' => $this->formatBytes((int) $file->getSize()),
        'mime' => $file->getMimeType(),
        'extension' => pathinfo($file->getFilename(), PATHINFO_EXTENSION),
        'url' => $file->createFileUrl(FALSE),
        'download_url' => Url::fromRoute('openintranet_documents.download', [
          'oi_document' => $oi_document->id(),
        ])->toString(),
      ];
    }

    // Get folder info.
    $folder = $oi_document->getFolder();
    $folderInfo = NULL;
    if ($folder) {
      $folderInfo = [
        'name' => $folder->getName(),
        'url' => Url::fromRoute('openintranet_documents.folder.view', [
          'oi_folder' => $folder->id(),
        ])->toString(),
      ];
    }

    // Get author info.
    $owner = $oi_document->getOwner();
    $authorInfo = [
      'name' => $owner->getDisplayName(),
      'url' => $owner->toUrl()->toString(),
    ];

    // Get icon for file type.
    $fileIcon = $this->getFileIcon($fileInfo['extension'] ?? '');

    return [
      '#theme' => 'oi_document_view',
      '#document' => $oi_document,
      '#document_id' => (int) $oi_document->id(),
      '#breadcrumbs' => $breadcrumbs,
      '#file_info' => $fileInfo,
      '#file_icon' => $fileIcon,
      '#folder_info' => $folderInfo,
      '#author_info' => $authorInfo,
      '#created' => $this->dateFormatter->format((int) $oi_document->get('created')->value, 'medium'),
      '#changed' => $this->dateFormatter->format((int) $oi_document->getChangedTime(), 'medium'),
      '#attached' => [
        'library' => ['openintranet_documents/documents'],
      ],
      '#cache' => [
        'tags' => $oi_document->getCacheTags(),
      ],
    ];
  }

  /**
   * Builds breadcrumb links.
   */
  private function buildBreadcrumbs(OiDocumentInterface $document): array {
    $breadcrumbs = [
      [
        'title' => $this->t('Documents'),
        'url' => Url::fromRoute('openintranet_documents.browser')->toString(),
      ],
    ];

    $folder = $document->getFolder();
    if ($folder) {
      // Get folder ancestors.
      $ancestors = $this->folderManager->getAncestors($folder);
      foreach ($ancestors as $ancestor) {
        $breadcrumbs[] = [
          'title' => $ancestor->getName(),
          'url' => Url::fromRoute('openintranet_documents.folder.view', [
            'oi_folder' => $ancestor->id(),
          ])->toString(),
        ];
      }
      // Add current folder.
      $breadcrumbs[] = [
        'title' => $folder->getName(),
        'url' => Url::fromRoute('openintranet_documents.folder.view', [
          'oi_folder' => $folder->id(),
        ])->toString(),
      ];
    }

    return $breadcrumbs;
  }

  /**
   * Gets file icon based on extension.
   */
  private function getFileIcon(string $extension): string {
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

    return $icons[strtolower($extension)] ?? 'bi-file-earmark text-muted';
  }

  /**
   * Formats bytes to human readable string.
   */
  private function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
  }

}
