<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

/**
 * Determines user segments based on RFV scores.
 */
final class OiEngagementSegmenter implements OiEngagementSegmenterInterface {

  /**
   * Segment definitions with conditions and metadata.
   */
  private const SEGMENTS = [
    'champion' => [
      'label' => 'Champion',
      'description' => 'Most engaged power users who create valuable content',
      'color' => '#28a745',
      'icon' => '🏆',
      'conditions' => [
        'recency' => ['min' => 4],
        'frequency' => ['min' => 4],
        'value' => ['min' => 4],
      ],
    ],
    'loyal' => [
      'label' => 'Loyal',
      'description' => 'Regular users with stable engagement',
      'color' => '#007bff',
      'icon' => '⭐',
      'conditions' => [
        'recency' => ['min' => 3],
        'frequency' => ['min' => 3],
        'value' => ['min' => 3],
        'total' => ['min' => 10],
      ],
    ],
    'at_risk' => [
      'label' => 'At Risk',
      'description' => 'Previously active users showing declining engagement',
      'color' => '#ffc107',
      'icon' => '⚠️',
      'conditions' => [
        'recency' => ['max' => 2],
        'frequency' => ['min' => 3],
        'value' => ['min' => 3],
      ],
    ],
    'dormant' => [
      'label' => 'Dormant',
      'description' => 'Inactive users needing reactivation',
      'color' => '#dc3545',
      'icon' => '💤',
      'conditions' => [
        'recency' => ['max' => 1],
        'frequency' => ['max' => 2],
        'value' => ['max' => 2],
      ],
    ],
    'new' => [
      'label' => 'New',
      'description' => 'Recently registered users in onboarding period',
      'color' => '#17a2b8',
      'icon' => '🆕',
      'conditions' => [
        'days_since_registration' => ['max' => 30],
      ],
    ],
    'regular' => [
      'label' => 'Regular',
      'description' => 'Standard users with moderate engagement',
      'color' => '#6c757d',
      'icon' => '👤',
      'conditions' => [],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function determineSegment(
    int $recencyScore,
    int $frequencyScore,
    int $valueScore,
    int $daysSinceRegistration
  ): string {
    $totalScore = $recencyScore + $frequencyScore + $valueScore;

    // Check NEW first (based on registration date).
    if ($daysSinceRegistration <= 30) {
      return 'new';
    }

    // Check CHAMPION - all scores are high.
    if ($recencyScore >= 4 && $frequencyScore >= 4 && $valueScore >= 4) {
      return 'champion';
    }

    // Check AT RISK - was active (high F/V) but declining (low R).
    if ($recencyScore <= 2 && $frequencyScore >= 3 && $valueScore >= 3) {
      return 'at_risk';
    }

    // Check LOYAL - good scores across the board.
    if ($recencyScore >= 3 && $frequencyScore >= 3 && $valueScore >= 3 && $totalScore >= 10) {
      return 'loyal';
    }

    // Check DORMANT - low activity across all dimensions.
    if ($recencyScore <= 1 && $frequencyScore <= 2 && $valueScore <= 2) {
      return 'dormant';
    }

    // Default: REGULAR.
    return 'regular';
  }

  /**
   * {@inheritdoc}
   */
  public function getSegmentDefinition(string $segment): array {
    $definition = self::SEGMENTS[$segment] ?? self::SEGMENTS['regular'];

    return [
      'label' => $definition['label'],
      'description' => $definition['description'],
      'color' => $definition['color'],
      'icon' => $definition['icon'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllSegments(): array {
    $segments = [];
    foreach (self::SEGMENTS as $key => $definition) {
      $segments[$key] = [
        'label' => $definition['label'],
        'description' => $definition['description'],
        'color' => $definition['color'],
        'icon' => $definition['icon'],
      ];
    }
    return $segments;
  }

}
