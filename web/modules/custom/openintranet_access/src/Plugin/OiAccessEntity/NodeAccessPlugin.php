<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Plugin\OiAccessEntity;

use Drupal\Core\Entity\EntityInterface;

/**
 * Access plugin for node entities.
 *
 * @OiAccessEntityPlugin(
 *   id = "node",
 *   label = @Translation("Content (Nodes)"),
 *   entity_type = "node"
 * )
 */
final class NodeAccessPlugin extends OiAccessEntityPluginBase {

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    if ($entity->getEntityTypeId() !== 'node') {
      return FALSE;
    }

    // Check if this node type is enabled in configuration.
    $config = $this->configFactory->get('openintranet_access.settings');
    $enabled_types = $config->get('enabled_entity_types.node') ?? [];

    // Empty array means all types are enabled.
    if (empty($enabled_types)) {
      return TRUE;
    }

    return in_array($entity->bundle(), $enabled_types, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessFormRoute(): string {
    return 'openintranet_access.node_access_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessFormRouteParameters(EntityInterface $entity): array {
    return ['node' => $entity->id()];
  }

}
