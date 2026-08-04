<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
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
    private readonly TimeInterface $time,
    private readonly AccessManagerInterface $accessManager,
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
      $container->get('datetime.time'),
      $container->get('access_manager'),
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
      ->condition('status', 'cancelled', '<>')
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->pager(self::INBOX_PAGE_SIZE)
      ->execute();
    /** @var \Drupal\openintranet_notifications\Entity\NotificationInterface[] $notifications */
    $notifications = $storage->loadMultiple($ids);

    $new_count = count(array_filter(
      $notifications,
      static fn (NotificationInterface $notification): bool => !$notification->isRead(),
    ));
    $this->markRead($notifications);

    $items = [];
    foreach ($notifications as $notification) {
      $created = (int) $notification->get('created')->value;
      $actor = $this->actorPresenter->present($notification);
      $date_group = $this->getDateGroup($created);
      $items[] = [
        'url' => $notification->toUrl('canonical'),
        'subject' => $notification->get('subject')->value ?? $notification->label(),
        'created_ago' => $this->t('@time ago', [
          '@time' => $this->dateFormatter->formatTimeDiffSince($created, ['granularity' => 1]),
        ]),
        'created_iso' => gmdate(DATE_ATOM, $created),
        'created_full' => $this->dateFormatter->format($created, 'custom', 'F j, Y, g:i a'),
        'date_key' => $date_group['key'],
        'date_label' => $date_group['label'],
        'is_read' => $notification->isRead(),
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
      ];
    }

    $preferences_route_parameters = ['user' => $this->account->id()];
    $preferences_url = NULL;
    if ($this->accessManager->checkNamedRoute(
      'openintranet_notifications.user_preferences',
      $preferences_route_parameters,
      $this->account,
    )) {
      $preferences_url = Url::fromRoute(
        'openintranet_notifications.user_preferences',
        $preferences_route_parameters,
      );
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-inbox']],
      'list' => [
        '#theme' => 'openintranet_notifications_inbox',
        '#items' => $items,
        '#new_count' => $new_count,
        '#preferences_url' => $preferences_url,
        '#pager' => [
          '#type' => 'pager',
        ],
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
   * Builds a calendar group for a notification timestamp.
   *
   * @return array{key: string, label: \Drupal\Core\StringTranslation\TranslatableMarkup|string}
   *   A stable date key and a user-facing label.
   */
  private function getDateGroup(int $created): array {
    $date_key = $this->dateFormatter->format($created, 'custom', 'Y-m-d');
    $today_key = $this->dateFormatter->format(
      $this->time->getRequestTime(),
      'custom',
      'Y-m-d',
    );
    $yesterday_key = $this->dateFormatter->format(
      $this->time->getRequestTime() - 86400,
      'custom',
      'Y-m-d',
    );

    if ($date_key === $today_key) {
      $label = $this->t('Today');
    }
    elseif ($date_key === $yesterday_key) {
      $label = $this->t('Yesterday');
    }
    else {
      $label = $this->dateFormatter->format($created, 'custom', 'F j, Y');
    }

    return [
      'key' => $date_key,
      'label' => $label,
    ];
  }

  /**
   * Views a single notification, marking it read.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $openintranet_notification
   *   The notification (upcast route parameter).
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to a safe local target when set, an inbox redirect for a
   *   disallowed external target, or a render array showing the notification.
   */
  public function view(NotificationInterface $openintranet_notification): array|RedirectResponse {
    if (!$openintranet_notification->isRead()) {
      $openintranet_notification->setRead();
      $openintranet_notification->save();
    }

    $url = $openintranet_notification->get('url')->value;
    if ($url !== NULL && $url !== '') {
      // Stored targets are not trusted. LocalRedirectResponse allows internal
      // paths and same-site absolute URLs, while rejecting other origins.
      try {
        return new LocalRedirectResponse($url);
      }
      catch (\InvalidArgumentException) {
        // Keep the user on this site when a stored target is unsafe.
        return $this->redirect('openintranet_notifications.inbox');
      }
    }

    return [
      'subject' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $openintranet_notification->get('subject')->value ?? $openintranet_notification->label(),
      ],
      'body' => $openintranet_notification->get('body')->view([
        'label' => 'hidden',
        'type' => 'text_default',
      ]),
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
