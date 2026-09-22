<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Builds the embedded "Trending now" carousel render array.
 *
 * Shared by the feed controller and the listing-page preprocess so the
 * emptiness check and the embed execute the forum_trending view only once.
 */
final class ForumTrendingEmbed implements ForumTrendingEmbedInterface {

  private const VIEW_ID = 'forum_trending';
  private const DISPLAY_ID = 'block_1';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $view_entity = $this->entityTypeManager->getStorage('view')->load(self::VIEW_ID);
    if (!$view_entity instanceof ViewEntityInterface) {
      return [];
    }

    $view = $view_entity->getExecutable();
    if (!$view->access(self::DISPLAY_ID)) {
      return [];
    }

    $view->setDisplay(self::DISPLAY_ID);
    $view->preExecute();
    $view->execute();

    if ($view->result === []) {
      // Nothing to show, but keep the view's cacheability (config tag, the
      // node_list tags, contexts) so cached pages are invalidated when the
      // first trending post appears.
      $build = [];
      CacheableMetadata::createFromObject($view->getDisplay()->getCacheMetadata())
        ->addCacheTags($view->getCacheTags())
        ->applyTo($build);
      return $build;
    }

    // The renderable carries the executed view; ViewExecutable::execute()
    // returns early at render time, so the query does not run again.
    return $view->buildRenderable(self::DISPLAY_ID, [], FALSE) ?? [];
  }

}
