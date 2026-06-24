<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface;
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
    private readonly EntityRepositoryInterface $entityRepository,
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

    // Dedupe identity (00-synteza §12). When the caller supplies a
    // dedupe_context (e.g. the new_comment model's
    // "comment-thread:[commented_node:nid]"), it BECOMES the dedupe identity in
    // place of the per-source ref: a comment thread fans out one notification
    // per (thread, recipient), so 100 comments on one node to one author dedupe
    // within the window. The per-comment source ref would otherwise make every
    // comment unique and defeat the thread key. With no dedupe_context the key
    // keeps its (type, source, recipient) shape.
    $context = (string) ($values['dedupe_context'] ?? '');
    $dedupeRef = $context !== '' ? $context : $sourceRef;
    $build['dedupe_key'] = $this->deduplicator->computeKey($typeId, $dedupeRef, 'user:' . $uid);

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
    $context = is_array($values['context'] ?? NULL) ? $values['context'] : [];
    $tokenData = [];

    // §9: render in the recipient's preferred langcode. The langcode is
    // resolved first so every source/context entity is exposed in the
    // recipient's translation: the [node:title] token returns $node->getTitle()
    // on the object given (it does not re-translate), so the translated object
    // must be the one handed to the renderer. The langcode is also passed to
    // Token::replace (renderer base) for locale-sensitive and body/summary
    // tokens that DO honour it.
    $recipient = $values['recipient_account'] ?? NULL;
    $langcode = $recipient instanceof AccountInterface ? $recipient->getPreferredLangcode() : NULL;

    // Expose every entity-valued context entry under its entity-type-id key so
    // templates can reference it (e.g. the commented node under [node]). On a
    // type collision the last entry wins; the explicit source below is set
    // afterwards, so it overrides for its own entity type.
    foreach ($context as $value) {
      if ($value instanceof EntityInterface) {
        $tokenData[$value->getEntityTypeId()] = $this->translate($value, $langcode);
      }
    }

    // The source entity is exposed under BOTH its entity-type-id key (core
    // tokens, e.g. [node:title]) and a generic 'entity' key (json_payload and
    // render_array read 'entity').
    if ($source !== NULL) {
      $source = $this->translate($source, $langcode);
      $tokenData[$source->getEntityTypeId()] = $source;
      $tokenData['entity'] = $source;
    }

    if (isset($values['recipient_account']) && $values['recipient_account'] instanceof EntityInterface) {
      $tokenData['user'] = $values['recipient_account'];
    }
    if (isset($values['actor']) && $values['actor'] instanceof EntityInterface) {
      $tokenData['actor'] = $values['actor'];
    }
    $tokenData['notification'] = $notification;
    $tokenData['payload'] = $context['payload'] ?? [];

    if ($langcode !== NULL) {
      $tokenData['langcode'] = $langcode;
    }

    return $tokenData;
  }

  /**
   * Returns the entity's translation for a langcode, or the entity unchanged.
   *
   * Uses the entity repository's context-aware fallback so a missing
   * translation degrades to the best available one (§9). A NULL langcode (no
   * recipient account) or a non-translatable entity is returned as-is.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to translate.
   * @param string|null $langcode
   *   The recipient's preferred langcode, or NULL when unknown.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The translated entity, or the original when no translation applies.
   */
  private function translate(EntityInterface $entity, ?string $langcode): EntityInterface {
    if ($langcode === NULL || !$entity instanceof TranslatableInterface) {
      return $entity;
    }
    $translated = $this->entityRepository->getTranslationFromContext($entity, $langcode);
    return $translated instanceof EntityInterface ? $translated : $entity;
  }

}
