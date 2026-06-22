<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Event\NotificationEvents;
use Drupal\openintranet_notifications\Event\NotificationSeenEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * User-facing /notifications inbox + single notification view (Chunk 5C).
 *
 * The inbox lists the current user's own notifications (newest first) and, as a
 * side effect of rendering, marks the listed unseen ones seen — firing
 * NotificationEvents::SEEN for each. The single view marks a notification read.
 * Own-only access is enforced by the route requirements and the inbox query.
 */
final class NotificationInboxController extends ControllerBase {

  public function __construct(
    private readonly AccountInterface $account,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * Lists the current user's notifications and marks the unseen ones seen.
   *
   * @return array
   *   A render array.
   */
  public function inbox(): array {
    $storage = $this->entityTypeManager()->getStorage('openintranet_notification');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', (int) $this->account->id())
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->execute();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface[] $notifications */
    $notifications = $storage->loadMultiple($ids);

    $items = [];
    foreach ($notifications as $notification) {
      $created = (int) $notification->get('created')->value;
      $items[] = [
        '#wrapper_attributes' => [
          'class' => [$notification->isRead() ? 'notification--read' : 'notification--unread'],
        ],
        'subject' => [
          '#type' => 'link',
          '#title' => $notification->get('subject')->value ?? $notification->label(),
          '#url' => $notification->toUrl('canonical'),
        ],
        'created' => [
          '#markup' => $created > 0
            ? ' (' . $this->dateFormatter->format($created, 'short') . ')'
            : '',
        ],
      ];
    }

    $this->markSeen($notifications);

    return [
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#empty' => $this->t('You have no notifications.'),
      ],
      // Rendering the inbox marks notifications seen, so it must never be
      // served from cache.
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Views a single notification, marking it read.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $openintranet_notification
   *   The notification (upcast route parameter).
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the notification's target URL when set, otherwise a render
   *   array showing the subject and body.
   */
  public function view(NotificationInterface $openintranet_notification): array|RedirectResponse {
    if (!$openintranet_notification->isRead()) {
      $openintranet_notification->setRead();
      $openintranet_notification->save();
    }

    $url = $openintranet_notification->get('url')->value;
    if ($url !== NULL && $url !== '') {
      // The URL is operator/dispatcher-provided, not request input; redirect to
      // it (it may be an external target).
      return new TrustedRedirectResponse($url);
    }

    return [
      'subject' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $openintranet_notification->get('subject')->value ?? $openintranet_notification->label(),
      ],
      'body' => $openintranet_notification->get('body')->view('full'),
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Marks the given notifications seen and fires the seen event for each.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface[] $notifications
   *   The notifications listed in the inbox.
   */
  private function markSeen(array $notifications): void {
    foreach ($notifications as $notification) {
      if ($notification->get('seen_at')->value !== NULL) {
        continue;
      }
      $notification->setSeen();
      $notification->save();
      $this->eventDispatcher->dispatch(
        new NotificationSeenEvent($notification),
        NotificationEvents::SEEN,
      );
    }
  }

}
