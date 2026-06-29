<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity\Handler;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;

/**
 * Lists the notification_type config entities (§4.1, §14 admin type list).
 */
final class NotificationTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'label' => $this->t('Label'),
      'id' => $this->t('Machine name'),
      'category' => $this->t('Category'),
      'default_priority' => $this->t('Priority'),
      'default_channels' => $this->t('Default channels'),
      'delivery_policy' => $this->t('Delivery policy'),
      'enabled' => $this->t('Enabled'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof NotificationTypeInterface);
    $row = [
      'label' => $entity->label(),
      'id' => $entity->id(),
      'category' => $entity->getCategory(),
      'default_priority' => $entity->getDefaultPriority(),
      'default_channels' => implode(', ', $entity->getDefaultChannels()),
      'delivery_policy' => $entity->getDeliveryPolicy(),
      'enabled' => $entity->isEnabled() ? $this->t('Yes') : $this->t('No'),
    ];
    return $row + parent::buildRow($entity);
  }

}
