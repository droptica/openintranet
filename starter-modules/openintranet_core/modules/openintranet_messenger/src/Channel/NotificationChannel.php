<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Channel;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines the notification channel plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NotificationChannel extends Plugin {

  /**
   * Constructs a NotificationChannel attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable label.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The plugin description.
   * @param int $weight
   *   The plugin weight for ordering.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $label = NULL,
    public readonly string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $description = NULL,
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
  ) {}

}
