<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationDeliveryPolicy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationDeliveryPolicy;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyBase;

/**
 * Default policy: deliver on the channels the recipient prefers.
 *
 * Owns the channel-set arithmetic (00-synteza §3.2): the candidate set is the
 * type's default ∪ forced channels, then each channel survives only when it is
 * preferred (or forced) and otherwise usable (enabled, not killed, available,
 * addressable — the shared filter on the base).
 */
#[NotificationDeliveryPolicy(
  id: 'user_preferences',
  label: new TranslatableMarkup('User preferences'),
  description: new TranslatableMarkup('Delivers on the channels the user prefers, keeping forced channels and dropping disabled, killed or unavailable ones.'),
)]
final class UserPreferencesPolicy extends NotificationDeliveryPolicyBase {

  /**
   * {@inheritdoc}
   */
  public function selectChannels(NotificationTypeInterface $type, NotificationRecipient $recipient, array $context): array {
    if ($this->isBlocked($recipient)) {
      return [];
    }

    $forced = $type->getForcedChannels();
    $selected = [];
    foreach ($this->candidateChannels($type) as $channelId) {
      $isForced = \in_array($channelId, $forced, TRUE);
      if (!$isForced && !$this->preferenceResolver->isEnabled($recipient->id ?? 0, $type->id(), $channelId)) {
        continue;
      }
      if ($this->channelUsable($channelId, $recipient)) {
        $selected[] = $channelId;
      }
    }

    return $selected;
  }

}
