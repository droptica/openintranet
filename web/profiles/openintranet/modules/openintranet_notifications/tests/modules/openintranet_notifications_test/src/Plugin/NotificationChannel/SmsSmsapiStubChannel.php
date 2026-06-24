<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_test\Plugin\NotificationChannel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;

/**
 * Toggleable escalation-tier SMS test channel for timed escalation (§3.2).
 *
 * Uses the real escalation-tier id 'sms_smsapi' so the urgent_escalation
 * policy holds it behind ESCALATION_DELAY_SECONDS; the test asserts its row's
 * next_attempt is in the future and that it is cancelled when the immediate
 * tier lands first (and left to fire when the immediate tier fails).
 */
#[NotificationChannel(
  id: 'sms_smsapi',
  label: new TranslatableMarkup('SMS (SMSAPI stub)'),
  description: new TranslatableMarkup('Toggleable escalation-tier channel; exercises §3.2 delayed send.'),
)]
final class SmsSmsapiStubChannel extends ToggleableChannelBase {

  /**
   * The state key holding this channel's outcome toggle.
   */
  public const STATE_KEY = 'openintranet_notifications_test.sms_smsapi_outcome';

  /**
   * {@inheritdoc}
   */
  protected function outcomeStateKey(): string {
    return self::STATE_KEY;
  }

}
