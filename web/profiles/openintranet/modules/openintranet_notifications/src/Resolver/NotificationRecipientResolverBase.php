<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for notification recipient resolver plugins.
 *
 * Concrete resolvers read their inputs from the dispatch context and from
 * $this->configuration, then build user recipients via buildUserRecipient(),
 * which skips blocked accounts (00-synteza §9).
 */
abstract class NotificationRecipientResolverBase extends PluginBase implements NotificationRecipientResolverInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a NotificationRecipientResolverBase.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
    );
  }

  /**
   * Builds a user recipient, skipping blocked accounts.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to wrap.
   *
   * @return \Drupal\openintranet_notifications\Dto\NotificationRecipient|null
   *   The recipient, or NULL when the user is blocked (00-synteza §9).
   */
  protected function buildUserRecipient(UserInterface $user): ?NotificationRecipient {
    if ($user->isBlocked()) {
      return NULL;
    }
    return new NotificationRecipient(
      type: 'user',
      id: (int) $user->id(),
      langcode: $user->getPreferredLangcode(),
      account: $user,
    );
  }

}
