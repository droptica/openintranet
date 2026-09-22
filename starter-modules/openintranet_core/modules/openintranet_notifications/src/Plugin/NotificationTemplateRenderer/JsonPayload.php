<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationTemplateRenderer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationTemplateRenderer;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Renderer\NotificationTemplateRendererBase;

/**
 * Builds a structured payload for webhook/API channels.
 *
 * Produces a machine-readable payload (notification id/type, source entity
 * reference, recipient placeholder, tokenised title/body) merged with any
 * caller-supplied payload data, and exposes it on the message's payload
 * property (00-synteza §8). The subject/body carry the tokenised strings for
 * channels that also read those.
 */
#[NotificationTemplateRenderer(
  id: 'json_payload',
  label: new TranslatableMarkup('JSON payload'),
  description: new TranslatableMarkup('Builds a structured payload for webhook/API channels.'),
)]
final class JsonPayload extends NotificationTemplateRendererBase {

  /**
   * {@inheritdoc}
   */
  public function render(NotificationTypeInterface $type, string $channelId, array $tokenData): NotificationMessage {
    $title = $this->replace($type->getSubjectTemplate(), $tokenData);
    $body = $this->replace($type->getBodyTemplate(), $tokenData);

    $notification = $tokenData['notification'] ?? NULL;
    $source = $tokenData['entity'] ?? NULL;

    $payload = [
      'notification_id' => $notification instanceof EntityInterface ? $notification->id() : NULL,
      'notification_type' => $type->id(),
      'source_entity' => $this->entityReference($source),
      'channel' => $channelId,
      'title' => $title,
      'body' => $body,
    ];

    // Merge any caller-supplied payload data (caller keys win).
    $extra = $tokenData['payload'] ?? [];
    if (is_array($extra)) {
      $payload = array_merge($payload, $extra);
    }

    return new NotificationMessage(
      subject: $title,
      body: $body,
      payload: $payload,
    );
  }

  /**
   * Builds an entity reference array for the payload.
   *
   * @param mixed $entity
   *   The candidate source entity, or NULL.
   *
   * @return array{entity_type: string, id: int|string|null}|null
   *   The reference, or NULL when no content entity is given.
   */
  protected function entityReference(mixed $entity): ?array {
    if (!$entity instanceof EntityInterface) {
      return NULL;
    }
    return [
      'entity_type' => $entity->getEntityTypeId(),
      'id' => $entity->id(),
    ];
  }

}
