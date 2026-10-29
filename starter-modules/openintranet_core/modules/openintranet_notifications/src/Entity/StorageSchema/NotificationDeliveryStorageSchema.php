<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity\StorageSchema;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines query-driven indexes and uniqueness for notification delivery storage.
 */
final class NotificationDeliveryStorageSchema extends SqlContentEntityStorageSchema {

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
      $this->getEntityIndexName($entity_type, 'notif_status_next') => [
        'notification_id',
        'status',
        'next_attempt',
      ],
      $this->getEntityIndexName($entity_type, 'status_id') => [
        'status',
        'id',
      ],
    ];

    // The key is derived from notification, recipient and channel, so a second
    // row with the same key would be a duplicate send. The database enforces
    // it; NULL keys stay allowed for rows created without one.
    $schema[$base_table]['unique keys'] = ($schema[$base_table]['unique keys'] ?? []) + [
      $this->getEntityIndexName($entity_type, 'idempotency_key') => [
        'idempotency_key',
      ],
    ];

    return $schema;
  }

}
