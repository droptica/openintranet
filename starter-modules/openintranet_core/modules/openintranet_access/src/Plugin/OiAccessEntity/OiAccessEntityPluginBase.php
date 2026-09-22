<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Plugin\OiAccessEntity;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openintranet_access\Service\OiAccessCheckerInterface;
use Drupal\openintranet_access\Service\OiAccessManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for OI Access Entity plugins.
 */
abstract class OiAccessEntityPluginBase extends PluginBase implements OiAccessEntityPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly OiAccessCheckerInterface $accessChecker,
    protected readonly OiAccessManagerInterface $accessManager,
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
      $container->get('config.factory'),
      $container->get('openintranet_access.checker'),
      $container->get('openintranet_access.access_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->pluginDefinition['entity_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    // By default, apply to all entities of this type.
    return $entity->getEntityTypeId() === $this->getEntityTypeId();
  }

  /**
   * {@inheritdoc}
   */
  public function buildAccessForm(array $form, FormStateInterface $form_state, EntityInterface $entity): array {
    // Default implementation does nothing - form is built by EntityAccessForm.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitAccessForm(array &$form, FormStateInterface $form_state, EntityInterface $entity): void {
    // Default implementation does nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, AccountInterface $account, string $operation): ?bool {
    // Default implementation returns NULL to defer to standard logic.
    return NULL;
  }

}
