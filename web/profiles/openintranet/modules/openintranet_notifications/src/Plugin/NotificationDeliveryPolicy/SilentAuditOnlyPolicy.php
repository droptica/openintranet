<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationDeliveryPolicy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationDeliveryPolicy;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Policy\EmptySelectionDisposition;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyBase;

/**
 * Policy: record an audit entry but deliver on no channel.
 *
 * Always returns an empty channel set. The notification entity still persists
 * as the audit/inbox record (the dispatcher writes it before selecting
 * channels); no channel is selected — not even inbox. A site that wants the
 * notification visible on-site without any transport send should use
 * user_preferences with only the inbox channel enabled instead.
 */
#[NotificationDeliveryPolicy(
  id: 'silent_audit_only',
  label: new TranslatableMarkup('Silent (audit only)'),
  description: new TranslatableMarkup('Records the notification as an audit entry and delivers on no channel.'),
)]
final class SilentAuditOnlyPolicy extends NotificationDeliveryPolicyBase {

  /**
   * {@inheritdoc}
   */
  public function selectChannels(NotificationTypeInterface $type, NotificationRecipient $recipient, array $context): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * The empty set is intentional: the notification is an audit record, not a
   * cancellation, so it persists and reaches the terminal audit state.
   */
  public function emptySelectionDisposition(): EmptySelectionDisposition {
    return EmptySelectionDisposition::Audit;
  }

}
