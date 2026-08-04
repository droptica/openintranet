<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Declares a notification channel plugin.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NotificationChannel extends Plugin {

  /**
   * Constructs a NotificationChannel attribute.
   *
   * @param string $id
   *   The plugin id.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable channel label.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The channel description.
   * @param class-string|null $deriver
   *   The deriver class.
   * @param bool $user_configurable
   *   Whether end users may configure this channel in their preferences.
   */
  public function __construct(
    public readonly string $id,
    public readonly string|TranslatableMarkup|null $label = NULL,
    public readonly string|TranslatableMarkup|null $description = NULL,
    public readonly ?string $deriver = NULL,
    public readonly bool $user_configurable = TRUE,
  ) {}

}
