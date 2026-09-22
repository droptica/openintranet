<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_test\Plugin\NotificationChannel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;

/**
 * Toggleable immediate-tier test channel for timed escalation (§3.2).
 *
 * Stands in for an immediate channel (e.g. email): not a member of the
 * urgent_escalation escalation set, so it carries no delay and fires at once.
 */
#[NotificationChannel(
  id: 'escalation_immediate',
  label: new TranslatableMarkup('Escalation immediate'),
  description: new TranslatableMarkup('Toggleable immediate-tier channel; exercises §3.2 cancel-on-success.'),
)]
final class EscalationImmediateChannel extends ToggleableChannelBase {

  /**
   * The state key holding this channel's outcome toggle.
   */
  public const STATE_KEY = 'openintranet_notifications_test.escalation_immediate_outcome';

  /**
   * {@inheritdoc}
   */
  protected function outcomeStateKey(): string {
    return self::STATE_KEY;
  }

}
