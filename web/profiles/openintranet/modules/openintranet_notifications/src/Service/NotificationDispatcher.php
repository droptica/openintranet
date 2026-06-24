<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Event\NotificationCreatedEvent;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationQueuedEvent;
use Drupal\openintranet_notifications\Policy\DeliveryPolicyManager;
use Drupal\openintranet_notifications\Policy\EmptySelectionDisposition;
use Drupal\openintranet_notifications\Resolver\RecipientResolverManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Orchestrates a notification from build to enqueued deliveries.
 *
 * Holds the send-time domain logic (00-synteza §7): dedupe and rate-limit
 * guards run before anything is persisted, the type's policy decides the
 * channel set, and the delivery queue materializes one row per channel. ECA
 * only decides WHEN to call this; the logic lives here, not in a model.
 */
final class NotificationDispatcher {

  public function __construct(
    private readonly NotificationFactory $notificationFactory,
    private readonly DeliveryPolicyManager $policyManager,
    private readonly Deduplicator $deduplicator,
    private readonly RateLimiter $rateLimiter,
    private readonly DeliveryQueue $deliveryQueue,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RecipientResolverManager $recipientResolverManager,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Enqueues one notification: dedupe/rate guards, channel selection, queue.
   *
   * The dedupe guard runs BEFORE persisting, so a duplicate leaves no
   * notification entity and no delivery rows behind (00-synteza §12).
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $n
   *   The notification to enqueue. It may already be saved; this method saves
   *   it again only after the dedupe guard passes.
   */
  public function enqueue(NotificationInterface $n): void {
    $uid = (int) $n->get('uid')->target_id;
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user === NULL) {
      $this->abandon($n);
      return;
    }

    $recipient = NotificationRecipient::forUser($user);

    $type = $this->loadType($n);
    if ($type === NULL) {
      $this->abandon($n);
      return;
    }

    // Disabled-type gate (FIX 3): the entity `enabled` flag is the dispatch
    // gate, so disabling a type takes effect — nothing is persisted, no
    // deliveries are created and no queue item is enqueued. The
    // `enabled_types` settings list is an advisory admin allow-list, not a hard
    // dispatch gate, so it never silently disables a shipped/migrated type.
    if (!$type->isEnabled()) {
      $this->abandon($n);
      return;
    }

    $window = $type->getDedupeWindow();
    $dedupeKey = (string) $n->get('dedupe_key')->value;

    // Dedupe guard first: a duplicate must not persist or create deliveries.
    // A non-positive window disables dedupe entirely (FIX #3): recording with a
    // zero TTL would store a born-expired entry that never matches.
    if ($window > 0 && $this->deduplicator->isDuplicate($dedupeKey)) {
      $this->abandon($n);
      return;
    }

    // Per-dispatch cap on the reserved ALL_CHANNELS sentinel: this counts
    // notifications per (uid, type) regardless of channel, a SEPARATE counter
    // from the per-channel BelowRateLimit ECA condition (see RateLimiter).
    // @todo Source the rate limit and window from per-type settings (Stage 2);
    //   Stage 1 uses a permissive default so the guard is wired but inert.
    $limit = 1000;
    $rateWindow = 3600;
    if (!$this->rateLimiter->allow($uid, RateLimiter::ALL_CHANNELS, $type->id(), $limit, $rateWindow)) {
      $this->abandon($n);
      return;
    }

    $n->save();

    $policyId = $type->getDeliveryPolicy() ?: 'user_preferences';
    $policy = $this->policyManager->createInstance($policyId);
    // Thread the per-notification priority so the policy tiers per message, not
    // per type (00-synteza §3.2): selectChannels() and channelDelays() both
    // read $context['priority'], falling back to the type default when absent.
    $context = ['priority' => (string) $n->get('priority')->value];
    $channels = $policy->selectChannels($type, $recipient, $context);

    // An empty channel set is ambiguous: a true drop (blocked/filtered
    // recipient) must be cancelled, but digest_only/silent_audit_only return []
    // BY DESIGN and must persist + fire CREATED. The policy disambiguates.
    if (empty($channels)) {
      $disposition = $policy->emptySelectionDisposition();
      if ($disposition === EmptySelectionDisposition::Drop) {
        // A true drop must not linger as 'queued' or record dedupe; mark it
        // cancelled so a future legit send for the same key is not suppressed
        // (FIX #4). No lifecycle event fires here.
        $n->set('status', 'cancelled');
        $n->save();
        return;
      }

      // Intentional empty set: persist and fire CREATED. Defer (digest_only)
      // stays 'queued' so the DigestBuilder picks it up (digested IS NULL);
      // Audit (silent_audit_only) reaches the terminal 'delivered' audit state
      // (recorded, nothing to send).
      $n->set('status', $disposition === EmptySelectionDisposition::Audit ? 'delivered' : 'queued');
      $n->save();
      $this->eventDispatcher->dispatch(new NotificationCreatedEvent($n), NotificationEvents::CREATED);
      return;
    }

    $this->eventDispatcher->dispatch(new NotificationCreatedEvent($n), NotificationEvents::CREATED);

    // Timed escalation (00-synteza §3.2): the policy may hold an escalation
    // tier (e.g. SMS) behind a delay; createAndEnqueue stamps each row's
    // next_attempt so the worker staggers it. Immediate channels send now.
    $delays = $policy->channelDelays($type, $recipient, $context);
    $this->deliveryQueue->createAndEnqueue($n, $recipient, $channels, $delays);
    if ($window > 0) {
      $this->deduplicator->record($dedupeKey, $window);
    }

    $n->set('status', 'queued');
    $n->save();

    $this->eventDispatcher->dispatch(new NotificationQueuedEvent($n), NotificationEvents::QUEUED);
  }

