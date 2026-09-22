<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tracks entity views that might not trigger hook_entity_view.
 *
 * Some routes (like custom controllers) may display entity data
 * without rendering the full entity, so we also track route access.
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
   * Constructs the EntityViewSubscriber.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementTrackerInterface $tracker
   *   The tracker service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    private readonly OiEngagementTrackerInterface $tracker,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AccountProxyInterface $currentUser,
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

    $routeName = $this->routeMatch->getRouteName();

    if (!isset(self::TRACKED_ROUTES[$routeName])) {
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

}
