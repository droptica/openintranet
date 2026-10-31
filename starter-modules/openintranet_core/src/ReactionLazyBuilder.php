<?php

declare(strict_types=1);

namespace Drupal\openintranet_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Renders reaction fields as placeholders.
 *
 * The votingapi_reaction formatter outputs a form that varies per session and
 * does not opt in to render caching, so core gives it max-age 0. Rendered
 * inline, that made every page with a reaction field (news lists, articles,
 * basic pages) uncacheable for the Dynamic Page Cache. Built lazily, the form
 * is filled in per request while the rest of the page is cached.
 */
final class ReactionLazyBuilder implements TrustedCallbackInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

  /**
   * Lazy builder: renders one reaction field of an entity.
   */
  public function build(string $entity_type_id, string $entity_id, string $field_name, string $view_mode, string $langcode): array {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    if (!$entity instanceof ContentEntityInterface || !$entity->hasField($field_name)) {
      return [];
    }
    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }
    if (!$entity->access('view')) {
      return [];
    }
    return $entity->get($field_name)->view($view_mode);
  }

}
