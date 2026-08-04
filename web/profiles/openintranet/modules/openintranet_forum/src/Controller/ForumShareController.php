<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\Service\ForumStatisticsInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles share AJAX requests for forum posts.
 */
final class ForumShareController extends ControllerBase {

  use AutowireTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly ForumStatisticsInterface $statistics,
  ) {}

  /**
   * Increments the share count for a forum post.
   */
  public function share(NodeInterface $node): JsonResponse {
    if ($node->bundle() !== 'forum_post' || !$node->isPublished()) {
      return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
    }

    $nid = (int) $node->id();

    $updated = $this->database->update('node__field_forum_share_count')
      ->expression('field_forum_share_count_value', 'field_forum_share_count_value + 1')
      ->condition('entity_id', $nid)
      ->condition('bundle', 'forum_post')
      ->execute();

    if (!$updated) {
      $this->database->insert('node__field_forum_share_count')
        ->fields([
          'bundle' => 'forum_post',
          'deleted' => 0,
          'entity_id' => $nid,
          'revision_id' => $nid,
          'langcode' => 'en',
          'delta' => 0,
          'field_forum_share_count_value' => 1,
        ])
        ->execute();
    }

    Cache::invalidateTags(['node:' . $nid]);

    return new JsonResponse(['count' => $this->statistics->getShareCount($nid)]);
  }

}
