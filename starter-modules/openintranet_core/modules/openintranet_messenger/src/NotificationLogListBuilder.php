<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the notification log entity type.
 */
final class NotificationLogListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a new NotificationLogListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter,
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['recipient_name'] = $this->t('Recipient');
    $header['channel'] = $this->t('Channel');
    $header['recipient_address'] = $this->t('Address');
    $header['status'] = $this->t('Status');
    $header['sent_at'] = $this->t('Sent At');
    // No operations column - logs are read-only.
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\openintranet_messenger\NotificationLogInterface $entity */
    $row['id'] = $entity->id();
    $row['recipient_name'] = $entity->getRecipientName();
    $row['channel'] = $entity->getChannel();
    $row['recipient_address'] = $entity->getRecipientAddress();
    $row['status'] = $this->getStatusLabel($entity->getStatus());
    $row['sent_at'] = $entity->getSentTime()
      ? $this->dateFormatter->format($entity->getSentTime(), 'short')
      : '-';
    // No operations column - logs are read-only.
    return $row;
  }

  /**
   * Gets a human-readable status label.
   *
   * @param string $status
   *   The status value.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The status label.
   */
  protected function getStatusLabel(string $status): string|\Drupal\Core\StringTranslation\TranslatableMarkup {
    return match ($status) {
      NotificationLogInterface::STATUS_SENT => $this->t('Sent'),
      NotificationLogInterface::STATUS_FAILED => $this->t('Failed'),
      NotificationLogInterface::STATUS_PENDING => $this->t('Pending'),
      default => $status,
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);
    // Remove all operations - logs are read-only (no edit/delete/clone).
    unset($operations['edit'], $operations['delete'], $operations['clone']);
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): \Drupal\Core\Entity\Query\QueryInterface {
    $query = parent::getEntityListQuery();
    // Sort by ID descending (newest first).
    $query->sort('id', 'DESC');
    return $query;
  }

}
