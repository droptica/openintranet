<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Resolver;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Contract for a recipient resolver plugin.
 *
 * A resolver turns a dispatch context into recipient identities; it never
 * resolves transport addresses — each channel does that itself (00-synteza §8).
 */
interface NotificationRecipientResolverInterface extends PluginInspectionInterface {

  /**
   * Resolves the recipients for a dispatch.
   *
   * @param array $context
   *   The dispatch context (source entity, actor, extra parameters).
   *
   * @return \Drupal\openintranet_notifications\Dto\NotificationRecipient[]
   *   The resolved recipient identities.
   */
  public function resolve(array $context): array;

}
