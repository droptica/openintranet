<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the OI Document entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class OiDocumentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view oi_document'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit oi_document'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete oi_document'),
      'delete revision' => AccessResult::allowedIfHasPermission($account, 'delete oi_document revision'),
      'view all revisions', 'view revision' => AccessResult::allowedIfHasPermissions($account, ['view oi_document revision', 'view oi_document']),
      'revert' => AccessResult::allowedIfHasPermissions($account, ['revert oi_document revision', 'edit oi_document']),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create oi_document', 'administer oi_document'], 'OR');
  }

}
