<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

/**
 * Interface for entity type discovery service.
 */
interface EntityTypeDiscoveryInterface {

  /**
   * Returns all trackable content entity types.
   *
   * @return array<string, array{label: string, default_enabled: bool, auto_enabled: bool, default_values: array}>
   *   Array of trackable entity types with metadata.
   */
  public function getTrackableEntityTypes(): array;

  /**
   * Checks if an entity type is trackable.
   *
   * @param string $entityType
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if the entity type can be tracked.
   */
  public function isTrackable(string $entityType): bool;

  /**
   * Gets default tracking values for an entity type.
   *
   * @param string $entityType
   *   The entity type ID.
   *
   * @return array{create: int, view: int, update: int, delete: int}
   *   Default point values for each operation.
   */
  public function getDefaultValues(string $entityType): array;

}
