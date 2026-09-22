<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

/**
 * Builds the embedded "Trending now" carousel render array.
 */
interface ForumTrendingEmbedInterface {

  /**
   * Builds the trending posts view embed.
   *
   * The view is executed once here; the returned renderable carries the
   * already-executed view, so rendering does not run the query again.
   *
   * @return array
   *   The view render array, or a metadata-only render array when the view
   *   has no results (so cached pages still invalidate when the first
   *   trending post appears). Use \Drupal\Core\Render\Element::isEmpty() to
   *   tell the two apart.
   */
  public function build(): array;

}
