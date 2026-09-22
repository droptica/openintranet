<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_documents\OiFolderInterface;
use Drupal\openintranet_documents\Service\OiDocumentManagerInterface;
use Drupal\openintranet_documents\Service\OiFolderManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for folder pages.
 */
final class FolderController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly OiFolderManagerInterface $folderManager,
    private readonly OiDocumentManagerInterface $documentManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('openintranet_documents.folder_manager'),
      $container->get('openintranet_documents.document_manager'),
    );
  }

  /**
   * Displays a folder.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface $oi_folder
   *   The folder entity.
   *
   * @return array
   *   Render array.
   */
  public function view(OiFolderInterface $oi_folder): array {
    $folders = $this->folderManager->getChildren($oi_folder);
    $documents = $this->documentManager->getByFolder($oi_folder);
    $breadcrumbs = $this->folderManager->getPath($oi_folder);
    $folderStats = $this->folderManager->getFolderStats($folders);

    return [
      '#theme' => 'oi_documents_browser',
      '#folders' => $folders,
      '#documents' => $documents,
      '#current_folder' => $oi_folder,
      '#breadcrumbs' => $breadcrumbs,
      '#folder_stats' => $folderStats,
      '#attached' => [
        'library' => ['openintranet_documents/documents'],
      ],
    ];
  }

  /**
   * Returns the title for a folder page.
   *
   * @param \Drupal\openintranet_documents\OiFolderInterface $oi_folder
   *   The folder entity.
   *
   * @return string
   *   The page title.
   */
  public function title(OiFolderInterface $oi_folder): string {
    return $oi_folder->getName();
  }

}
