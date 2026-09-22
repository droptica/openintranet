<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Service for managing entity access restrictions.
 */
final class OiAccessManager implements OiAccessManagerInterface {

  /**
   * Constructs the access manager.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected readonly OiAuditLoggerInterface $auditLogger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setAccessGroups(EntityInterface $entity, array $groups): void {
    if ($entity->isNew()) {
      throw new \InvalidArgumentException('Cannot set access on unsaved entity.');
    }

    // Get current group IDs for comparison.
    $current_ids = $this->database->select('oi_access_record', 'oar')
      ->fields('oar', ['target_id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'group')
      ->execute()
      ->fetchCol();
    $current_ids = array_map('intval', $current_ids);

    // Get new group IDs.
    $new_ids = array_map(fn($g) => (int) $g->id(), $groups);

    // Determine added and removed.
    $added_ids = array_diff($new_ids, $current_ids);
    $removed_ids = array_diff($current_ids, $new_ids);

    // Remove existing group grants.
    $this->database->delete('oi_access_record')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'group')
      ->execute();

    // Add new grants.
    foreach ($groups as $group) {
      $this->addAccessRecord($entity, 'group', (int) $group->id());
    }

    $this->invalidateCache($entity);

    // Log removed groups.
    if (!empty($removed_ids)) {
      $removed_groups = \Drupal::entityTypeManager()->getStorage('oi_group')->loadMultiple($removed_ids);
      foreach ($removed_groups as $group) {
        $this->auditLogger->logEntityAccessGroupRemoved($entity, $group);
      }
    }

    // Log added groups.
    foreach ($groups as $group) {
      if (in_array((int) $group->id(), $added_ids, TRUE)) {
        $this->auditLogger->logEntityAccessGroupAdded($entity, $group);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addAccessGroup(EntityInterface $entity, OiGroupInterface $group): void {
    if ($entity->isNew()) {
      throw new \InvalidArgumentException('Cannot set access on unsaved entity.');
    }

    // Check if record already exists to avoid duplicate logging.
    $exists = $this->database->select('oi_access_record', 'oar')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'group')
      ->condition('target_id', (int) $group->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->addAccessRecord($entity, 'group', (int) $group->id());
    $this->invalidateCache($entity);

    // Only log if this is a new record.
    if (!$exists) {
      $this->auditLogger->logEntityAccessGroupAdded($entity, $group);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeAccessGroup(EntityInterface $entity, OiGroupInterface $group): void {
    if ($entity->isNew()) {
      return;
    }

    // Check if record exists before deletion for logging.
    $exists = $this->database->select('oi_access_record', 'oar')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'group')
      ->condition('target_id', (int) $group->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->database->delete('oi_access_record')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'group')
      ->condition('target_id', (int) $group->id())
      ->execute();

    $this->invalidateCache($entity);

    // Only log if record was actually removed.
    if ($exists) {
      $this->auditLogger->logEntityAccessGroupRemoved($entity, $group);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setAccessUsers(EntityInterface $entity, array $user_ids): void {
    if ($entity->isNew()) {
      throw new \InvalidArgumentException('Cannot set access on unsaved entity.');
    }

    // Get current user IDs for comparison.
    $current_ids = $this->database->select('oi_access_record', 'oar')
      ->fields('oar', ['target_id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'user')
      ->execute()
      ->fetchCol();
    $current_ids = array_map('intval', $current_ids);

    // Get new user IDs.
    $new_ids = array_map('intval', $user_ids);

    // Determine added and removed.
    $added_ids = array_diff($new_ids, $current_ids);
    $removed_ids = array_diff($current_ids, $new_ids);

    // Remove existing user grants.
    $this->database->delete('oi_access_record')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'user')
      ->execute();

    // Add new grants.
    foreach ($user_ids as $userId) {
      $this->addAccessRecord($entity, 'user', (int) $userId);
    }

    $this->invalidateCache($entity);

    // Log removed users.
    foreach ($removed_ids as $userId) {
      $this->auditLogger->logEntityAccessUserRemoved($entity, $userId);
    }

    // Log added users.
    foreach ($added_ids as $userId) {
      $this->auditLogger->logEntityAccessUserAdded($entity, $userId);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addAccessUser(EntityInterface $entity, int $user_id): void {
    if ($entity->isNew()) {
      throw new \InvalidArgumentException('Cannot set access on unsaved entity.');
    }

    // Check if record already exists to avoid duplicate logging.
    $exists = $this->database->select('oi_access_record', 'oar')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'user')
      ->condition('target_id', $user_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->addAccessRecord($entity, 'user', $user_id);
    $this->invalidateCache($entity);

    // Only log if this is a new record.
    if (!$exists) {
      $this->auditLogger->logEntityAccessUserAdded($entity, $user_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeAccessUser(EntityInterface $entity, int $user_id): void {
    if ($entity->isNew()) {
      return;
    }

    // Check if record exists before deletion for logging.
    $exists = $this->database->select('oi_access_record', 'oar')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'user')
      ->condition('target_id', $user_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->database->delete('oi_access_record')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', 'user')
      ->condition('target_id', $user_id)
      ->execute();

    $this->invalidateCache($entity);

    // Only log if record was actually removed.
    if ($exists) {
      $this->auditLogger->logEntityAccessUserRemoved($entity, $user_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearAccessRestrictions(EntityInterface $entity): void {
    if ($entity->isNew()) {
      return;
    }

    $this->database->delete('oi_access_record')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->execute();

    $this->invalidateCache($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function copyAccessSettings(EntityInterface $source, EntityInterface $target): void {
    if ($source->isNew() || $target->isNew()) {
      throw new \InvalidArgumentException('Cannot copy access settings for unsaved entities.');
    }

    // Get source records.
    $records = $this->database->select('oi_access_record', 'oar')
      ->fields('oar', ['grant_type', 'target_id'])
      ->condition('entity_type', $source->getEntityTypeId())
      ->condition('entity_id', (int) $source->id())
      ->execute()
      ->fetchAll();

    // Clear target.
    $this->clearAccessRestrictions($target);

    // Copy records to target.
    foreach ($records as $record) {
      $this->addAccessRecord($target, $record->grant_type, (int) $record->target_id);
    }

    $this->invalidateCache($target);
  }

  /**
   * Adds an access record, avoiding duplicates.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $grant_type
   *   The grant type (group or user).
   * @param int $target_id
   *   The target ID (group ID or user ID).
   */
  protected function addAccessRecord(EntityInterface $entity, string $grant_type, int $target_id): void {
    // Check if record already exists.
    $exists = $this->database->select('oi_access_record', 'oar')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (int) $entity->id())
      ->condition('grant_type', $grant_type)
      ->condition('target_id', $target_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($exists) {
      return;
    }

    $this->database->insert('oi_access_record')
      ->fields([
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => (int) $entity->id(),
        'grant_type' => $grant_type,
        'target_id' => $target_id,
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Invalidates cache for an entity after access changes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  protected function invalidateCache(EntityInterface $entity): void {
    $tags = $entity->getCacheTags();
    $tags[] = 'oi_access:' . $entity->getEntityTypeId() . ':' . $entity->id();

    $this->cacheTagsInvalidator->invalidateTags($tags);

    // For nodes, trigger node access rebuild for this node.
    // The node_access_acquire_grants function handles updating the node_access table.
    if ($entity instanceof NodeInterface && function_exists('node_access_acquire_grants')) {
      \node_access_acquire_grants($entity);
    }
  }

}
