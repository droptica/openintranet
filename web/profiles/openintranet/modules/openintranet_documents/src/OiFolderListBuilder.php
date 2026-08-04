<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the OI Folder entity type.
 */
final class OiFolderListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Name');
    $header['parent'] = $this->t('Parent');
    $header['status'] = $this->t('Status');
    $header['uid'] = $this->t('Author');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\openintranet_documents\OiFolderInterface $entity */
    $row['id'] = $entity->id();
    $row['name'] = $entity->toLink();
    $row['parent'] = $entity->getParent()?->toLink() ?? '-';
    $row['status'] = $entity->isActive() ? $this->t('Active') : $this->t('Inactive');
    $row['uid']['data'] = $entity->get('uid')->view(['label' => 'hidden']);
    $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
    return $row + parent::buildRow($entity);
  }

}
