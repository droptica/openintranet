<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Discovers trackable entity types in the system.
 */
final class EntityTypeDiscovery implements EntityTypeDiscoveryInterface {

  /**
   * Entity types that are enabled by default.
   */
  private const DEFAULT_ENABLED = [
    'node' => ['create' => 10, 'view' => 1, 'update' => 5, 'delete' => 1],
    'comment' => ['create' => 3, 'view' => 0, 'update' => 2, 'delete' => 1],
  ];

  /**
   * Entity types auto-enabled when their module is active.
   */
  private const AUTO_ENABLED = [
    'oi_document' => [
      'module' => 'openintranet_documents',
      'values' => ['create' => 8, 'view' => 2, 'update' => 4, 'delete' => 1],
    ],
    'oi_folder' => [
      'module' => 'openintranet_documents',
      'values' => ['create' => 5, 'view' => 1, 'update' => 3, 'delete' => 1],
    ],
  ];

  /**
   * Constructs the EntityTypeDiscovery service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getTrackableEntityTypes(): array {
    $types = [];

    foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
      // Only content entities.
      if (!$definition->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }

      // Skip config entities and other non-user-facing entities.
      $skipTypes = ['user', 'file', 'block_content', 'menu_link_content', 'shortcut', 'path_alias'];
      if (in_array($id, $skipTypes, TRUE)) {
        continue;
      }

      $types[$id] = [
        'label' => (string) $definition->getLabel(),
        'default_enabled' => isset(self::DEFAULT_ENABLED[$id]),
        'auto_enabled' => $this->isAutoEnabled($id),
        'default_values' => $this->getDefaultValues($id),
      ];
    }

    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function isTrackable(string $entityType): bool {
    $definition = $this->entityTypeManager->getDefinition($entityType, FALSE);

    if (!$definition) {
      return FALSE;
    }

    return $definition->entityClassImplements(ContentEntityInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValues(string $entityType): array {
    if (isset(self::DEFAULT_ENABLED[$entityType])) {
      return self::DEFAULT_ENABLED[$entityType];
    }

    if (isset(self::AUTO_ENABLED[$entityType])) {
      return self::AUTO_ENABLED[$entityType]['values'];
    }

    // Default values for unknown entity types.
    return ['create' => 5, 'view' => 1, 'update' => 3, 'delete' => 1];
  }

  /**
   * Checks if entity type should be auto-enabled.
   *
   * @param string $entityType
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if auto-enabled.
   */
  private function isAutoEnabled(string $entityType): bool {
    if (!isset(self::AUTO_ENABLED[$entityType])) {
      return FALSE;
    }

    $module = self::AUTO_ENABLED[$entityType]['module'];
    return $this->moduleHandler->moduleExists($module);
  }

}
