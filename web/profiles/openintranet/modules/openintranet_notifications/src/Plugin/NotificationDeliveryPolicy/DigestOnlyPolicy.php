<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationDeliveryPolicy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationDeliveryPolicy;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyBase;

/**
 * Policy: deliver nothing immediately; defer to a periodic digest.
 *
 * The selectChannels() method returns an empty set, so the dispatcher enqueues
 * no immediate deliveries. The notification entity still persists as an inbox /
 * audit record. The DigestBuilder (run on cron) later finds every not-yet
 * digested notification whose type uses this policy, groups them by recipient
 * and fires NotificationDigestReadyEvent so an ECA model or integrator can
 * render and send the aggregated digest, marking each item digested exactly
 * once.
 */
#[NotificationDeliveryPolicy(
  id: 'digest_only',
  label: new TranslatableMarkup('Digest only'),
  description: new TranslatableMarkup('Sends nothing immediately; the notification is aggregated into a periodic digest.'),
)]
final class DigestOnlyPolicy extends NotificationDeliveryPolicyBase {

  /**
   * {@inheritdoc}
   */
  public function selectChannels(NotificationTypeInterface $type, NotificationRecipient $recipient, array $context): array {
    return [];
  }

}
