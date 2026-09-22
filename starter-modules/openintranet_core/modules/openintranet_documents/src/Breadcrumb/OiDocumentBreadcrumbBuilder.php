<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\openintranet_documents\OiDocumentInterface;
use Drupal\openintranet_documents\OiFolderInterface;
use Drupal\openintranet_documents\Service\OiFolderManagerInterface;

/**
 * Builds breadcrumbs for document and folder pages.
 */
final class OiDocumentBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * Constructs the breadcrumb builder.
   */
  public function __construct(
    private readonly OiFolderManagerInterface $folderManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    $route_name = $route_match->getRouteName();

    // Apply to document/folder routes.
    $applies_to = [
      'entity.oi_folder.canonical',
      'entity.oi_folder.edit_form',
      'entity.oi_folder.delete_form',
      'entity.oi_folder.add_form_in_folder',
      'entity.oi_document.canonical',
      'entity.oi_document.edit_form',
      'entity.oi_document.delete_form',
      'entity.oi_document.add_form_in_folder',
      'entity.oi_document.version_history',
      'openintranet_documents.folder.view',
      'openintranet_documents.download',
    ];

    return in_array($route_name, $applies_to, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    // Start with Home and Documents.
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('Documents'), 'openintranet_documents.browser'));

    // Get the current entity.
    $folder = $route_match->getParameter('oi_folder');
    $document = $route_match->getParameter('oi_document');
    $parent = $route_match->getParameter('parent');
    $contextFolder = $route_match->getParameter('folder');

    // Handle folder routes.
    if ($folder instanceof OiFolderInterface) {
      $ancestors = $this->folderManager->getAncestors($folder);
      foreach ($ancestors as $ancestor) {
        $breadcrumb->addLink(Link::createFromRoute(
          $ancestor->getName(),
          'entity.oi_folder.canonical',
          ['oi_folder' => $ancestor->id()]
        ));
        $breadcrumb->addCacheableDependency($ancestor);
      }
      $breadcrumb->addCacheableDependency($folder);
    }

    // Handle document routes.
    if ($document instanceof OiDocumentInterface) {
      $documentFolder = $document->getFolder();
      if ($documentFolder) {
        $path = $this->folderManager->getPath($documentFolder);
        foreach ($path as $pathFolder) {
          $breadcrumb->addLink(Link::createFromRoute(
            $pathFolder->getName(),
            'entity.oi_folder.canonical',
            ['oi_folder' => $pathFolder->id()]
          ));
          $breadcrumb->addCacheableDependency($pathFolder);
        }
      }
      $breadcrumb->addCacheableDependency($document);
    }

    // Handle add form in folder.
    if ($parent instanceof OiFolderInterface) {
      $path = $this->folderManager->getPath($parent);
      foreach ($path as $pathFolder) {
        $breadcrumb->addLink(Link::createFromRoute(
          $pathFolder->getName(),
          'entity.oi_folder.canonical',
          ['oi_folder' => $pathFolder->id()]
        ));
        $breadcrumb->addCacheableDependency($pathFolder);
      }
    }

    // Handle add document in folder.
    if ($contextFolder instanceof OiFolderInterface) {
      $path = $this->folderManager->getPath($contextFolder);
      foreach ($path as $pathFolder) {
        $breadcrumb->addLink(Link::createFromRoute(
          $pathFolder->getName(),
          'entity.oi_folder.canonical',
          ['oi_folder' => $pathFolder->id()]
        ));
        $breadcrumb->addCacheableDependency($pathFolder);
      }
    }

    return $breadcrumb;
  }

}
