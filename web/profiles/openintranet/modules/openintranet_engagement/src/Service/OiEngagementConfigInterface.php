<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

/**
 * Interface for engagement configuration service.
 */
interface OiEngagementConfigInterface {

  /**
   * Checks if tracking is globally enabled.
   *
   * @return bool
   *   TRUE if tracking is enabled.
   */
  public function isTrackingEnabled(): bool;

  /**
   * Checks if anonymous users should be tracked.
   *
   * @return bool
   *   TRUE if anonymous tracking is enabled.
   */
  public function shouldTrackAnonymous(): bool;

  /**
   * Gets event log retention period in days.
   *
   * @return int
   *   Number of days to retain event logs.
   */
  public function getEventLogRetention(): int;

  /**
   * Checks if entity type tracking is enabled for given operation.
   *
   * @param string $entityType
   *   Entity type ID (node, comment, oi_document, etc.)
   * @param string $operation
   *   Operation: create, view, update, delete.
   *
   * @return bool
   *   TRUE if tracking is enabled for this entity type and operation.
   */
  public function isEntityTypeEnabled(string $entityType, string $operation): bool;

  /**
   * Gets point value for entity type operation.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string $operation
   *   Operation: create, view, update, delete.
   *
   * @return int
   *   Point value (0 means disabled).
   */
  public function getEntityTypeValue(string $entityType, string $operation): int;

  /**
   * Gets scoring thresholds.
   *
   * @return array{recency: int[], frequency: int[], value: int[]}
   *   Array with recency, frequency, and value thresholds.
   */
  public function getThresholds(): array;

  /**
   * Gets new user threshold in days.
   *
   * @return int
   *   Number of days a user is considered "new".
   */
  public function getNewUserThreshold(): int;

  /**
   * Gets all configured entity types with their settings.
   *
   * @return array
   *   Array of entity type configurations.
   */
  public function getEntityTypes(): array;

}
