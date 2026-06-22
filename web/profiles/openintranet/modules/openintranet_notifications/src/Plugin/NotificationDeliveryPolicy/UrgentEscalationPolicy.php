<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationDeliveryPolicy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationDeliveryPolicy;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyBase;

/**
 * Policy: tier the channel selection by the notification priority.
 *
 * High-stakes messages reach the user everywhere; routine ones stay restrained:
 * - urgent / high: every usable candidate channel (the full escalation set,
 *   incl. SMS/push when configured), ignoring per-user preferences.
 * - normal / low: the user_preferences subset — usable candidates that are also
 *   preferred (or forced).
 *
 * The priority is read from $context['priority'] when the dispatcher supplies
 * it, otherwise from the type's default priority.
 *
 * @todo The dispatcher currently calls selectChannels() with an empty context
 *   (NotificationDispatcher), so per-notification priority falls back to the
 *   type default; pass the notification's priority in $context['priority'] to
 *   tier per message rather than per type.
 * @todo Scope: this is priority-TIERED CHANNEL SELECTION only. Timed
 *   re-escalation ("send SMS N minutes later if email was not delivered") is a
 *   re-dispatch concern that does not fit a single selectChannels() call and is
 *   deferred; it would be driven by the queue worker / next_attempt mechanism.
 */
#[NotificationDeliveryPolicy(
  id: 'urgent_escalation',
  label: new TranslatableMarkup('Urgent escalation'),
  description: new TranslatableMarkup('Escalates to every available channel for urgent/high priority; stays restrained (user preferences) otherwise.'),
)]
final class UrgentEscalationPolicy extends NotificationDeliveryPolicyBase {

  /**
   * Priorities that trigger the full escalation set.
   */
  private const ESCALATION_PRIORITIES = ['urgent', 'high'];

  /**
   * {@inheritdoc}
   */
  public function selectChannels(NotificationTypeInterface $type, NotificationRecipient $recipient, array $context): array {
    if ($this->isBlocked($recipient)) {
      return [];
    }

    $priority = $context['priority'] ?? $type->getDefaultPriority();
    $escalate = \in_array($priority, self::ESCALATION_PRIORITIES, TRUE);

    $forced = $type->getForcedChannels();
    $selected = [];
    foreach ($this->candidateChannels($type) as $channelId) {
      // Restrained tier honours preferences (or forced); the escalation tier
      // ignores them and takes every usable channel.
      if (!$escalate) {
        $isForced = \in_array($channelId, $forced, TRUE);
        if (!$isForced && !$this->preferenceResolver->isEnabled($recipient->id ?? 0, $type->id(), $channelId)) {
          continue;
        }
      }
      if ($this->channelUsable($channelId, $recipient)) {
        $selected[] = $channelId;
      }
    }

    return $selected;
  }

}
