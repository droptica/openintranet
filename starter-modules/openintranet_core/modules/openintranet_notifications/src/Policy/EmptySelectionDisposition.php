<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Policy;

/**
 * What an empty channel selection means for a delivery policy.
 *
 * Most policies select [] only when a recipient is blocked or every candidate
 * channel was filtered out — a true drop. But digest_only and silent_audit_only
 * return [] BY DESIGN, so the dispatcher must distinguish the intent: a dropped
 * notification is stamped 'cancelled' and fires no lifecycle event, whereas a
 * deferred or audit one persists, fires NotificationCreatedEvent, and reaches
 * the right non-cancelled status.
 */
enum EmptySelectionDisposition {

  // The empty set is a true drop (blocked recipient or all channels filtered).
  case Drop;

  // The empty set is intentional: deliver nothing now, defer to a digest.
  case Defer;

  // The empty set is intentional: record an audit entry, send on no channel.
  case Audit;

}
