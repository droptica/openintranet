<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity\StorageSchema;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines query-driven indexes for notification storage.
 */
final class NotificationStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(
    ContentEntityTypeInterface $entity_type,
    $reset = FALSE,
  ): array {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $base_table = $this->storage->getBaseTable();

    $schema[$base_table]['indexes'] += [
      $this->getEntityIndexName($entity_type, 'uid_created') => [
        'uid',
        'created',
      ],
      $this->getEntityIndexName($entity_type, 'status_created') => [
        'status',
        'created',
      ],
      $this->getEntityIndexName($entity_type, 'type_digested') => [
        'type',
        'digested',
      ],
    ];

    return $schema;
  }

}
