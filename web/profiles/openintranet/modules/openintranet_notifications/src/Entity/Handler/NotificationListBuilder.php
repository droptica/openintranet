<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity\Handler;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists openintranet_notification records (§4.2, §14 notification dashboard).
 */
final class NotificationListBuilder extends EntityListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
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
    $header = [
      'id' => $this->t('ID'),
      'type' => $this->t('Type'),
      'uid' => $this->t('Recipient'),
      'subject' => $this->t('Subject'),
      'priority' => $this->t('Priority'),
      'status' => $this->t('Status'),
      'created' => $this->t('Created'),
      'read' => $this->t('Read'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof NotificationInterface);
    $created = (int) $entity->get('created')->value;
    $row = [
      'id' => $entity->id(),
      'type' => $entity->get('type')->value,
      'uid' => $entity->get('uid')->target_id,
      'subject' => $entity->get('subject')->value,
      'priority' => $entity->get('priority')->value,
      'status' => $entity->get('status')->value,
      'created' => $created > 0 ? $this->dateFormatter->format($created, 'short') : '',
      'read' => $entity->get('read_at')->value !== NULL ? $this->t('Yes') : $this->t('No'),
    ];
    return $row + parent::buildRow($entity);
  }

}
