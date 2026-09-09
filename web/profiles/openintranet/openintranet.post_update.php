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

/**
 * Repoints the must-read report on the user -> flagging relationship.
 */
function openintranet_post_update_fix_must_read_report_relationship(): void {
  $view = \Drupal::configFactory()->getEditable('views.view.must_read_report_details');
  if ($view->isNew()) {
    return;
  }
  foreach (['page_1', 'page_4'] as $display) {
    $key = "display.$display.display_options.relationships.flag_user_content_rel";
    $rel = $view->get($key);
    if (!$rel) {
      continue;
    }
    unset($rel['flag']);
    $view->set($key, [
      'table' => 'users_field_data',
      'field' => 'flagging_read',
      'plugin_id' => 'standard',
    ] + $rel);
  }
  $view->save();
}
