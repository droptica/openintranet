<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationRecipientResolver;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationRecipientResolver;
use Drupal\openintranet_notifications\Resolver\NotificationRecipientResolverBase;
use Drupal\user\UserInterface;

/**
 * Resolves recipients from an entity-reference field on the source entity.
 *
 * Reads the entity from $context[entity_key], loads each user referenced by the
 * configured field_name and returns them. Missing and blocked users are
 * skipped.
 */
#[NotificationRecipientResolver(
  id: 'entity_field_users',
  label: new TranslatableMarkup('Users from entity field'),
  description: new TranslatableMarkup('Resolves recipients from a user entity-reference field on the source entity.'),
)]
final class EntityFieldUsers extends NotificationRecipientResolverBase {

  /**
   * {@inheritdoc}
   */
  public function resolve(array $context): array {
    $entity_key = $this->configuration['entity_key'] ?? 'entity';
    $field_name = $this->configuration['field_name'] ?? '';
    $entity = $context[$entity_key] ?? NULL;
    if ($field_name === '' || !$entity instanceof FieldableEntityInterface || !$entity->hasField($field_name)) {
      return [];
    }

    $recipients = [];
    foreach ($entity->get($field_name)->referencedEntities() as $referenced) {
      if (!$referenced instanceof UserInterface) {
        continue;
      }
      $recipient = $this->buildUserRecipient($referenced);
      if ($recipient !== NULL) {
        $recipients[] = $recipient;
      }
    }
    return $recipients;
  }

}
