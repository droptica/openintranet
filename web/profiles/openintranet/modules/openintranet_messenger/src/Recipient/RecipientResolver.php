<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Recipient;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_messenger\MessengerContactInterface;
use Drupal\user\UserInterface;

/**
 * Service for resolving entities to RecipientInterface objects.
 */
final class RecipientResolver {

  /**
   * Constructs a RecipientResolver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Resolves an entity to a RecipientInterface.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to resolve.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface|null
   *   The recipient or NULL if the entity type is not supported.
   */
  public function resolve(EntityInterface $entity): ?RecipientInterface {
    if ($entity instanceof UserInterface) {
      return UserRecipient::createFromConfig($entity, $this->configFactory);
    }

    if ($entity instanceof MessengerContactInterface) {
      return new ContactRecipient($entity);
    }

    return NULL;
  }

  /**
   * Resolves multiple entities to RecipientInterface objects.
   *
   * @param array $entities
   *   Array of entities to resolve.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface[]
   *   Array of recipients (entities that couldn't be resolved are skipped).
   */
  public function resolveMultiple(array $entities): array {
    $recipients = [];
    foreach ($entities as $entity) {
      $recipient = $this->resolve($entity);
      if ($recipient !== NULL) {
        $recipients[] = $recipient;
      }
    }
    return $recipients;
  }

  /**
   * Loads and resolves contacts by IDs.
   *
   * @param array $ids
   *   Array of messenger_contact entity IDs.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface[]
   *   Array of recipients.
   */
  public function resolveContactsByIds(array $ids): array {
    if (empty($ids)) {
      return [];
    }

    $contacts = $this->entityTypeManager
      ->getStorage('messenger_contact')
      ->loadMultiple($ids);

    return $this->resolveMultiple($contacts);
  }

  /**
   * Loads and resolves users by IDs.
   *
   * @param array $ids
   *   Array of user entity IDs.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface[]
   *   Array of recipients.
   */
  public function resolveUsersByIds(array $ids): array {
    if (empty($ids)) {
      return [];
    }

    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadMultiple($ids);

    return $this->resolveMultiple($users);
  }

  /**
   * Loads active contacts by taxonomy term.
   *
   * @param int $term_id
   *   The taxonomy term ID for the group.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface[]
   *   Array of recipients.
   */
  public function resolveContactsByTaxonomy(int $term_id): array {
    $ids = $this->entityTypeManager
      ->getStorage('messenger_contact')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('taxonomy', $term_id)
      ->condition('active', TRUE)
      ->execute();

    return $this->resolveContactsByIds($ids);
  }

  /**
   * Gets the configured taxonomy vocabulary for contact grouping.
   *
   * @return string|null
   *   The vocabulary ID or NULL if not configured.
   */
  public function getConfiguredVocabulary(): ?string {
    $config = $this->configFactory->get('openintranet_messenger.settings');
    $vocabulary = $config->get('taxonomy_vocabulary');
    return !empty($vocabulary) ? $vocabulary : NULL;
  }

  /**
   * Loads all active contacts.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface[]
   *   Array of recipients.
   */
  public function resolveAllActiveContacts(): array {
    $ids = $this->entityTypeManager
      ->getStorage('messenger_contact')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('active', TRUE)
      ->execute();

    return $this->resolveContactsByIds($ids);
  }

  /**
   * Loads users by role.
   *
   * @param string $role
   *   The role machine name.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface[]
   *   Array of recipients.
   */
  public function resolveUsersByRole(string $role): array {
    $ids = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('roles', $role)
      ->condition('status', 1)
      ->execute();

    return $this->resolveUsersByIds($ids);
  }

}
