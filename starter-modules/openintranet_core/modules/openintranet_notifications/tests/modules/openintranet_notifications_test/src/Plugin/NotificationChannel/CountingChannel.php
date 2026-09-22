<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_test\Plugin\NotificationChannel;

use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test channel that counts its sends in state, then succeeds.
 *
 * Lets a test assert the worker's idempotency guard prevented a send: the
 * counter at state key openintranet_notifications_test.counting_sends stays 0
 * when the worker short-circuits on a terminal delivery.
 */
#[NotificationChannel(
  id: 'counting',
  label: new TranslatableMarkup('Counting'),
  description: new TranslatableMarkup('Counts sends in state then succeeds; proves the idempotency guard.'),
)]
final class CountingChannel extends NotificationChannelBase {

  /**
   * The state key holding the send counter.
   */
  public const STATE_KEY = 'openintranet_notifications_test.counting_sends';

  /**
   * Constructs a CountingChannel.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service holding the send counter.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly StateInterface $state,
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
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    return 'counting';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $this->state->set(self::STATE_KEY, (int) $this->state->get(self::STATE_KEY, 0) + 1);
    return DeliveryResult::success();
  }

}
