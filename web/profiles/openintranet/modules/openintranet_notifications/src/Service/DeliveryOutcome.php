<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

/**
 * The outcome of processing a single delivery through DeliverySender.
 *
 * Lets the shared sender stay transport-agnostic: it performs the claim, send,
 * classification, status roll-up and events, then reports back so a queue
 * caller can translate a Retry into the queue's backoff mechanism while a
 * synchronous caller (SendNow) simply ignores it.
 */
enum DeliveryOutcome {

  // The delivery was sent and marked terminal; nothing more to do.
  case Sent;

  // The delivery failed permanently (or exhausted its retry budget).
  case PermanentlyFailed;

  // A retryable failure: the row is reset to pending with next_attempt set, so
  // a queue caller should re-queue with backoff and a synchronous caller leaves
  // the row as-is.
  case Retry;

  // Not sent: terminal row, missing/unavailable channel, or the idempotency
  // claim was already held. The row reflects the reason (skipped or its prior
  // terminal status).
  case NotSent;

}
