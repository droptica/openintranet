<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity\StorageSchema;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Enforces one notification settings row per user.
 */
final class UserNotificationSettingsStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(
    ContentEntityTypeInterface $entity_type,
    $reset = FALSE,
  ): array {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $base_table = $this->storage->getBaseTable();

    $schema[$base_table]['unique keys'] = ($schema[$base_table]['unique keys'] ?? []) + [
      $this->getEntityIndexName($entity_type, 'uid') => ['uid'],
    ];

    return $schema;
  }

}
