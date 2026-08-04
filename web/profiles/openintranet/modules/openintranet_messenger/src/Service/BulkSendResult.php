<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Service;

/**
 * Value object representing bulk send results.
 */
final class BulkSendResult {

  /**
   * All individual send results.
   *
   * @var \Drupal\openintranet_messenger\Service\SendResult[]
   */
  private array $results = [];

  /**
   * Adds a send result.
   *
   * @param \Drupal\openintranet_messenger\Service\SendResult $result
   *   The result.
   *
   * @return $this
   */
  public function addResult(SendResult $result): self {
    $this->results[] = $result;
    return $this;
  }

  /**
   * Gets all results.
   *
   * @return \Drupal\openintranet_messenger\Service\SendResult[]
   *   All results.
   */
  public function getResults(): array {
    return $this->results;
  }

  /**
   * Gets successful results.
   *
   * @return \Drupal\openintranet_messenger\Service\SendResult[]
   *   Successful results.
   */
  public function getSuccessful(): array {
    return array_filter($this->results, fn(SendResult $r) => $r->success);
  }

  /**
   * Gets failed results.
   *
   * @return \Drupal\openintranet_messenger\Service\SendResult[]
   *   Failed results (not skipped).
   */
  public function getFailed(): array {
    return array_filter($this->results, fn(SendResult $r) => !$r->success && !$r->isSkipped());
  }

  /**
   * Gets skipped results.
   *
   * @return \Drupal\openintranet_messenger\Service\SendResult[]
   *   Skipped results.
   */
  public function getSkipped(): array {
    return array_filter($this->results, fn(SendResult $r) => $r->isSkipped());
  }

  /**
   * Gets the count of sent notifications.
   *
   * @return int
   *   The sent count.
   */
  public function getSentCount(): int {
    return count($this->getSuccessful());
  }

  /**
   * Gets the count of failed notifications.
   *
   * @return int
   *   The failed count.
   */
  public function getFailedCount(): int {
    return count($this->getFailed());
  }

  /**
   * Gets the count of skipped recipients.
   *
   * @return int
   *   The skipped count.
   */
  public function getSkippedCount(): int {
    return count($this->getSkipped());
  }

  /**
   * Gets the total count of recipients processed.
   *
   * @return int
   *   The total count.
   */
  public function getTotalCount(): int {
    return count($this->results);
  }

  /**
   * Checks if all sends were successful.
   *
   * @return bool
   *   TRUE if all successful.
   */
  public function isAllSuccessful(): bool {
    return $this->getFailedCount() === 0;
  }

  /**
   * Checks if there were any failures.
   *
   * @return bool
   *   TRUE if any failures.
   */
  public function hasFailures(): bool {
    return $this->getFailedCount() > 0;
  }

}
