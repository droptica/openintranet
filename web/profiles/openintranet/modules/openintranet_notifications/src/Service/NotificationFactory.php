<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;

/**
 * Builds notification entities from a type and a value array.
 *
 * Produces an UNSAVED notification: the dispatcher decides whether to persist
 * and enqueue it. The factory also stamps the dedupe key so the dispatcher can
 * suppress duplicates without re-deriving it (00-synteza §7, §12).
 */
final class NotificationFactory {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Deduplicator $deduplicator,
  ) {}

  /**
   * Builds (but does not save) a notification of the given type.
   *
   * @param string $typeId
   *   The notification_type id.
   * @param array<string, mixed> $values
   *   Build values: uid, subject, body, summary, source_entity (entity),
   *   actor (account), payload (array), priority, dedupe_context.
   *
   * @return \Drupal\openintranet_notifications\Entity\NotificationInterface
   *   The unsaved notification.
   */
  public function create(string $typeId, array $values): NotificationInterface {
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type */
    $type = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load($typeId);

    $uid = (int) ($values['uid'] ?? 0);
    $build = [
      'type' => $typeId,
      'uid' => $uid,
      'subject' => $values['subject'] ?? '',
      'body' => $values['body'] ?? '',
      'summary' => $values['summary'] ?? '',
      'payload' => $values['payload'] ?? [],
      'priority' => $values['priority'] ?? $type->getDefaultPriority(),
      'status' => 'created',
    ];

    $sourceRef = '';
    if (isset($values['source_entity']) && $values['source_entity'] instanceof EntityInterface) {
      $source = $values['source_entity'];
      $build['source_entity'] = [
        'target_type' => $source->getEntityTypeId(),
        'target_id' => $source->id(),
      ];
      $sourceRef = $source->getEntityTypeId() . ':' . $source->id();
    }

    if (isset($values['actor']) && $values['actor'] instanceof EntityInterface) {
      $build['actor_uid'] = $values['actor']->id();
    }

    $context = (string) ($values['dedupe_context'] ?? '');
    $build['dedupe_key'] = $this->deduplicator->computeKey($typeId, $sourceRef, 'user:' . $uid, $context);

    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface $notification */
    $notification = $this->entityTypeManager
      ->getStorage('openintranet_notification')
      ->create($build);

    return $notification;
  }

}
