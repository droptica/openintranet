<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Policy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\user\UserInterface;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Service\PreferenceResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base for delivery policies, owning the shared candidate-set filter.
 *
 * Every policy works from the same raw candidate set (the type's default ∪
 * forced channels) and the same usability gate (globally enabled, not killed,
 * plugin present, available and able to address the recipient). Subclasses add
 * only their own selection rule on top (00-synteza §3.2).
 *
 * @phpstan-consistent-constructor
 */
abstract class NotificationDeliveryPolicyBase extends PluginBase implements NotificationDeliveryPolicyInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a NotificationDeliveryPolicyBase.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\openintranet_notifications\Channel\ChannelPluginManager $channelManager
   *   The channel plugin manager.
   * @param \Drupal\openintranet_notifications\Service\PreferenceResolverInterface $preferenceResolver
   *   The preference resolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly ChannelPluginManager $channelManager,
    protected readonly PreferenceResolverInterface $preferenceResolver,
    protected readonly ConfigFactoryInterface $configFactory,
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
      $container->get('plugin.manager.notification_channel'),
      $container->get('openintranet_notifications.preference_resolver'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) ($this->getPluginDefinition()['label'] ?? $this->getPluginId());
  }

  /**
   * Whether the recipient is a user account that is blocked.
   *
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient identity.
   *
   * @return bool
   *   TRUE when the recipient is a blocked user (delivery must be suppressed).
   */
  protected function isBlocked(NotificationRecipient $recipient): bool {
    return $recipient->isUser()
      && $recipient->account instanceof UserInterface
      && $recipient->account->isBlocked();
  }

  /**
   * The raw candidate channels for a type, in default-then-forced order.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationTypeInterface $type
   *   The notification type.
   *
   * @return string[]
   *   The de-duplicated candidate channel ids (default ∪ forced).
   */
  protected function candidateChannels(NotificationTypeInterface $type): array {
    return array_values(array_unique(array_merge(
      $type->getDefaultChannels(),
      $type->getForcedChannels(),
    )));
  }

  /**
   * Whether a channel can actually carry a delivery to this recipient.
   *
   * The channel-set gate every policy shares: globally enabled, not killed,
   * the plugin exists, is available and can address the recipient. It does NOT
   * apply the per-policy selection rule (preference, priority tier, …).
   *
   * @param string $channelId
   *   The channel plugin id.
   * @param \Drupal\openintranet_notifications\Dto\NotificationRecipient $recipient
   *   The recipient identity.
   *
   * @return bool
   *   TRUE when the channel is usable for this recipient.
   */
  protected function channelUsable(string $channelId, NotificationRecipient $recipient): bool {
    $settings = $this->configFactory->get('openintranet_notifications.settings');
    $enabledChannels = $settings->get('enabled_channels') ?? [];
    $killSwitch = $settings->get('kill_switch') ?? [];

    if (!\in_array($channelId, $enabledChannels, TRUE)) {
      return FALSE;
    }
    if (($killSwitch[$channelId] ?? FALSE) === TRUE) {
      return FALSE;
    }
    if (!$this->channelManager->hasDefinition($channelId)) {
      return FALSE;
    }
    $plugin = $this->channelManager->createInstance($channelId);
    return $plugin->isAvailable() && $plugin->canSendTo($recipient);
  }

}
