<?php

/**
 * @file
 * Open Intranet install tasks.
 */

declare(strict_types=1);

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\user\Entity\User;
use Drupal\openintranet\Form\RecipesForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Implements hook_install_tasks().
 */
function openintranet_install_tasks(&$install_state): array {
  // Set a path for private files.
  $settings_path = DRUPAL_ROOT . '/' . 'sites/default/settings.php';

  if (is_writable($settings_path)) {
    $contents = file_get_contents($settings_path);

    $contents = str_replace(
      "# \$settings['file_private_path'] = '';",
      "\$settings['file_private_path'] = 'sites/private_files';",
      $contents
    );

    $private_files_dir = DRUPAL_ROOT . '/sites/private_files';
    if (!is_dir($private_files_dir)) {
      mkdir($private_files_dir, 0755, TRUE);
    }

    file_put_contents($settings_path, $contents);
  }
  else {
    error_log('settings.php is not writable.');
  }

  // Check for demo content parameter from drush.
  if (!empty($install_state['forms']['install_configure_form']['enable_demo_content'])) {
    if (!isset($install_state['parameters'])) {
      $install_state['parameters'] = [];
    }
    $install_state['parameters']['recipes'] = ['default_content'];
  }

  // Force content recipe to run if selected.
  $has_content = !empty($install_state['parameters']['recipes']) &&
    in_array('default_content', $install_state['parameters']['recipes']);

  $tasks = [
    'openintranet_apply_core_recipe' => [
      'display_name' => t('Install Open Intranet'),
      'type' => 'batch',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
      'function' => 'openintranet_apply_core_recipe',
    ],
  ];

  // Only show recipes form if demo content not enabled via drush.
  if (empty($install_state['forms']['install_configure_form']['enable_demo_content'])) {
    $tasks['openintranet_choose_recipes'] = [
      'display_name' => t('Choose content'),
      'type' => 'form',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
      // @phpstan-ignore-next-line
      'function' => RecipesForm::class,
    ];
  }

  // Add content recipe task if selected.
  if ($has_content) {
    $tasks['openintranet_apply_content_recipe'] = [
      'display_name' => t('Install demo content'),
      'type' => 'batch',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
      'function' => 'openintranet_apply_content_recipe',
    ];
  }

  $tasks['openintranet_install_finished'] = [
    'display_name' => t('Finishing up'),
    'type' => 'batch',
    'function' => 'openintranet_install_finished',
  ];

  return $tasks;
}

/**
 * Implements hook_install_tasks_alter().
 */
function openintranet_install_tasks_alter(array &$tasks, array $install_state): void {
  // Insert our tasks after the configure form.
  $configure_form_position = array_search('install_configure_form', array_keys($tasks), TRUE);
  if ($configure_form_position !== FALSE) {
    $tasks_before = array_slice($tasks, 0, $configure_form_position + 1, TRUE);
    $tasks_after = array_slice($tasks, $configure_form_position + 1, NULL, TRUE);

    // Get our tasks.
    $our_tasks = openintranet_install_tasks($install_state);

    $tasks = $tasks_before + $our_tasks + $tasks_after;
  }

  // Set the language code to English.
  $GLOBALS['install_state']['parameters'] += ['langcode' => 'en'];
  $tasks['install_select_language']['run'] = INSTALL_TASK_SKIP;
}

/**
 * Wrapper for recipe operations that handles non-critical errors.
 */
function openintranet_recipe_operation(array $operation, array &$context): void {
  try {
    [$class, $method] = $operation[0];
    $args = $operation[1];
    $class::$method(...$args);
  }
  catch (\Exception $e) {
    error_log($e->getMessage());
  }
}

/**
 * Applies the core Open Intranet recipe.
 */
