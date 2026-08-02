<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\openintranet_access\Entity\OiGroupInterface;

/**
 * Service for audit logging of access-related operations.
 */
final class OiAuditLogger implements OiAuditLoggerInterface {

  /**
   * Constructs the audit logger.
   */
  public function __construct(
    protected readonly LoggerChannelInterface $logger,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function logGroupCreated(OiGroupInterface $group): void {
    $this->logger->notice('Group created: "@name" (ID: @id) by @user', [
      '@name' => $group->getName(),
      '@id' => $group->id(),
      '@user' => $this->getCurrentUserLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function logGroupDeleted(OiGroupInterface $group): void {
    $this->logger->warning('Group deleted: "@name" (ID: @id) by @user', [
      '@name' => $group->getName(),
      '@id' => $group->id(),
      '@user' => $this->getCurrentUserLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function logMemberAdded(OiGroupInterface $group, AccountInterface $user): void {
    $this->logger->notice('User @username added to group "@group" by @actor', [
      '@username' => $this->getUserLabel($user),
      '@group' => $group->getName(),
      '@actor' => $this->getCurrentUserLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function logMemberRemoved(OiGroupInterface $group, AccountInterface $user): void {
    $this->logger->notice('User @username removed from group "@group" by @actor', [
      '@username' => $this->getUserLabel($user),
      '@group' => $group->getName(),
      '@actor' => $this->getCurrentUserLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function logEntityAccessGroupAdded(EntityInterface $entity, OiGroupInterface $group): void {
    $this->logger->notice('Group "@group" granted access to @entity_type @entity_id by @user', [
      '@group' => $group->getName(),
      '@entity_type' => $entity->getEntityTypeId(),
      '@entity_id' => $entity->id(),
      '@user' => $this->getCurrentUserLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function logEntityAccessGroupRemoved(EntityInterface $entity, OiGroupInterface $group): void {
    $this->logger->notice('Group "@group" access revoked from @entity_type @entity_id by @user', [
      '@group' => $group->getName(),
      '@entity_type' => $entity->getEntityTypeId(),
      '@entity_id' => $entity->id(),
      '@user' => $this->getCurrentUserLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function logEntityAccessUserAdded(EntityInterface $entity, int $userId): void {
    $this->logger->notice('User @username granted direct access to @entity_type @entity_id by @actor', [
      '@username' => $this->getUserLabelById($userId),
      '@entity_type' => $entity->getEntityTypeId(),
      '@entity_id' => $entity->id(),
      '@actor' => $this->getCurrentUserLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function logEntityAccessUserRemoved(EntityInterface $entity, int $userId): void {
    $this->logger->notice('User @username direct access revoked from @entity_type @entity_id by @actor', [
      '@username' => $this->getUserLabelById($userId),
      '@entity_type' => $entity->getEntityTypeId(),
      '@entity_id' => $entity->id(),
      '@actor' => $this->getCurrentUserLabel(),
    ]);
  }

  /**
   * Gets a label for the current user.
   *
   * @return string
   *   The user label in format "username (UID: X)".
   */
  protected function getCurrentUserLabel(): string {
    return $this->getUserLabel($this->currentUser);
  }

  /**
   * Gets a label for a user account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return string
   *   The user label in format "username (UID: X)".
   */
  protected function getUserLabel(AccountInterface $account): string {
    $name = $account->getAccountName() ?: $account->getDisplayName();
    return sprintf('%s (UID: %d)', $name, $account->id());
  }

  /**
   * Gets a label for a user by ID.
   *
   * @param int $userId
   *   The user ID.
   *
   * @return string
   *   The user label in format "username (UID: X)" or "Unknown (UID: X)".
   */
  protected function getUserLabelById(int $userId): string {
    try {
      $user = $this->entityTypeManager->getStorage('user')->load($userId);
      if ($user instanceof AccountInterface) {
        return $this->getUserLabel($user);
      }
    }
    catch (\Exception $e) {
      // Fall through to return unknown.
    }

    return sprintf('Unknown (UID: %d)', $userId);
  }

}
