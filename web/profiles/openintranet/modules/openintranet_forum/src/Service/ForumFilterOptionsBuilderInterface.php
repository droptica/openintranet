<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

/**
 * Builds select-option arrays for forum filter dropdowns.
 */
interface ForumFilterOptionsBuilderInterface {

  /**
   * Reads and normalizes the forum filters from the current request.
   *
   * @return array
   *   Keyed by q, category, tag, author, sort.
   */
  public function readFilters(): array;

  /**
   * Builds hidden form fields that preserve filters across a GET form.
   *
   * @param array $filters
   *   The normalized filters from ::readFilters().
   * @param string[] $exclude
   *   Filter keys to omit (the ones the form itself submits).
   *
   * @return array
   *   Keyed by field name => value, omitting default/empty values.
   */
  public function buildHiddenFields(array $filters, array $exclude): array;

  /**
   * Returns [tid => indented name] for the category select.
   *
   * The '- Any -' placeholder is added at key 0.
   */
  public function buildCategoryOptions(ForumCategoryTree $tree): array;

  /**
   * Returns [tid => name] for the tag select.
   *
   * The '- Any -' placeholder is added at key 0.
   */
  public function buildTagOptions(): array;

  /**
   * Returns [uid => display_name] for the author select.
   *
   * The '- Any -' placeholder is added at key 0. Uses the distinct-uid query.
   */
  public function buildAuthorOptions(): array;

}