function openintranet_apply_core_recipe(array &$install_state): array {
  $recipe_dir = DRUPAL_ROOT . '/../recipes/openintranet';
  if (!is_dir($recipe_dir)) {
    error_log("Open Intranet recipe directory not found at: $recipe_dir");
    return [];
  }

  try {
    $recipe = Recipe::createFromDirectory($recipe_dir);
    $recipe_operations = RecipeRunner::toBatchOperations($recipe);

    // Wrap each operation with our error handler.
    $operations = [];
    foreach ($recipe_operations as $operation) {
      $operations[] = [
        'openintranet_recipe_operation',
        [$operation],
      ];
    }

    $operations[] = ['openintranet_download_webform_libraries', []];

    return [
      'operations' => $operations,
      'title' => t('Installing Open Intranet'),
      'progress_message' => t('Installing Open Intranet... @current out of @total steps.'),
      'finished' => 'openintranet_recipe_finished',
    ];
  }
  catch (\Exception $e) {
    error_log("Error applying Open Intranet recipe: " . $e->getMessage());
    return [];
  }
}

/**
 * Applies the content recipe if selected.
 */
function openintranet_apply_content_recipe(array &$install_state): array {
  // Check if recipe is selected either via drush or form.
  if (empty($install_state['parameters']['recipes']) &&
    empty($install_state['forms']['install_configure_form']['enable_demo_content'])) {
    return [];
  }

  $recipe_dir = DRUPAL_ROOT . '/../recipes/default_content';
  if (!is_dir($recipe_dir)) {
    return [];
  }

  try {
    $recipe = Recipe::createFromDirectory($recipe_dir);
    $operations = RecipeRunner::toBatchOperations($recipe);

    $operations[] = ['openintranet_import_book_structure', []];
    $operations[] = ['openintranet_index_content', []];
    $operations[] = ['openintranet_index_site_map', []];
    $operations[] = ['openintranet_post_install_clean_up', []];

    // Directories to delete.
    $dirs = [
      DRUPAL_ROOT . '/private:',
      DRUPAL_ROOT . '/public:',
    ];

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    foreach ($dirs as $dir) {
      if (!is_dir($dir)) {
        continue;
      }

      $file_system->deleteRecursive($dir);
    }

    // Return batch array.
    return [
      'operations' => $operations,
      'title' => t('Installing demo content'),
      'init_message' => t('Starting demo content installation...'),
      'progress_message' => t('Installing demo content... @current out of @total steps.'),
      'error_message' => t('An error occurred. The installation will continue.'),
      'finished' => 'openintranet_content_recipe_finished',
    ];
  }
  catch (\Exception $e) {
    error_log($e->getMessage());
    return [];
  }
}

/**
 * Updates event dates to random dates relative to now.
 *
 * Most events get dates 6-7 months in the future (upcoming).
 * Events with specific UUIDs get dates 3-9 months in the past (archived).
 */
function openintranet_update_event_dates(): void {
  // UUIDs of events that should appear as archived (past dates).
  $archived_event_uuids = [
    '9050bf63-c6b2-4443-8547-74174ea9673e',
    'fa796915-6897-48e0-a8aa-74a0ab296fa0',
    'd1796e6c-554c-458b-8c8c-9d98213b39c2',
  ];

  try {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'event')
      ->condition('status', 1)
      ->accessCheck(FALSE);
    $nids = $query->execute();

    if (empty($nids)) {
      return;
    }

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($nids);

    // Base date for upcoming events: 6 months from now.
    $future_base = new \Drupal\Core\Datetime\DrupalDateTime();
    $future_base->modify('+6 months');

    // Base date for archived events: 3 months ago.
    $past_base = new \Drupal\Core\Datetime\DrupalDateTime();
    $past_base->modify('-3 months');

    foreach ($nodes as $node) {
      $event_date = $node->get('field_event_date')->getValue();
      if (empty($event_date)) {
        continue;
      }

      $is_archived = in_array($node->uuid(), $archived_event_uuids, TRUE);
      $base_date = $is_archived ? clone $past_base : clone $future_base;

      $needs_update = FALSE;
      foreach ($event_date as $key => $date) {
        if (!empty($date['value'])) {
          // Random offset: 0-180 days for archived, 0-30 days for upcoming.
          $random_days = $is_archived ? mt_rand(0, 180) : mt_rand(0, 30);
          $date_obj = clone $base_date;
          // Archived events go further into the past.
          $date_obj->modify($is_archived ? "-$random_days days" : "+$random_days days");

          // Random hour between 9 and 17.
          $random_hour = mt_rand(9, 17);
          $date_obj->setTime($random_hour, 0);

          $event_date[$key]['value'] = $date_obj->format('Y-m-d\TH:i:s');
          $needs_update = TRUE;

          // Set end date to 1-3 hours after start date.
          if (!empty($date['end_value'])) {
            $end_date = clone $date_obj;
            $random_hours = mt_rand(1, 3);
            $end_date->modify("+$random_hours hours");
            $event_date[$key]['end_value'] = $end_date->format('Y-m-d\TH:i:s');
          }
        }
      }
      if ($needs_update) {
        $node->set('field_event_date', $event_date);
        $node->save();
      }
    }
  }
  catch (\Exception $e) {
    error_log("Error updating event dates: " . $e->getMessage());
  }
}

