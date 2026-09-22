<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Service\DeliveryQueue;
use Drupal\openintranet_notifications\Service\RetentionPurger;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for notification operations.
 */
final class NotificationCommands extends DrushCommands {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DeliveryQueue $deliveryQueue,
    private readonly RetentionPurger $retentionPurger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('openintranet_notifications.delivery_queue'),
      $container->get('openintranet_notifications.retention_purger'),
    );
  }

  /**
   * Re-queues failed deliveries for another delivery attempt.
   */
  #[CLI\Command(name: 'openintranet_notifications:retry-failed', aliases: ['oin-retry'])]
  #[CLI\Option(name: 'limit', description: 'Maximum number of failed deliveries to re-queue.')]
  #[CLI\Usage(name: 'openintranet_notifications:retry-failed', description: 'Re-queue every failed delivery.')]
  #[CLI\Usage(name: 'openintranet_notifications:retry-failed --limit=50', description: 'Re-queue up to 50 failed deliveries.')]
  public function retryFailed(array $options = ['limit' => NULL]): void {
    $storage = $this->entityTypeManager->getStorage('openintranet_notif_delivery');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'failed');
    $limit = $options['limit'] ?? NULL;
    if ($limit !== NULL) {
      $query->range(0, (int) $limit);
    }
    $ids = $query->execute();

    $count = 0;
    foreach ($storage->loadMultiple($ids) as $delivery) {
      if ($delivery instanceof NotificationDeliveryInterface) {
        $this->deliveryQueue->requeue($delivery);
        $count++;
      }
    }

    $this->logger()->success(dt('Re-queued @count failed deliveries.', ['@count' => $count]));
  }

  /**
   * Purges notifications past their retention window.
   */
  #[CLI\Command(name: 'openintranet_notifications:purge', aliases: ['oin-purge'])]
  #[CLI\Option(name: 'limit', description: 'Maximum number of notifications to purge in this run.')]
  #[CLI\Usage(name: 'openintranet_notifications:purge', description: 'Purge all notifications past their retention window.')]
  #[CLI\Usage(name: 'openintranet_notifications:purge --limit=500', description: 'Purge up to 500 expired notifications.')]
  public function purge(array $options = ['limit' => NULL]): void {
    $limit = isset($options['limit']) ? (int) $options['limit'] : NULL;
    $count = $this->retentionPurger->purge($limit);
    $this->logger()->success(dt('Purged @count notifications.', ['@count' => $count]));
  }

}
