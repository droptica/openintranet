<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationDeliveryPolicy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Drupal\openintranet_notifications\Attribute\NotificationDeliveryPolicy;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Policy\NotificationDeliveryPolicyInterface;
use Drupal\openintranet_notifications\Service\PreferenceResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default policy: deliver on the channels the recipient prefers.
 *
 * Owns the channel-set arithmetic (00-synteza §3.2): the candidate set is the
 * type's default ∪ forced channels, then each channel survives only when it is
 * preferred (or forced), globally enabled, not killed, and the plugin can
 * actually serve this recipient.
 */
#[NotificationDeliveryPolicy(
  id: 'user_preferences',
  label: new TranslatableMarkup('User preferences'),
  description: new TranslatableMarkup('Delivers on the channels the user prefers, keeping forced channels and dropping disabled, killed or unavailable ones.'),
)]
final class UserPreferencesPolicy extends PluginBase implements NotificationDeliveryPolicyInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a UserPreferencesPolicy.
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
    private readonly ChannelPluginManager $channelManager,
    private readonly PreferenceResolverInterface $preferenceResolver,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
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
   * {@inheritdoc}
   */
  public function selectChannels(NotificationTypeInterface $type, NotificationRecipient $recipient, array $context): array {
    if ($recipient->isUser() && $recipient->account instanceof UserInterface && $recipient->account->isBlocked()) {
      return [];
    }

    $settings = $this->configFactory->get('openintranet_notifications.settings');
    $enabledChannels = $settings->get('enabled_channels') ?? [];
    $killSwitch = $settings->get('kill_switch') ?? [];
    $forced = $type->getForcedChannels();

    $candidates = array_unique(array_merge($type->getDefaultChannels(), $forced));
    $selected = [];
    foreach ($candidates as $channelId) {
      $isForced = \in_array($channelId, $forced, TRUE);
      if (!$isForced && !$this->preferenceResolver->isEnabled($recipient->id ?? 0, $type->id(), $channelId)) {
        continue;
      }
      if (!\in_array($channelId, $enabledChannels, TRUE)) {
        continue;
      }
      if (($killSwitch[$channelId] ?? FALSE) === TRUE) {
        continue;
      }
      if (!$this->channelManager->hasDefinition($channelId)) {
        continue;
      }
      $plugin = $this->channelManager->createInstance($channelId);
      if (!$plugin->isAvailable() || !$plugin->canSendTo($recipient)) {
        continue;
      }
      $selected[] = $channelId;
    }

    return $selected;
  }

}