/**
 * Finished callback for content recipe.
 */
function openintranet_content_recipe_finished($success, $results, $operations) {
  if ($success) {
    try {
      // Clear all caches to ensure content is visible.
      drupal_flush_all_caches();

      // Update entity type definitions.
      $entityTypeManager = \Drupal::entityTypeManager();
      $entityTypeManager->clearCachedDefinitions();

      // Update entity schema if needed.
      $entityUpdateManager = \Drupal::entityDefinitionUpdateManager();
      $pendingUpdates = $entityUpdateManager->getChangeList();
      if (!empty($pendingUpdates)) {
        foreach ($entityUpdateManager->getChangeSummary() as $entity_type_id => $changes) {
          foreach ($changes as $change) {
            error_log("Applying entity schema update for $entity_type_id: $change");
          }
        }
        $entityUpdateManager->getChangeList();
      }

      // Update event dates
      openintranet_update_event_dates();
    }
    catch (\Exception $e) {
      error_log("Non-critical error during content recipe cleanup: " . $e->getMessage());
    }
  }
  else {
    error_log("Content recipe application failed");
    if (!empty($results['errors'])) {
      foreach ($results['errors'] as $error) {
        error_log($error);
      }
    }
  }
}

/**
 * Batch operation finished callback.
 */
function openintranet_recipe_finished($success, $results, $operations) {
  if ($success) {
    error_log("Recipe applied successfully");
  }
  else {
    error_log("Recipe application failed");
    if (!empty($results['errors'])) {
      foreach ($results['errors'] as $error) {
        error_log($error);
      }
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form.
 */
function openintranet_form_install_configure_form_alter(&$form, FormStateInterface $form_state): void {
  // Hide the update notification setting (we enable update module later).
  $form['update_notifications']['#access'] = FALSE;
}

/**
 * Finish callback for the installer.
 */
function openintranet_install_finished(&$install_state) {
  \Drupal::messenger()->deleteAll();

  try {
    // Switch to openintranet.
    \Drupal::service('theme_installer')->install(['openintranet_theme']);
    \Drupal::service('theme_installer')->install(['gin']);
    \Drupal::configFactory()
      ->getEditable('system.theme')
      ->set('default', 'openintranet_theme')
      ->set('admin', 'gin')
      ->save();
  }
  catch (\Exception $e) {
    error_log($e->getMessage());
  }

  // Load user 1 and log them in.
  $user = User::load(1);
  if ($user) {
    user_login_finalize($user);
  }
}

/**
 * Custom submit handler to update the site email.
 */
function openintranet_update_site_mail(array &$form, FormStateInterface $form_state): void {
  \Drupal::configFactory()
    ->getEditable('system.site')
    ->set('mail', $form_state->getValue(['admin_account', 'account', 'mail']))
    ->save();
}

/**
 * Submit handler to store form values.
 */
function openintranet_install_configure_form_submit(array &$form, FormStateInterface $form_state): void {
  global $install_state;

  // Store the values in install_state for later use.
  $install_state['forms']['install_configure_form'] = [
    'account' => [
      'name' => $form_state->getValue(['admin_account', 'account', 'name']),
      'mail' => $form_state->getValue(['admin_account', 'account', 'mail']),
      'pass' => $form_state->getValue(['admin_account', 'account', 'pass']),
    ],
  ];
}

/**
 * Indexes the content after recipe application.
 */
function openintranet_index_content($context): void {
  try {
    // Execute drush sapi-i command.
    $process = new Process(['drush', 'sapi-i', '--yes']);
    $process->setWorkingDirectory(DRUPAL_ROOT);
    $process->run();

    if (!$process->isSuccessful()) {
      throw new \Exception($process->getErrorOutput());
    }

    $context['message'] = t('Content indexed successfully');
  }
  catch (\Exception $e) {
    \Drupal::messenger()->addError(t('Error indexing content: @error', ['@error' => $e->getMessage()]));
  }
}

/**
 * Indexes the site map after recipe application.
 */
function openintranet_index_site_map($context): void {
  try {
    // Execute drush ssg command.
    $process = new Process(['drush', 'ssg', '--yes']);
    $process->setWorkingDirectory(DRUPAL_ROOT);
    $process->run();

    if (!$process->isSuccessful()) {
      throw new \Exception($process->getErrorOutput());
    }

    $context['message'] = t('Sitemap indexed successfully');
  }
  catch (\Exception $e) {
    \Drupal::messenger()->addError(t('Error indexing sitemap: @error', ['@error' => $e->getMessage()]));
  }
}

/**
 * Cleans up after installation by deleting specific directories.
 */
function openintranet_post_install_clean_up($context): void {
  // Directories to delete.
  $dirs = [
    DRUPAL_ROOT . '/private:',
    DRUPAL_ROOT . '/public:',
  ];

  /** @var \Drupal\Core\File\FileSystemInterface $file_system */
  $file_system = \Drupal::service('file_system');

  foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
      continue;
    }

    $file_system->deleteRecursive($dir);
  }

  $context['message'] = t('Cleaning after installation.');
}

/**
 * Webform libraries download recipe application.
 */
function openintranet_download_webform_libraries($context): void {
  try {
    // Execute drush webform:libraries:download command.
    $process = new Process(['drush', 'webform:libraries:download', '--yes']);
    $process->setWorkingDirectory(DRUPAL_ROOT);
    $process->run();

    if (!$process->isSuccessful()) {
      throw new \Exception($process->getErrorOutput());
    }

    $context['message'] = t('Webform libraries downloaded successfully');
  }
  catch (\Exception $e) {
    \Drupal::messenger()->addError(t('Error downloading libraries: @error', ['@error' => $e->getMessage()]));
  }
}

/**
 * Batch operation to import book structure after content import.
 *
 * @param array $context
 *   Batch context.
 */
function openintranet_import_book_structure(array &$context): void {
  $book_structure_file = DRUPAL_ROOT . '/../recipes/default_content/book/book.structure.yml';

  if (!file_exists($book_structure_file)) {
    $context['message'] = t('Book structure file not found. Skipping book structure import.');
    return;
  }

  try {
    $book_structure = Yaml::parse(file_get_contents($book_structure_file));

    /** @var \Drupal\book\BookManagerInterface $book_manager */
    $book_manager = \Drupal::service('book.manager');
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */

    $uuid_map = [];

    foreach ($book_structure as $link) {
      $converted_link = [];

      foreach ($link as $key => $uuid) {
        if ($uuid === null) {
          $converted_link[$key] = 0;
          continue;
        }

        if (!in_array($key, ['nid', 'bid', 'pid']) || empty($uuid)) {
          $converted_link[$key] = $uuid;
          continue;
        }

        if (!isset($uuid_map[$uuid])) {
          $node = $entity_repository->loadEntityByUuid('node', $uuid);
          if ($node) {
            $uuid_map[$uuid] = $node->id();
          } else {
            $uuid_map[$uuid] = 0;
          }
        }

        $converted_link[$key] = $uuid_map[$uuid];
      }

      $defaults = [
        'has_children' => 0,
        'weight' => 0,
        'depth' => 1,
      ];

      foreach ($defaults as $key => $default_value) {
        if (!isset($converted_link[$key])) {
          $converted_link[$key] = $default_value;
        }
      }

      $book_manager->saveBookLink($converted_link, TRUE);
    }

    $context['message'] = t('Book structure imported successfully.');
  }
  catch (\Exception $e) {
    error_log($e->getMessage());
  }
}
