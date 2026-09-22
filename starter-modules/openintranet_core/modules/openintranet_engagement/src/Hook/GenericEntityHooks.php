<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Hook;

use Drupal\Core\Cache\CacheBackendInterface;
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
   * View tracking cooldown in seconds (prevents spam).
   */
  private const VIEW_COOLDOWN = 300;

  /**
   * Constructs the GenericEntityHooks class.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface $tracker
   *   The tracker service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementConfigInterface $config
   *   The config service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    private readonly OiEngagementTrackerInterface $tracker,
    private readonly OiEngagementConfigInterface $config,
    private readonly AccountProxyInterface $currentUser,
    private readonly CacheBackendInterface $cache,
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

  /**
   * Tracks entity view for all configured entity types.
   *
   * Includes rate limiting to prevent view spam.
   *
   * @param array &$build
   *   The render array.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The viewed entity.
   * @param mixed $display
   *   The display.
   * @param string $view_mode
   *   The view mode.
   */
  #[Hook('entity_view')]
  public function onEntityView(array &$build, EntityInterface $entity, $display, $view_mode): void {
    // Only track 'full' view mode to avoid counting teasers, references, etc.
    if ($view_mode !== 'full') {
      return;
    }

    $entityType = $entity->getEntityTypeId();

    if (!$this->config->isEntityTypeEnabled($entityType, 'view')) {
      return;
    }

    if ($this->currentUser->isAnonymous()) {
      return;
    }

    $userId = (int) $this->currentUser->id();
    $entityId = (int) $entity->id();

    // Rate limiting: max 1 view per entity per user per VIEW_COOLDOWN seconds.
    if (!$this->shouldTrackView($userId, $entityType, $entityId)) {
      return;
    }

    $value = $this->config->getEntityTypeValue($entityType, 'view');

    $this->tracker->track("{$entityType}_view", $this->currentUser->getAccount(), [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      'value' => $value,
    ]);
  }

  /**
   * Checks if view should be tracked (rate limiting).
   *
   * Prevents tracking the same entity view multiple times in quick succession.
   *
   * @param int $userId
   *   The user ID.
   * @param string $entityType
   *   The entity type.
   * @param int $entityId
   *   The entity ID.
   *
   * @return bool
   *   TRUE if should track, FALSE if within cooldown period.
   */
  private function shouldTrackView(int $userId, string $entityType, int $entityId): bool {
    $cacheKey = "engagement_view:{$userId}:{$entityType}:{$entityId}";

    // Check if already tracked recently.
    if ($this->cache->get($cacheKey)) {
      return FALSE;
    }

    // Mark as tracked with cooldown expiry.
    $this->cache->set($cacheKey, TRUE, time() + self::VIEW_COOLDOWN);

    return TRUE;
  }

}
