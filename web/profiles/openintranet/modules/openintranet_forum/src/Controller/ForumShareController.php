<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Controller;

use Drupal\Core\Controller\ControllerBase;
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
    private readonly ForumStatisticsInterface $statistics,
  ) {}

  /**
   * Increments the share count for a forum post.
   */
  public function share(NodeInterface $node): JsonResponse {
    if ($node->bundle() !== 'forum_post' || !$node->isPublished()) {
      return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
    }

    return new JsonResponse([
      'count' => $this->statistics->incrementShareCount((int) $node->id()),
    ]);
  }

}
