<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Plugin\OiAccessEntity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access plugin for OI Folder entities.
 *
 * @OiAccessEntityPlugin(
 *   id = "oi_folder",
 *   label = @Translation("Folders"),
 *   entity_type = "oi_folder"
 * )
 */
final class OiFolderAccessPlugin extends OiAccessEntityPluginBase {

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    if ($entity->getEntityTypeId() !== 'oi_folder') {
      return FALSE;
    }

    $config = $this->configFactory->get('openintranet_access.settings');
    return (bool) $config->get('enabled_entity_types.oi_folder');
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessFormRoute(): string {
    return 'openintranet_access.folder_access_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessFormRouteParameters(EntityInterface $entity): array {
    return ['oi_folder' => $entity->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, AccountInterface $account, string $operation): ?bool {
    // Check parent folder access.
    if (!method_exists($entity, 'getParent')) {
      return NULL;
    }

    /** @var \Drupal\openintranet_documents\OiFolderInterface $entity */
    $parent = $entity->getParent();

    if ($parent === NULL) {
      return NULL;
    }

    // If parent folder has restrictions, check parent access.
    if ($this->accessChecker->hasRestrictions($parent)) {
      $parentAccess = $this->accessChecker->checkEntityAccess($parent, $account, $operation);
      if ($parentAccess === FALSE) {
        // No access to parent means no access to child.
        return FALSE;
      }
    }

    // Defer to standard folder access check.
    return NULL;
  }

}
