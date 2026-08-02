<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\openintranet_messenger\Channel\ChannelPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for messenger admin pages.
 */
final class MessengerController extends ControllerBase {

  /**
   * The channel plugin manager.
   *
   * @var \Drupal\openintranet_messenger\Channel\ChannelPluginManager
   */
  protected ChannelPluginManager $channelManager;

  /**
   * Constructs a MessengerController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\openintranet_messenger\Channel\ChannelPluginManager $channel_manager
   *   The channel plugin manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ChannelPluginManager $channel_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->channelManager = $channel_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.notification_channel'),
    );
  }

  /**
   * Dashboard page.
   *
   * @return array
   *   Render array.
   */
  public function dashboard(): array {
    $build = [];

    // Wrapper for Gin theme white background.
    $build['#prefix'] = '<div class="gin-layer-wrapper">';
    $build['#suffix'] = '</div>';

    // Summary statistics.
    $build['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messenger-summary']],
    ];

    // Contact count.
    $contact_count = $this->entityTypeManager
      ->getStorage('messenger_contact')
      ->getQuery()
      ->accessCheck(TRUE)
      ->count()
      ->execute();

    $active_contact_count = $this->entityTypeManager
      ->getStorage('messenger_contact')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('active', TRUE)
      ->count()
      ->execute();

    $build['summary']['contacts'] = [
      '#type' => 'item',
      '#title' => $this->t('Contacts'),
      '#markup' => $this->t('@active active of @total total', [
        '@active' => $active_contact_count,
        '@total' => $contact_count,
      ]),
    ];

    // Recent notifications.
    $recent_count = $this->entityTypeManager
      ->getStorage('notification_log')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('sent_at', strtotime('-24 hours'), '>=')
      ->count()
      ->execute();

    $build['summary']['recent'] = [
      '#type' => 'item',
      '#title' => $this->t('Notifications (last 24h)'),
      '#markup' => $recent_count,
    ];

    // Available channels.
    $available_channels = $this->channelManager->getAvailableChannels();
    $channel_names = array_map(fn($c) => $c->getLabel(), $available_channels);

    $build['summary']['channels'] = [
      '#type' => 'item',
      '#title' => $this->t('Available Channels'),
      '#markup' => !empty($channel_names) ? implode(', ', $channel_names) : $this->t('None'),
    ];

    // Quick links.
    $build['links'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messenger-links']],
    ];

    $build['links']['send'] = [
      '#type' => 'link',
      '#title' => $this->t('Send Notification'),
      '#url' => Url::fromRoute('openintranet_messenger.send'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $build['links']['contacts'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage Contacts'),
      '#url' => Url::fromRoute('entity.messenger_contact.collection'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    $build['links']['log'] = [
      '#type' => 'link',
      '#title' => $this->t('View Log'),
      '#url' => Url::fromRoute('openintranet_messenger.log'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    $build['links']['settings'] = [
      '#type' => 'link',
      '#title' => $this->t('Settings'),
      '#url' => Url::fromRoute('openintranet_messenger.settings'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $build;
  }

  /**
   * Notification log page.
   *
   * @return array
   *   Render array.
   */
  public function log(): array {
    // Use the entity list builder.
    $list_builder = $this->entityTypeManager->getListBuilder('notification_log');
    return $list_builder->render();
  }

}
