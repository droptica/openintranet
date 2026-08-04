<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the messenger contact entity type.
 */
final class MessengerContactListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Name');
    $header['email'] = $this->t('Email');
    $header['phone'] = $this->t('Phone');
    $header['preferred_channel'] = $this->t('Preferred Channel');
    $header['active'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\openintranet_messenger\MessengerContactInterface $entity */
    $channelLabels = [
      'email' => $this->t('Email'),
      'sms' => $this->t('SMS'),
      'both' => $this->t('Both'),
    ];
    $channel = $entity->getPreferredChannel();

    $row['id'] = $entity->id();
    $row['name'] = $entity->toLink();
    $row['email'] = $entity->getEmail();
    $row['phone'] = $entity->getPhone() ?: '-';
    $row['preferred_channel'] = $channelLabels[$channel] ?? $channel;
    $row['active'] = $entity->isActive() ? $this->t('Active') : $this->t('Inactive');
    return $row + parent::buildRow($entity);
  }

}
