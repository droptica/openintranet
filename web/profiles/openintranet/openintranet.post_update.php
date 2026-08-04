<?php

/**
 * @file
 * Post-update functions for the Open Intranet profile.
 */

declare(strict_types=1);

/**
 * Removes the legacy Dashboard alias for the Open Intranet admin section.
 */
function openintranet_post_update_remove_dashboard_admin_alias(): void {
  $entity_type_manager = \Drupal::entityTypeManager();
  if (!$entity_type_manager->hasDefinition('path_alias')) {
    return;
  }

  $storage = $entity_type_manager->getStorage('path_alias');
  $alias_ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('path', '/admin/dashboard')
    ->condition('alias', '/admin/openintranet')
    ->execute();

  if ($alias_ids) {
    $storage->delete($storage->loadMultiple($alias_ids));
  }
}
