<?php

declare(strict_types=1);

namespace Drupal\openintranet_core\EventSubscriber;

use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Sends department and office term pages to the filtered employee directory.
 *
 * The taxonomy term page is a search view that only lists tagged content, so
 * terms of the people vocabularies had no page of their own (404). Their
 * meaningful page is the employee directory filtered by that term.
 */
final class DirectoryTermRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Employee directory exposed filter identifier, keyed by vocabulary ID.
   */
  private const FILTERS = [
    'department' => 'field_department',
    'office' => 'field_office',
  ];

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After the router (priority 32) has resolved the route and upcast the
    // term, before the view controller runs and rejects it.
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

  /**
   * Redirects a department or office term page to the employee directory.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || $this->routeMatch->getRouteName() !== 'entity.taxonomy_term.canonical') {
      return;
    }
    $term = $this->routeMatch->getParameter('taxonomy_term');
    if (!$term instanceof TermInterface || !isset(self::FILTERS[$term->bundle()])) {
      return;
    }
    try {
      $url = Url::fromRoute('view.employee_directory_view.page_1', [], [
        'query' => [self::FILTERS[$term->bundle()] => $term->id()],
      ])->toString();
    }
    catch (RouteNotFoundException) {
      // The site removed the employee directory; keep the default behavior.
      return;
    }
    $event->setResponse(new LocalRedirectResponse($url));
  }

}