  /**
   * Fans out a request to one notification per recipient.
   *
   * @param string $typeId
   *   The notification_type id.
   * @param array<int, int> $recipients
   *   The recipient user ids.
   * @param array<string, mixed> $context
   *   Extra build values merged into each notification (subject, body, etc.).
   */
  public function dispatchRequest(string $typeId, array $recipients, array $context = []): void {
    // The source entity is the dispatch entity/source_entity context key; the
    // actor is the acting account. The whole keyed context is nested under
    // 'context' so the factory can expose every entity-valued entry to the
    // renderer token data, instead of spreading flat (which lost it).
    $source = $context['source_entity'] ?? ($context['entity'] ?? NULL);
    $actor = $context['actor'] ?? NULL;

    if ($recipients === []) {
      foreach ($this->resolveRecipients($typeId, $context) as $recipient) {
        $values = [
          'uid' => $recipient->id,
          'recipient_account' => $recipient->account,
          'source_entity' => $source,
          'actor' => $actor,
          'context' => $context,
        ];
        $n = $this->notificationFactory->create($typeId, $values);
        // The recipients were resolved by the type's resolvers (not pre-
        // supplied), so the notification passes through 'resolving' before
        // enqueue rolls it to 'queued' (00-synteza §4.2). enqueue() owns the
        // dedupe/rate guards and the final status, so this only records the
        // resolution phase as an observable, audit-visible state.
        $n->set('status', 'resolving');
        $n->save();
        $this->enqueue($n);
      }
      return;
    }

    foreach ($recipients as $recipientId) {
      // Explicit recipients carry only a uid, so load the account here too so
      // [user:*] tokens resolve in this branch as well (#12).
      $recipientAccount = $this->entityTypeManager->getStorage('user')->load((int) $recipientId);
      $values = [
        'uid' => $recipientId,
        'recipient_account' => $recipientAccount,
        'source_entity' => $source,
        'actor' => $actor,
        'context' => $context,
      ];
      $n = $this->notificationFactory->create($typeId, $values);
      $this->enqueue($n);
    }
  }

  /**
   * Cancels a notification that was persisted before a guard rejected it.
   *
   * The resolve-path saves the notification at 'resolving' before enqueue runs
   * its guards (00-synteza §4.2). When a guard bails (missing user/type,
   * disabled type, dedupe hit, rate limit), that persisted row must not be left
   * stranded at the non-terminal 'resolving': it is stamped terminal
   * 'cancelled' so it never lingers and retention can purge it. An unsaved
   * notification (the pre-resolved path, which only saves after the guards) is
   * left untouched — there is no row to cancel.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $n
   *   The notification a guard rejected.
   */
  private function abandon(NotificationInterface $n): void {
    if ($n->isNew()) {
      return;
    }
    $n->set('status', 'cancelled');
    $n->save();
  }

  /**
   * Resolves the recipient set from a type's recipient_resolvers.
   *
   * @param string $typeId
   *   The notification_type id.
   * @param array<string, mixed> $context
   *   The dispatch context handed to each resolver.
   *
   * @return array<int, \Drupal\openintranet_notifications\Dto\NotificationRecipient>
   *   The user recipients, de-duplicated by user id.
   */
  private function resolveRecipients(string $typeId, array $context): array {
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface|null $type */
    $type = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load($typeId);
    if ($type === NULL) {
      return [];
    }

    $resolved = [];
    foreach ($type->getRecipientResolvers() as $definition) {
      $resolver = $this->recipientResolverManager
        ->createInstance($definition['id'], $definition['configuration'] ?? []);
      foreach ($resolver->resolve($context) as $recipient) {
        if ($recipient->id !== NULL) {
          $resolved[$recipient->id] = $recipient;
        }
      }
    }
    return array_values($resolved);
  }

  /**
   * Loads the notification's type config entity.
   */
  private function loadType(NotificationInterface $n): ?NotificationTypeInterface {
    $typeId = (string) $n->get('type')->value;
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface|null $type */
    $type = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load($typeId);
    return $type;
  }

}
