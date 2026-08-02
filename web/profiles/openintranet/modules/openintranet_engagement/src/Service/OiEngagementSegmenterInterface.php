<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

/**
 * Interface for user segmentation service.
 */
interface OiEngagementSegmenterInterface {

  /**
   * Determines user segment based on RFV scores.
   *
   * @param int $recencyScore
   *   Recency score (1-5).
   * @param int $frequencyScore
   *   Frequency score (1-5).
   * @param int $valueScore
   *   Value score (1-5).
   * @param int $daysSinceRegistration
   *   Days since user registration.
   *
   * @return string
   *   Segment name: champion, loyal, at_risk, dormant, new, regular.
   */
  public function determineSegment(
    int $recencyScore,
    int $frequencyScore,
    int $valueScore,
    int $daysSinceRegistration
  ): string;

  /**
   * Gets segment definition with metadata.
   *
   * @param string $segment
   *   The segment name.
   *
   * @return array{label: string, description: string, color: string, icon: string}
   *   Segment definition.
   */
  public function getSegmentDefinition(string $segment): array;

  /**
   * Gets all segment definitions.
   *
   * @return array<string, array{label: string, description: string, color: string, icon: string}>
   *   All segment definitions keyed by segment name.
   */
  public function getAllSegments(): array;

}
