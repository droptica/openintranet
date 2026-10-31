<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\openintranet_engagement\Service\OiEngagementConfigInterface;
use Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Generic entity hooks for engagement tracking.
 *
 * This single class handles ALL configured entity types via generic hooks.
 * No need for separate hooks per entity type (node, comment, oi_document, etc.)
 */
final class GenericEntityHooks {

  /**
   * Constructs the GenericEntityHooks class.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface $tracker
   *   The tracker service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementConfigInterface $config
   *   The config service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    private readonly OiEngagementTrackerInterface $tracker,
    private readonly OiEngagementConfigInterface $config,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Tracks entity creation for all configured entity types.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The created entity.
   */
  #[Hook('entity_insert')]
  public function onEntityInsert(EntityInterface $entity): void {
    $entityType = $entity->getEntityTypeId();

    if (!$this->config->isEntityTypeEnabled($entityType, 'create')) {
      return;
    }

    // Only track entities with owners.
    if (!$entity instanceof EntityOwnerInterface) {
      return;
    }

    $owner = $entity->getOwner();
    if (!$owner || $owner->isAnonymous()) {
      return;
    }

    $value = $this->config->getEntityTypeValue($entityType, 'create');

    $this->tracker->track("{$entityType}_create", $owner, [
      'entity_type' => $entityType,
      'entity_id' => (int) $entity->id(),
      'value' => $value,
      'data' => [
        'bundle' => $entity->bundle(),
        'label' => $entity->label(),
      ],
    ]);
  }

  /**
   * Tracks entity updates for all configured entity types.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The updated entity.
   */
  #[Hook('entity_update')]
  public function onEntityUpdate(EntityInterface $entity): void {
    $entityType = $entity->getEntityTypeId();

    if (!$this->config->isEntityTypeEnabled($entityType, 'update')) {
      return;
    }

    if ($this->currentUser->isAnonymous()) {
      return;
    }

    // Skip if entity was just created (avoid double tracking).
    if (method_exists($entity, 'getCreatedTime')) {
      $createdTime = $entity->getCreatedTime();
      if ($createdTime && $createdTime > (time() - 60)) {
        return;
      }
    }

    $value = $this->config->getEntityTypeValue($entityType, 'update');

    $this->tracker->track("{$entityType}_update", $this->currentUser->getAccount(), [
      'entity_type' => $entityType,
      'entity_id' => (int) $entity->id(),
      'value' => $value,
    ]);
  }

  /**
   * Tracks entity deletion for all configured entity types.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The deleted entity.
   */
  #[Hook('entity_delete')]
  public function onEntityDelete(EntityInterface $entity): void {
    $entityType = $entity->getEntityTypeId();

    if (!$this->config->isEntityTypeEnabled($entityType, 'delete')) {
      return;
    }

    if ($this->currentUser->isAnonymous()) {
      return;
    }

    $value = $this->config->getEntityTypeValue($entityType, 'delete');

    $this->tracker->track("{$entityType}_delete", $this->currentUser->getAccount(), [
      'entity_type' => $entityType,
      'entity_id' => (int) $entity->id(),
      'value' => $value,
    ]);
  }

}
