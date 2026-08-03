<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
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
   *
   * @throws \InvalidArgumentException
   *   Thrown when the requested notification type does not exist.
   */
  public function create(string $typeId, array $values): NotificationInterface {
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface|null $type */
    $type = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load($typeId);
    if (!$type instanceof NotificationTypeInterface) {
      throw new \InvalidArgumentException(sprintf('Notification type "%s" does not exist.', $typeId));
    }

    $uid = (int) ($values['uid'] ?? 0);
    $build = [
      'type' => $typeId,
      'uid' => $uid,
      'subject' => $values['subject'] ?? '',
      'body' => $values['body'] ?? '',
      'summary' => $values['summary'] ?? '',
      'url' => $values['url'] ?? '',
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
      // Default the click target to the source entity (the article/comment).
      if ($build['url'] === '' && $source->hasLinkTemplate('canonical')) {
        $generatedUrl = $source->toUrl('canonical')->toString(TRUE)->getGeneratedUrl();
        $build['url'] = $this->normalizeCanonicalUrl($generatedUrl);
      }
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

    // Context fingerprint (00-synteza §4.2): a stable hash of the notification
    // context — type + source + payload + actor — distinct from the dedupe_key
    // (which adds the recipient + dedupe context). Recipient-independent, so
    // two recipients of the same event share it; used for audit/grouping, not
    // for per-recipient suppression.
    $actorRef = isset($build['actor_uid']) ? 'user:' . $build['actor_uid'] : '';
    $payload = $values['payload'] ?? [];
    $build['context_hash'] = hash('sha256', implode('|', [
      $typeId,
      $sourceRef,
      $actorRef,
      serialize($payload),
    ]));

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
   * Removes the temporary installer front controller from a canonical URL.
   *
   * Demo entities may trigger notifications while Drupal is running through
   * core/install.php. The router then prefixes otherwise valid canonical paths
   * with that temporary front controller. It is unavailable after installation
   * and must never be persisted as a notification target.
   */
  private function normalizeCanonicalUrl(string $url): string {
    return preg_replace(
      '~\/core\/install\.php(?=\/|$|\?|#)~',
      '',
      $url,
      1,
    ) ?? $url;
  }

  /**
   * Renders a saved notification's message for a specific channel (§4.1).
   *
   * Wires the type's per-channel template_map: when the type maps $channelId to
   * a template, the renderer's per-channel lookup returns the mapped body, so
   * the message sent on that channel differs from the channel-agnostic stored
   * body. Token data is reconstructed from the STORED notification (source
   * entity reference, actor, recipient, payload), so only tokens reachable from
   * those resolve — extra create-time context entities are not available at
   * send time. The caller should only invoke this when a template_map entry
   * exists; with no entry the renderer falls back to the same body the stored
   * notification already holds, so calling it would be wasted work.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The saved notification to re-render.
   * @param \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type
   *   The notification type carrying template_map and the renderer id.
   * @param string $channelId
   *   The target channel plugin id.
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient, whose langcode drives the render language (§9).
   *
   * @return \Drupal\openintranet_notifications\Dto\NotificationMessage
   *   The channel-specific rendered message.
   */
  public function renderForChannel(NotificationInterface $notification, NotificationTypeInterface $type, string $channelId, NotificationRecipient $recipient): NotificationMessage {
    $rendererId = $type->getTemplateRenderer() ?: 'token_text';
    $renderer = $this->templateRendererManager->createInstance($rendererId);

    $values = $this->reconstructValues($notification, $recipient);
    $source = $values['source_entity'] ?? NULL;
    $tokenData = $this->buildTokenData($notification, $values, $source);

    return $renderer->render($type, $channelId, $tokenData);
  }

  /**
   * Rebuilds the render values from a saved notification and recipient.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The saved notification.
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient, supplying the account and langcode.
   *
   * @return array<string, mixed>
   *   The values buildTokenData() expects.
   */
  private function reconstructValues(NotificationInterface $notification, NotificationRecipient $recipient): array {
    // buildTokenData() reads the payload from $values['context']['payload'], so
    // the stored payload is nested under 'context' to match the create path.
    $values = [
      'recipient_account' => $recipient->account,
      'context' => ['payload' => $notification->get('payload')->first()?->getValue() ?? []],
    ];

    $sourceRef = $notification->get('source_entity')->first()?->getValue() ?? [];
    $targetType = $sourceRef['target_type'] ?? NULL;
    $targetId = $sourceRef['target_id'] ?? NULL;
    if (is_string($targetType) && $targetType !== '' && $targetId !== NULL) {
      $source = $this->entityTypeManager->getStorage($targetType)->load($targetId);
      if ($source !== NULL) {
        $values['source_entity'] = $source;
      }
    }

    $actorId = $notification->get('actor_uid')->target_id;
    if ($actorId !== NULL) {
      $actor = $this->entityTypeManager->getStorage('user')->load($actorId);
      if ($actor !== NULL) {
        $values['actor'] = $actor;
      }
    }

    return $values;
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
