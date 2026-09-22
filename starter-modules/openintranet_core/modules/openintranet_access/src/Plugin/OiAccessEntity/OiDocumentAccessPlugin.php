<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Plugin\OiAccessEntity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access plugin for OI Document entities.
 *
 * @OiAccessEntityPlugin(
 *   id = "oi_document",
 *   label = @Translation("Documents"),
 *   entity_type = "oi_document"
 * )
 */
final class OiDocumentAccessPlugin extends OiAccessEntityPluginBase {

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    if ($entity->getEntityTypeId() !== 'oi_document') {
      return FALSE;
    }

    $config = $this->configFactory->get('openintranet_access.settings');
    return (bool) $config->get('enabled_entity_types.oi_document');
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessFormRoute(): string {
    return 'openintranet_access.document_access_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessFormRouteParameters(EntityInterface $entity): array {
    return ['oi_document' => $entity->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, AccountInterface $account, string $operation): ?bool {
    // Check if folder access inheritance is enabled.
    $config = $this->configFactory->get('openintranet_access.settings');
    if (!$config->get('behavior.inherit_folder_access')) {
      return NULL;
    }

    // Check if entity has a getFolder method (OiDocument).
    if (!method_exists($entity, 'getFolder')) {
      return NULL;
    }

    /** @var \Drupal\openintranet_documents\OiDocumentInterface $entity */
    $folder = $entity->getFolder();

    if ($folder === NULL) {
      return NULL;
    }

    // If folder has restrictions, check folder access.
    if ($this->accessChecker->hasRestrictions($folder)) {
      $folderAccess = $this->accessChecker->checkEntityAccess($folder, $account, $operation);
      if ($folderAccess === FALSE) {
        // No access to folder means no access to document.
        return FALSE;
      }
    }

    // Defer to standard document access check.
    return NULL;
  }

}
