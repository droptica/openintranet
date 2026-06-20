<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\openintranet_notifications\Channel\ChannelPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read-only overview of the discovered notification channels (§13).
 *
 * Lists each channel with its availability, whether it is globally enabled and
 * its kill-switch state. The full admin dashboard is Stage 4; this page is the
 * minimal status surface.
 */
final class ChannelStatusController extends ControllerBase {

  public function __construct(
    private readonly ChannelPluginManager $channelManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('plugin.manager.notification_channel'),
    );
  }

  /**
   * Builds the channel-status table.
   *
   * @return array
   *   A render array.
   */
  public function overview(): array {
    $settings = $this->config('openintranet_notifications.settings');
    $enabled = (array) $settings->get('enabled_channels');
    $killSwitch = (array) $settings->get('kill_switch');

    $rows = [];
    foreach ($this->channelManager->getDefinitions() as $id => $definition) {
      $rows[] = [
        (string) ($definition['label'] ?? $id),
        $id,
        $this->availabilityCell($id),
        in_array($id, $enabled, TRUE) ? $this->t('Yes') : $this->t('No'),
        !empty($killSwitch[$id]) ? $this->t('Killed') : $this->t('Active'),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Channel'),
        $this->t('ID'),
        $this->t('Available'),
        $this->t('Globally enabled'),
        $this->t('Kill switch'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No notification channels are available.'),
    ];
  }

  /**
   * Resolves a channel's availability cell, isolating a faulty channel.
   *
   * @param string $id
   *   The channel plugin id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   "Yes"/"No", or "Error" when the channel cannot report its availability.
   */
  private function availabilityCell(string $id) {
    try {
      $channel = $this->channelManager->createInstance($id);
      return $channel->isAvailable() ? $this->t('Yes') : $this->t('No');
    }
    catch (\Throwable) {
      return $this->t('Error');
    }
  }

}
