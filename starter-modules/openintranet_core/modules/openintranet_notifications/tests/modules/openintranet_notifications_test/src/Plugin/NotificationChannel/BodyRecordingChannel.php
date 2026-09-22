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
 * Test channel that records the subject/body it was handed, then succeeds.
 *
 * Lets a test assert which message reached this channel — proving the
 * per-channel template_map render (00-synteza §4.1): when the type maps this
 * channel to a template, the body recorded here is the channel-specific render,
 * not the channel-agnostic stored body.
 */
#[NotificationChannel(
  id: 'body_recording',
  label: new TranslatableMarkup('Body recording'),
  description: new TranslatableMarkup('Records the message subject/body it received in state.'),
)]
final class BodyRecordingChannel extends NotificationChannelBase {

  /**
   * The state key holding the last recorded subject/body.
   */
  public const STATE_KEY = 'openintranet_notifications_test.body_recording';

  /**
   * Constructs a BodyRecordingChannel.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service holding the recorded message.
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
    return 'body-recording';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $this->state->set(self::STATE_KEY, [
      'subject' => $message->subject,
      'body' => $message->body,
    ]);
    return DeliveryResult::success();
  }

}
