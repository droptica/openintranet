<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_documents\OiDocumentInterface;
use Drupal\openintranet_documents\Service\OiDocumentManagerInterface;
use Drupal\openintranet_documents\Service\OiFolderManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the document browser.
 */
final class DocumentBrowserController extends ControllerBase implements ContainerInjectionInterface {

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
   * Displays the main document browser.
   *
   * @return array
   *   Render array.
   */
  public function browse(): array {
    $folders = $this->folderManager->getChildren(NULL);
    $documents = $this->documentManager->getByFolder(NULL);
    $folderStats = $this->folderManager->getFolderStats($folders);

    return [
      '#theme' => 'oi_documents_browser',
      '#folders' => $folders,
      '#documents' => $documents,
      '#current_folder' => NULL,
      '#breadcrumbs' => [],
      '#folder_stats' => $folderStats,
      '#attached' => [
        'library' => ['openintranet_documents/documents'],
      ],
    ];
  }

  /**
   * Downloads a document file.
   *
   * @param \Drupal\openintranet_documents\OiDocumentInterface $oi_document
   *   The document entity.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The file download response.
   */
  public function download(OiDocumentInterface $oi_document): BinaryFileResponse {
    $file = $oi_document->getFile();

    if (!$file) {
      throw new NotFoundHttpException('File not found.');
    }

    $uri = $file->getFileUri();
    $realpath = \Drupal::service('file_system')->realpath($uri);

    if (!$realpath || !file_exists($realpath)) {
      throw new NotFoundHttpException('File not found on disk.');
    }

    $response = new BinaryFileResponse($realpath);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $file->getFilename()
    );

    return $response;
  }

}
