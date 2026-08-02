<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Configuration service for engagement tracking.
 */
final class OiEngagementConfig implements OiEngagementConfigInterface {

  /**
   * Constructs the OiEngagementConfig service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isTrackingEnabled(): bool {
    return (bool) $this->getSettings()->get('enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function shouldTrackAnonymous(): bool {
    return (bool) $this->getSettings()->get('track_anonymous');
  }

  /**
   * {@inheritdoc}
   */
  public function getEventLogRetention(): int {
    return (int) ($this->getSettings()->get('event_log_retention') ?? 90);
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeEnabled(string $entityType, string $operation): bool {
    $entityTypes = $this->getEntityTypes();

    foreach ($entityTypes as $config) {
      if ($config['entity_type'] === $entityType && !empty($config['enabled'])) {
        $value = $config['operations'][$operation] ?? NULL;
        return $value !== NULL && $value > 0;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeValue(string $entityType, string $operation): int {
    $entityTypes = $this->getEntityTypes();

    foreach ($entityTypes as $config) {
      if ($config['entity_type'] === $entityType) {
        return (int) ($config['operations'][$operation] ?? 0);
      }
    }

    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getThresholds(): array {
    $thresholds = $this->getSettings()->get('thresholds') ?? [];

    return [
      'recency' => $thresholds['recency'] ?? [1, 7, 14, 30],
      'frequency' => $thresholds['frequency'] ?? [5, 20, 50, 100],
      'value' => $thresholds['value'] ?? [20, 50, 100, 200],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getNewUserThreshold(): int {
    return (int) ($this->getSettings()->get('new_user_threshold') ?? 30);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypes(): array {
    return $this->configFactory->get('openintranet_engagement.entity_types')->get('entity_types') ?? [];
  }

  /**
   * Gets the settings config object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The settings config.
   */
  private function getSettings(): ImmutableConfig {
    return $this->configFactory->get('openintranet_engagement.settings');
  }

}
