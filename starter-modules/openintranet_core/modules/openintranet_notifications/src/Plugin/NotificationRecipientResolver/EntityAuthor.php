<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationRecipientResolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationRecipientResolver;
use Drupal\openintranet_notifications\Resolver\NotificationRecipientResolverBase;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Resolves the author of the source entity as the single recipient.
 *
 * Reads the entity from $context[entity_key]. When the entity implements
 * EntityOwnerInterface its owner is used; otherwise the configured
 * author_field entity-reference is read. Missing and blocked users are skipped.
 */
#[NotificationRecipientResolver(
  id: 'entity_author',
  label: new TranslatableMarkup('Entity author'),
  description: new TranslatableMarkup('Resolves the author (owner) of the source entity as the recipient.'),
)]
final class EntityAuthor extends NotificationRecipientResolverBase {

  /**
   * {@inheritdoc}
   */
  public function resolve(array $context): array {
    $entity_key = $this->configuration['entity_key'] ?? 'entity';
    $entity = $context[$entity_key] ?? NULL;
    if (!$entity instanceof EntityInterface) {
      return [];
    }

    $author = $entity instanceof EntityOwnerInterface
      ? $entity->getOwner()
      : $this->loadAuthorFromField($entity);
    if (!$author instanceof UserInterface) {
      return [];
    }

    $recipient = $this->buildUserRecipient($author);
    return $recipient === NULL ? [] : [$recipient];
  }

  /**
   * Loads the author from the configured entity-reference field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The source entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   The referenced user, or NULL when none is set.
   */
  private function loadAuthorFromField(EntityInterface $entity): ?UserInterface {
    $author_field = $this->configuration['author_field'] ?? 'uid';
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($author_field)) {
      return NULL;
    }

    $target = $entity->get($author_field)->entity;
    return $target instanceof UserInterface ? $target : NULL;
  }

}
