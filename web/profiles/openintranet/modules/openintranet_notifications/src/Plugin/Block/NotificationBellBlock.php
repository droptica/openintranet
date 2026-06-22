<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
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
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
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
      $url = $notification->get('url')->value;
      $items[] = [
        'subject' => $notification->get('subject')->value,
        'url' => $url !== NULL && $url !== '' ? Url::fromUri($url) : NULL,
        'created' => (int) $notification->get('created')->value,
      ];
    }

    return [
      '#theme' => 'openintranet_notification_bell',
      '#count' => $count,
      '#items' => $items,
      '#see_all_url' => Url::fromUserInput('/notifications'),
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
