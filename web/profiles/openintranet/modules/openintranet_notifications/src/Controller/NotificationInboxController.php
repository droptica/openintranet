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
use Drupal\openintranet_notifications\Service\NotificationActorPresenter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * User-facing /notifications inbox + single notification view (Chunk 5C).
 *
 * The inbox lists the current user's own notifications (newest first) and, as a
 * side effect of rendering, marks the listed unread ones read (00-synteza §10:
 * "mark-as-read po wejściu") — read implies seen, so it also stamps seen_at and
 * fires NotificationEvents::SEEN for the freshly-seen ones. The single view
 * marks a notification read. Own-only access is enforced by the route
 * requirements and the inbox query.
 */
final class NotificationInboxController extends ControllerBase {

  /**
   * Notifications listed (and marked read) per page.
   */
  private const INBOX_PAGE_SIZE = 25;

  public function __construct(
    private readonly AccountInterface $account,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly NotificationActorPresenter $actorPresenter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('event_dispatcher'),
      $container->get('openintranet_notifications.actor_presenter'),
    );
  }

  /**
   * Lists the current user's notifications and marks the unread ones read.
   *
   * The listing is bounded to the most recent INBOX_LIMIT notifications, and
   * the mark-read side effect (00-synteza §10) is bounded to that same listed
   * set.
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
      ->pager(self::INBOX_PAGE_SIZE)
      ->execute();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface[] $notifications */
    $notifications = $storage->loadMultiple($ids);

    $items = [];
    foreach ($notifications as $notification) {
      $created = (int) $notification->get('created')->value;
      $actor = $this->actorPresenter->present($notification);
      $items[] = [
        'url' => $notification->toUrl('canonical'),
        'subject' => $notification->get('subject')->value ?? $notification->label(),
        'created_ago' => $this->dateFormatter->formatTimeDiffSince($created, ['granularity' => 1]),
        'is_read' => $notification->isRead(),
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
      ];
    }

    $this->markRead($notifications);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-inbox']],
      'list' => [
        '#theme' => 'openintranet_notifications_inbox',
        '#items' => $items,
      ],
      'pager' => [
        '#type' => 'pager',
      ],
      // Rendering the inbox marks notifications read, so it must never be
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
   * Marks the given notifications read on inbox entry (00-synteza §10).
   *
   * Read implies seen: an unread notification is stamped read and, when not yet
   * seen, also stamped seen with NotificationEvents::SEEN fired for it. An
   * already-read notification is skipped (idempotent — its read_at/seen_at are
   * preserved and it is not re-saved).
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface[] $notifications
   *   The notifications listed in the inbox.
   */
  private function markRead(array $notifications): void {
    foreach ($notifications as $notification) {
      if ($notification->isRead()) {
        continue;
      }
      $notification->setRead();
      $newlySeen = !$notification->isSeen();
      if ($newlySeen) {
        $notification->setSeen();
      }
      $notification->save();
      if ($newlySeen) {
        $this->eventDispatcher->dispatch(
          new NotificationSeenEvent($notification),
          NotificationEvents::SEEN,
        );
      }
    }
  }

}
