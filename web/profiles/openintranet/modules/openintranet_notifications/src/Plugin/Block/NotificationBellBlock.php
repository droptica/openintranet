<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\openintranet_notifications\Service\NotificationActorPresenter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a notification bell block with an unread count and recent dropdown.
 */
#[Block(
  id: 'openintranet_notification_bell',
  admin_label: new TranslatableMarkup('Notification bell'),
)]
final class NotificationBellBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Number of recent notifications listed in the dropdown.
   */
  private const RECENT_LIMIT = 10;

  /**
   * Constructs a NotificationBellBlock.
   *
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter for relative timestamps.
   * @param \Drupal\openintranet_notifications\Service\NotificationActorPresenter $actorPresenter
   *   Resolves each notification's actor name and avatar.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly NotificationActorPresenter $actorPresenter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('openintranet_notifications.actor_presenter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Never render notification data for anonymous users; vary by user so one
    // user's bell is never served to another.
    if ($this->currentUser->isAnonymous()) {
      return ['#cache' => ['contexts' => ['user']]];
    }

    $uid = $this->currentUser->id();
    $storage = $this->entityTypeManager->getStorage('openintranet_notification');

    $count = (int) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->notExists('read_at')
      ->count()
      ->execute();

    $recent_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->range(0, self::RECENT_LIMIT)
      ->execute();

    $items = [];
    foreach ($storage->loadMultiple($recent_ids) as $notification) {
      assert($notification instanceof NotificationInterface);
      $actor = $this->actorPresenter->present($notification);
      $created = (int) $notification->get('created')->value;
      $items[] = [
        'subject' => $notification->get('subject')->value,
        // Canonical view marks the notification read, then redirects to target.
        'url' => $notification->toUrl('canonical'),
        'created_ago' => $this->dateFormatter->formatTimeDiffSince($created, ['granularity' => 1]),
        'is_read' => $notification->isRead(),
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
      ];
    }

    return [
      '#theme' => 'openintranet_notification_bell',
      '#count' => $count,
      '#items' => $items,
      '#see_all_url' => Url::fromRoute('openintranet_notifications.inbox'),
      '#attached' => [
        'library' => ['openintranet_notifications/notification_bell'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => $this->entityTypeManager->getDefinition('openintranet_notification')->getListCacheTags(),
      ],
    ];
  }

}
