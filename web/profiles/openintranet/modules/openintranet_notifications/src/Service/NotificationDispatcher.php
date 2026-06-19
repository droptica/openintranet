<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Policy\DeliveryPolicyManager;

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
    private readonly ConfigFactoryInterface $configFactory,
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
      return;
    }

    $recipient = new NotificationRecipient(
      type: 'user',
      id: $uid,
      langcode: $user->getPreferredLangcode(),
      account: $user,
    );

    $type = $this->loadType($n);
    if ($type === NULL) {
      return;
    }
    $window = $type->getDedupeWindow();
    $dedupeKey = (string) $n->get('dedupe_key')->value;

    // Dedupe guard first: a duplicate must not persist or create deliveries.
    if ($this->deduplicator->isDuplicate($dedupeKey, $window)) {
      return;
    }

    // @todo Source the rate limit and window from per-type settings (Stage 2);
    //   Stage 1 uses a permissive default so the guard is wired but inert.
    $limit = 1000;
    $rateWindow = 3600;
    if (!$this->rateLimiter->allow($uid, 'all', $type->id(), $limit, $rateWindow)) {
      return;
    }

    $n->save();

    $policyId = $type->getDeliveryPolicy() ?: 'user_preferences';
    $policy = $this->policyManager->createInstance($policyId);
    $channels = $policy->selectChannels($type, $recipient, []);

    $this->deliveryQueue->createAndEnqueue($n, $recipient, $channels);
    $this->deduplicator->record($dedupeKey, $window);

    $n->set('status', 'queued');
    $n->save();

    // @todo Fire the ECA notification:created event here (wired in Stage 2).
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
    // @todo When $recipients is empty, resolve via the type's
    //   recipient_resolvers (Stage 2).
    foreach ($recipients as $recipientId) {
      $n = $this->notificationFactory->create($typeId, ['uid' => $recipientId] + $context);
      $this->enqueue($n);
    }
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
