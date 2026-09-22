<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_test\Plugin\NotificationChannel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test channel that records the in-flight delivery status, then succeeds.
 *
 * Proves the shared sender stamps and PERSISTS status 'processing' before the
 * channel call (00-synteza §4.3): during send() this channel reads back the
 * persisted delivery statuses straight from storage, so a row at 'processing'
 * is observable in-flight, before the terminal markSent() rolls it to 'sent'.
 */
#[NotificationChannel(
  id: 'processing_probe',
  label: new TranslatableMarkup('Processing probe'),
  description: new TranslatableMarkup('Records the in-flight delivery status then succeeds; proves the processing status.'),
)]
final class ProcessingProbeChannel extends NotificationChannelBase {

  /**
   * The state key holding the statuses observed mid-send.
   */
  public const STATE_KEY = 'openintranet_notifications_test.processing_probe_statuses';

  /**
   * Constructs a ProcessingProbeChannel.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service holding the observed statuses.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager used to read back delivery rows mid-send.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly StateInterface $state,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    return 'probe';
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $storage = $this->entityTypeManager->getStorage('openintranet_notif_delivery');
    $storage->resetCache();
    $statuses = [];
    foreach ($storage->loadMultiple() as $delivery) {
      \assert($delivery instanceof NotificationDeliveryInterface);
      $statuses[] = (string) $delivery->get('status')->value;
    }
    $this->state->set(self::STATE_KEY, $statuses);

    return DeliveryResult::success();
  }

}
