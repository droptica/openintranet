<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\openintranet_documents\Service\OiDocumentManagerInterface;
use Drupal\openintranet_documents\Service\OiFolderManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for document search.
 */
final class SearchController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Items per page.
   */
  private const ITEMS_PER_PAGE = 20;

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly OiFolderManagerInterface $folderManager,
    private readonly OiDocumentManagerInterface $documentManager,
    private readonly PagerManagerInterface $pagerManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('openintranet_documents.folder_manager'),
      $container->get('openintranet_documents.document_manager'),
      $container->get('pager.manager'),
    );
  }

  /**
   * Displays search results.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Render array.
   */
  public function search(Request $request): array {
    $query = trim($request->query->get('q', ''));
    $page = max(0, (int) $request->query->get('page', 0));

    if (empty($query)) {
      return [
        '#theme' => 'oi_documents_search',
        '#query' => '',
        '#results' => [],
        '#total' => 0,
        '#pager' => [],
        '#attached' => [
          'library' => ['openintranet_documents/documents'],
        ],
      ];
    }

    // Search folders.
    $folderResults = $this->folderManager->search($query, 1000, 0);
    $folders = $folderResults['results'];
    $folderTotal = $folderResults['total'];

    // Search documents.
    $documentResults = $this->documentManager->search($query, 1000, 0);
    $documents = $documentResults['results'];
    $documentTotal = $documentResults['total'];

    // Combine results with type indicator.
    $combined = [];
    foreach ($folders as $folder) {
      $combined[] = [
        'type' => 'folder',
        'entity' => $folder,
        'name' => $folder->getName(),
        'updated' => $folder->getChangedTime(),
      ];
    }
    foreach ($documents as $document) {
      $combined[] = [
        'type' => 'document',
        'entity' => $document,
        'name' => $document->getTitle(),
        'updated' => $document->getChangedTime(),
      ];
    }

    // Sort combined by name.
    usort($combined, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    $total = count($combined);

    // Paginate.
    $offset = $page * self::ITEMS_PER_PAGE;
    $paged = array_slice($combined, $offset, self::ITEMS_PER_PAGE);

    // Initialize pager.
    $this->pagerManager->createPager($total, self::ITEMS_PER_PAGE);

    return [
      '#theme' => 'oi_documents_search',
      '#query' => $query,
      '#results' => $paged,
      '#total' => $total,
      '#folder_count' => $folderTotal,
      '#document_count' => $documentTotal,
      '#pager' => [
        '#type' => 'pager',
      ],
      '#attached' => [
        'library' => ['openintranet_documents/documents'],
      ],
      '#cache' => [
        'contexts' => ['url.query_args:q', 'url.query_args:page'],
      ],
    ];
  }

}
