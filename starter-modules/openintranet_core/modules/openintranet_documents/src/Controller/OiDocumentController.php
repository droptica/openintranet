<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\openintranet_documents\DocumentSourceManager;
use Drupal\openintranet_documents\OiDocumentInterface;
use Drupal\openintranet_documents\Service\OiDocumentManagerInterface;
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
    private readonly OiDocumentManagerInterface $documentManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly DocumentSourceManager $sourceManager,
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
      $container->get('openintranet_documents.document_manager'),
      $container->get('date.formatter'),
      $container->get('plugin.manager.document_source'),
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

    // Get the source plugin for this document.
    $source_type = $oi_document->getSourceType();
    try {
      $plugin = $this->sourceManager->createInstanceById($source_type);
    }
    catch (\Exception $e) {
      // Fallback to local_file if plugin not found.
      $plugin = $this->sourceManager->createInstanceById('local_file');
    }

    // Get file info from plugin.
    $fileMetadata = $plugin->getFileMetadata($oi_document);
    $fileIcon = $plugin->getFileIcon($oi_document);
    $downloadUrl = $plugin->getDownloadUrl($oi_document);

    // Build file info array for template compatibility.
    $fileInfo = NULL;
    if (!empty($fileMetadata)) {
      $fileInfo = [
        'name' => $fileMetadata['name'] ?? NULL,
        'size' => $fileMetadata['size'] ?? NULL,
        'mime' => $fileMetadata['mime'] ?? NULL,
        'extension' => $fileMetadata['extension'] ?? NULL,
        'url' => $fileMetadata['url'] ?? NULL,
        'download_url' => $downloadUrl,
      ];
    }

    // Build source info.
    $sourceInfo = [
      'type' => $source_type,
      'label' => $plugin->getLabel(),
      'icon' => $plugin->getIcon(),
      'source' => $fileMetadata['source'] ?? $plugin->getLabel(),
    ];

    // Build preview using plugin.
    $preview = $plugin->buildPreview($oi_document);

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

    // Get content that references this document.
    $referencingContent = $this->documentManager->getReferencingContent((int) $oi_document->id());
    $referencingContentData = [];
    foreach ($referencingContent as $node) {
      $referencingContentData[] = [
        'title' => $node->getTitle(),
        'url' => $node->toUrl()->toString(),
        'type' => $node->type->entity ? $node->type->entity->label() : $node->bundle(),
      ];
    }

    // Build cache tags including referencing nodes.
    $cacheTags = $oi_document->getCacheTags();
    foreach ($referencingContent as $node) {
      $cacheTags = array_merge($cacheTags, $node->getCacheTags());
    }

    // Check if openintranet_access module is enabled and user has permission.
    $accessUrl = NULL;
    if ($this->moduleHandler()->moduleExists('openintranet_access')) {
      if ($this->currentUser()->hasPermission('administer openintranet access')) {
        $accessUrl = Url::fromRoute('openintranet_access.document_access_form', [
          'oi_document' => $oi_document->id(),
        ])->toString();
      }
    }

    return [
      '#theme' => 'oi_document_view',
      '#document' => $oi_document,
      '#document_id' => (int) $oi_document->id(),
      '#breadcrumbs' => $breadcrumbs,
      '#file_info' => $fileInfo,
      '#file_icon' => $fileIcon,
      '#folder_info' => $folderInfo,
      '#author_info' => $authorInfo,
      '#source_info' => $sourceInfo,
      '#preview' => $preview,
      '#referencing_content' => $referencingContentData,
      '#access_url' => $accessUrl,
      '#created' => $this->dateFormatter->format((int) $oi_document->get('created')->value, 'medium'),
      '#changed' => $this->dateFormatter->format((int) $oi_document->getChangedTime(), 'medium'),
      '#attached' => [
        'library' => ['openintranet_documents/documents'],
      ],
      '#cache' => [
        'tags' => $cacheTags,
        'contexts' => ['user.permissions'],
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

}
