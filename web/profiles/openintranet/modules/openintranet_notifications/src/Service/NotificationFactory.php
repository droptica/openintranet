<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Renderer\TemplateRendererManager;

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
    private readonly TemplateRendererManager $templateRendererManager,
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

    $source = NULL;
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

    // Back-compat: an explicit subject/body in $values (Stage 1 / override) is
    // used verbatim. Otherwise render the type's templates once, at create.
    $hasExplicit = isset($values['subject']) || isset($values['body']);
    if (!$hasExplicit) {
      $this->renderInto($notification, $type, $values, $source);
    }

    return $notification;
  }

  /**
   * Renders the type's templates into the notification's subject/body/summary.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The unsaved notification to populate.
   * @param \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type
   *   The notification type carrying the templates and renderer id.
   * @param array<string, mixed> $values
   *   The build values (carry context, recipient_account, actor).
   * @param \Drupal\Core\Entity\EntityInterface|null $source
   *   The source entity, exposed under its entity-type-id token key.
   */
  private function renderInto(NotificationInterface $notification, NotificationTypeInterface $type, array $values, ?EntityInterface $source): void {
    $rendererId = $type->getTemplateRenderer() ?: 'token_text';
    $renderer = $this->templateRendererManager->createInstance($rendererId);

    $tokenData = $this->buildTokenData($notification, $values, $source);
    $message = $renderer->render($type, '', $tokenData);

    $notification->set('subject', $message->subject);
    $notification->set('body', $message->body);
    $notification->set('summary', $message->summary);
  }

  /**
   * Builds the token data handed to the renderer.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The unsaved notification.
   * @param array<string, mixed> $values
   *   The build values (context, recipient_account, actor).
   * @param \Drupal\Core\Entity\EntityInterface|null $source
   *   The source entity.
   *
   * @return array<string, mixed>
   *   The token replacement data.
   */
  private function buildTokenData(NotificationInterface $notification, array $values, ?EntityInterface $source): array {
    $context = $values['context'] ?? [];
    $tokenData = is_array($context) ? $context : [];

    if ($source !== NULL) {
      $tokenData[$source->getEntityTypeId()] = $source;
    }
    if (isset($values['recipient_account']) && $values['recipient_account'] instanceof EntityInterface) {
      $tokenData['user'] = $values['recipient_account'];
    }
    if (isset($values['actor']) && $values['actor'] instanceof EntityInterface) {
      $tokenData['actor'] = $values['actor'];
    }
    $tokenData['notification'] = $notification;

    return $tokenData;
  }

}
