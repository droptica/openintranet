<?php

declare(strict_types=1);

namespace Drupal\openintranet_core\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\recently_read\Entity\RecentlyReadTypeInterface;
use Drupal\recently_read\RecentlyReadServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Records recently read entities when their canonical page is requested.
 *
 * Request-based, so the read is recorded even when the page comes from the
 * Dynamic Page Cache. Follows the recently_read type configuration the same
 * way the module's own (removed) hook_entity_view() did.
 *
 * @see \Drupal\openintranet_core\Hook\RecentlyReadHooks
 */
final class RecentlyReadSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?RecentlyReadServiceInterface $recentlyRead = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // TERMINATE runs after the response is sent, so it does not slow it down.
    return [KernelEvents::TERMINATE => ['onTerminate', 0]];
  }

  /**
   * Records the read of the entity shown on a canonical entity route.
   */
  public function onTerminate(TerminateEvent $event): void {
    if (!$this->recentlyRead || !$event->getResponse()->isSuccessful()) {
      return;
    }
    $route_name = (string) $this->routeMatch->getRouteName();
    if (!preg_match('/^entity\.([a-z0-9_]+)\.canonical$/', $route_name, $matches)) {
      return;
    }
    $entity = $this->routeMatch->getParameter($matches[1]);
    if (!$entity instanceof EntityInterface) {
      return;
    }
    $type = $this->entityTypeManager->getStorage('recently_read_type')->load($entity->getEntityTypeId());
    if (!$type instanceof RecentlyReadTypeInterface) {
      return;
    }
    // An empty bundle list tracks every bundle of the entity type.
    $bundles = $type->getTypes();
    if ($bundles && !in_array($entity->bundle(), $bundles, TRUE)) {
      return;
    }
    $this->recentlyRead->insertEntity($entity);
  }

}
