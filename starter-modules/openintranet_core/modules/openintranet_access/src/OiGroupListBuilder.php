<?php

declare(strict_types=1);

namespace Drupal\openintranet_access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\openintranet_access\Service\OiGroupManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the OI Group entity type.
 */
final class OiGroupListBuilder extends EntityListBuilder {

  /**
   * The group manager service.
   */
  protected OiGroupManagerInterface $groupManager;

  /**
   * Constructs a new OiGroupListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\openintranet_access\Service\OiGroupManagerInterface $group_manager
   *   The group manager service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    OiGroupManagerInterface $group_manager,
  ) {
    parent::__construct($entity_type, $storage);
    $this->groupManager = $group_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('openintranet_access.group_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['name'] = $this->t('Name');
    $header['parent'] = $this->t('Parent');
    $header['members'] = $this->t('Members');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\openintranet_access\Entity\OiGroupInterface $entity */

    // Build name with indentation based on depth.
    $depth = $entity->getDepth();
    $indent = str_repeat('— ', $depth);

    $row['name'] = [
      'data' => [
        '#markup' => $indent . Link::createFromRoute(
          $entity->getName(),
          'entity.oi_group.canonical',
          ['oi_group' => $entity->id()]
        )->toString(),
      ],
    ];

    $parent = $entity->getParent();
    $row['parent'] = $parent ? $parent->getName() : '-';

    // Count members.
    $memberCount = $this->groupManager->getMemberCount($entity);
    $row['members'] = $memberCount;

    $row['status'] = $entity->isActive() ? $this->t('Active') : $this->t('Inactive');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function load(): array {
    // Load all groups and sort by hierarchy.
    $entities = parent::load();
    return $this->sortByHierarchy($entities);
  }

  /**
   * Sorts entities by hierarchy (parents before children, alphabetically).
   *
   * @param array $entities
   *   Array of entities to sort.
   *
   * @return array
   *   Sorted array of entities.
   */
  protected function sortByHierarchy(array $entities): array {
    $sorted = [];
    $byParent = [];

    // Group by parent ID.
    foreach ($entities as $entity) {
      /** @var \Drupal\openintranet_access\Entity\OiGroupInterface $entity */
      $parentId = $entity->getParentId() ?? 0;
      $byParent[$parentId][$entity->id()] = $entity;
    }

    // Sort each level alphabetically.
    foreach ($byParent as &$level) {
      uasort($level, fn($a, $b) => strcasecmp($a->getName(), $b->getName()));
    }

    // Build sorted list starting from root (parent_id = 0).
    $this->addChildrenToSorted($sorted, $byParent, 0);

    return $sorted;
  }

  /**
   * Recursively adds children to the sorted list.
   *
   * @param array $sorted
   *   The sorted list being built.
   * @param array $byParent
   *   Entities grouped by parent ID.
   * @param int $parentId
   *   Current parent ID to process.
   */
  protected function addChildrenToSorted(array &$sorted, array &$byParent, int $parentId): void {
    if (!isset($byParent[$parentId])) {
      return;
    }

    foreach ($byParent[$parentId] as $id => $entity) {
      $sorted[$id] = $entity;
      $this->addChildrenToSorted($sorted, $byParent, (int) $id);
    }
  }

}
