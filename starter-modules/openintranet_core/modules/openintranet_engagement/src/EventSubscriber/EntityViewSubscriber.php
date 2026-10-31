<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\openintranet_engagement\Service\OiEngagementConfigInterface;
use Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tracks entity views per request.
 *
 * Covers custom routes that show entity data without rendering the entity,
 * and the canonical page of every entity type enabled for view tracking.
 * Views are tracked per request rather than in hook_entity_view(), because
 * that hook does not run when the page or the entity comes from cache.
 */
final class EntityViewSubscriber implements EventSubscriberInterface {

  /**
   * Routes to track as entity views.
   *
   * Format: route_name => [entity_type, event_type, parameter_name]
   */
  private const TRACKED_ROUTES = [
    // Document browser routes (openintranet_documents).
    'openintranet_documents.browser' => [
      'entity_type' => 'oi_folder',
      'event' => 'oi_folder_view',
      'param' => 'oi_folder',
    ],
    'openintranet_documents.document_download' => [
      'entity_type' => 'oi_document',
      'event' => 'oi_document_download',
      'param' => 'oi_document',
    ],
    'entity.oi_folder.canonical' => [
      'entity_type' => 'oi_folder',
      'event' => 'oi_folder_view',
      'param' => 'oi_folder',
    ],
  ];

  /**
   * View tracking cooldown in seconds (prevents spam).
   */
  private const VIEW_COOLDOWN = 300;

  /**
   * Constructs the EntityViewSubscriber.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface $tracker
   *   The tracker service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementConfigInterface $config
   *   The config service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    private readonly OiEngagementTrackerInterface $tracker,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AccountProxyInterface $currentUser,
    private readonly OiEngagementConfigInterface $config,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Use TERMINATE to not slow down the response.
      KernelEvents::TERMINATE => ['onTerminate', 0],
    ];
  }

  /**
   * Tracks entity access on route terminate.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The terminate event.
   */
  public function onTerminate(TerminateEvent $event): void {
    if ($this->currentUser->isAnonymous()) {
      return;
    }

    // No route name on 404 and other exception pages.
    $routeName = (string) $this->routeMatch->getRouteName();

    if (!isset(self::TRACKED_ROUTES[$routeName])) {
      $this->trackCanonicalView($routeName, $event);
      return;
    }

    $config = self::TRACKED_ROUTES[$routeName];
    $entityType = $config['entity_type'];
    $eventName = $config['event'];
    $paramName = $config['param'];

    // Get entity ID from route parameters.
    $entity = $this->routeMatch->getParameter($paramName);

    if (!$entity) {
      return;
    }

    $entityId = is_object($entity) ? $entity->id() : $entity;

    if ($entityId) {
      $this->tracker->track($eventName, $this->currentUser->getAccount(), [
        'entity_type' => $entityType,
        'entity_id' => (int) $entityId,
      ]);
    }
  }

  /**
   * Tracks a view of the entity on its canonical page.
   *
   * Only configured entity types, at most once per user and entity per
   * VIEW_COOLDOWN seconds.
   *
   * @param string $routeName
   *   The current route name.
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The terminate event.
   */
  private function trackCanonicalView(string $routeName, TerminateEvent $event): void {
    if (!$event->getResponse()->isSuccessful() || !preg_match('/^entity\.([a-z0-9_]+)\.canonical$/', $routeName, $matches)) {
      return;
    }
    $entity = $this->routeMatch->getParameter($matches[1]);
    if (!$entity instanceof EntityInterface) {
      return;
    }
    $entityType = $entity->getEntityTypeId();
    if (!$this->config->isEntityTypeEnabled($entityType, 'view')) {
      return;
    }

    $userId = (int) $this->currentUser->id();
    $entityId = (int) $entity->id();
    $cacheKey = "engagement_view:{$userId}:{$entityType}:{$entityId}";
    if ($this->cache->get($cacheKey)) {
      return;
    }
    $this->cache->set($cacheKey, TRUE, time() + self::VIEW_COOLDOWN);

    $this->tracker->track("{$entityType}_view", $this->currentUser->getAccount(), [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      'value' => $this->config->getEntityTypeValue($entityType, 'view'),
    ]);
  }

}
