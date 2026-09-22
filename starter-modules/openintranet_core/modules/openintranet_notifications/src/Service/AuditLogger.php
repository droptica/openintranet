<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Psr\Log\LoggerInterface;

/**
 * Writes audit records for delivery attempts with PII masked.
 *
 * Every send attempt is logged for the audit trail (00-synteza §9); transport
 * addresses (emails, phone numbers) are masked so logs never leak raw PII.
 */
final class AuditLogger {

  public function __construct(
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Logs the outcome of a delivery attempt.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery
   *   The delivery record that was attempted.
   * @param \Drupal\openintranet_notifications\Dto\DeliveryResult $result
   *   The classified outcome of the attempt.
   */
  public function log(NotificationDeliveryInterface $delivery, DeliveryResult $result): void {
    $context = [
      '@channel' => (string) $delivery->get('channel')->value,
      '@address' => $this->maskPii((string) $delivery->get('address')->value),
      '@status' => (string) $delivery->get('status')->value,
      '@code' => $result->errorCode ?? '',
      '@message' => $result->errorMessage ?? '',
    ];

    if ($result->success) {
      $this->logger->info('Notification delivery sent on @channel to @address (status @status).', $context);
      return;
    }

    $this->logger->warning('Notification delivery failed on @channel to @address (status @status): @code @message', $context);
  }

  /**
   * Masks PII in a transport address for safe logging.
   *
   * Emails keep the first local and first domain character plus the TLD
   * (j***@e***.com); phone numbers keep the first three and last three
   * characters (+48***200); other values are returned unchanged.
   *
   * @param string $value
   *   The raw address value.
   *
   * @return string
   *   The masked value.
   */
  public function maskPii(string $value): string {
    if ($value === '') {
      return '';
    }

    if (str_contains($value, '@')) {
      return $this->maskEmail($value);
    }

    if (preg_match('/^\+?\d[\d\s-]+$/', $value) === 1) {
      return $this->maskPhone($value);
    }

    return $value;
  }

  /**
   * Masks an email as first-local + first-domain + TLD.
   */
  private function maskEmail(string $value): string {
    [$local, $domain] = explode('@', $value, 2);
    $dot = strrpos($domain, '.');
    $tld = $dot !== FALSE ? substr($domain, $dot) : '';
    $localChar = $local === '' ? '' : mb_substr($local, 0, 1);
    $domainChar = $domain === '' ? '' : mb_substr($domain, 0, 1);
    return $localChar . '***@' . $domainChar . '***' . $tld;
  }

  /**
   * Masks a phone as first-three + last-three characters.
   */
  private function maskPhone(string $value): string {
    if (mb_strlen($value) <= 6) {
      return '***';
    }
    return mb_substr($value, 0, 3) . '***' . mb_substr($value, -3);
  }

}
