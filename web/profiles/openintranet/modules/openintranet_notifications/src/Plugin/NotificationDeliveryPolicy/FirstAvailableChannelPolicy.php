<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationDeliveryPolicy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationDeliveryPolicy;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyBase;

/**
 * Policy: deliver on the first usable candidate channel only.
 *
 * Walks the candidate set (default ∪ forced channels) in order and returns the
 * first channel that survives the shared usability gate, as a single-element
 * set. Forced channels are honoured because they are part of the candidate set;
 * preferences are not consulted (this is a fallback-style "reach the user on
 * whatever works first" policy).
 */
#[NotificationDeliveryPolicy(
  id: 'first_available_channel',
  label: new TranslatableMarkup('First available channel'),
  description: new TranslatableMarkup('Delivers on the first usable channel from the type, in order, and stops.'),
)]
final class FirstAvailableChannelPolicy extends NotificationDeliveryPolicyBase {

  /**
   * {@inheritdoc}
   */
  public function selectChannels(NotificationTypeInterface $type, NotificationRecipient $recipient, array $context): array {
    if ($this->isBlocked($recipient)) {
      return [];
    }

    foreach ($this->candidateChannels($type) as $channelId) {
      if ($this->channelUsable($channelId, $recipient)) {
        return [$channelId];
      }
    }

    return [];
  }

}
