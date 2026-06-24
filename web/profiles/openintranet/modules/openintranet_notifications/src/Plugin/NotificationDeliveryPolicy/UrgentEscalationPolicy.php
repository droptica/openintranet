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
 * Timed escalation (00-synteza §3.2) tiers the SEND TIME of the selected
 * channels: for urgent/high, the immediate tier (inbox, email_core) sends at
 * once while the escalation tier (sms_smsapi, push) is held back by
 * ESCALATION_DELAY_SECONDS via channelDelays(). The delivery queue stamps the
 * later tier's next_attempt into the future and the worker's early-defer
 * staggers it; on a successful immediate-tier send the shared sender cancels
 * the still-pending, not-yet-due escalation rows, so the SMS only fires when
 * email never landed.
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
   * The escalation-tier channels held back behind the escalation delay.
   *
   * The disruptive / costly transports (00-synteza §3.2): they fire only after
   * the immediate tier has had ESCALATION_DELAY_SECONDS to land, and are
   * cancelled if it did. Every other selected channel is immediate.
   */
  private const ESCALATION_CHANNELS = ['sms_smsapi', 'push'];

  /**
   * Seconds the escalation tier waits before its first send (default 600).
   */
  private const ESCALATION_DELAY_SECONDS = 600;

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

  /**
   * {@inheritdoc}
   *
   * Only escalating priorities (urgent/high) tier their send time: the
   * escalation-tier channels wait ESCALATION_DELAY_SECONDS, every other
   * selected channel sends immediately. normal/low never delay anything.
   */
  public function channelDelays(NotificationTypeInterface $type, NotificationRecipient $recipient, array $context): array {
    $priority = $context['priority'] ?? $type->getDefaultPriority();
    if (!\in_array($priority, self::ESCALATION_PRIORITIES, TRUE)) {
      return [];
    }

    $delays = [];
    foreach (self::ESCALATION_CHANNELS as $channelId) {
      $delays[$channelId] = self::ESCALATION_DELAY_SECONDS;
    }
    return $delays;
  }

}
