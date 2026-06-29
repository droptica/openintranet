<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_test\Plugin\NotificationChannel;

use Drupal\Core\State\StateInterface;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base test channel whose send outcome is toggled from state.
 *
 * Drives the timed-escalation kernel tests (00-synteza §3.2): a test flips a
 * per-channel state key to make the channel succeed, fail permanently, or fail
 * retryably, so the cancel-on-success / leave-on-failure escalation behaviour
 * can be exercised deterministically.
 *
 * @phpstan-consistent-constructor
 */
abstract class ToggleableChannelBase extends NotificationChannelBase {

  /**
   * Constructs a ToggleableChannelBase.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service holding the per-channel outcome toggle.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly StateInterface $state,
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
      $container->get('state'),
    );
  }

  /**
   * The state key holding this channel's outcome toggle.
   *
   * @return string
   *   A state key whose value is 'success', 'permanent' or 'retryable'.
   */
  abstract protected function outcomeStateKey(): string;

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $outcome = (string) $this->state->get($this->outcomeStateKey(), 'success');
    return match ($outcome) {
      'permanent' => DeliveryResult::permanentFailure('E_PERM', 'nope'),
      'retryable' => DeliveryResult::retryableFailure('E_RETRY', 'transient'),
      default => DeliveryResult::success(),
    };
  }

}
