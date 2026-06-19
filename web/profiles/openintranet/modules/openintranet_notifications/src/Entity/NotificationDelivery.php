<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Entity\Handler\NotificationDeliveryAccessControlHandler;

/**
 * Defines the notification_delivery content entity.
 *
 * One recipient × one channel attempt; audit-critical (00-synteza §4.3).
 *
 * The entity type id is abbreviated to openintranet_notif_delivery because
 * Drupal caps content entity type ids at 32 characters; the documented
 * openintranet_notification_delivery (34) would not install.
 */
#[ContentEntityType(
  id: 'openintranet_notif_delivery',
  label: new TranslatableMarkup('Notification delivery'),
  label_collection: new TranslatableMarkup('Notification deliveries'),
  label_singular: new TranslatableMarkup('notification delivery'),
  label_plural: new TranslatableMarkup('notification deliveries'),
  label_count: [
    'singular' => '@count notification delivery',
    'plural' => '@count notification deliveries',
  ],
  handlers: [
    'access' => NotificationDeliveryAccessControlHandler::class,
  ],
  base_table: 'openintranet_notif_delivery',
  admin_permission: 'view notification logs',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
)]
final class NotificationDelivery extends ContentEntityBase implements NotificationDeliveryInterface {

  /**
   * Statuses past which no further send attempt is allowed.
   */
  private const TERMINAL_STATUSES = ['sent', 'delivered', 'cancelled'];

  /**
   * {@inheritdoc}
   */
  public function markSent(?string $providerMessageId = NULL): void {
    $this->set('status', 'sent');
    $this->set('sent', \Drupal::time()->getRequestTime());
    if ($providerMessageId !== NULL) {
      $this->set('provider_message_id', $providerMessageId);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function markFailed(DeliveryResult $result): void {
    $this->set('status', 'failed');
    $this->set('last_error_code', $result->errorCode);
    $this->set('last_error_message', $result->errorMessage);
  }

  /**
   * {@inheritdoc}
   */
  public function scheduleRetry(int $delaySec): void {
    $this->set('status', 'pending');
    $this->set('next_attempt', \Drupal::time()->getRequestTime() + $delaySec);
  }

  /**
   * {@inheritdoc}
   */
  public function isTerminal(): bool {
    return \in_array($this->get('status')->value, self::TERMINAL_STATUSES, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['notification_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Notification'))
      ->setDescription(new TranslatableMarkup('The notification this delivery belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'openintranet_notification');

    $fields['recipient_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Recipient type'))
      ->setDescription(new TranslatableMarkup('user|email|phone|endpoint|external.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32);

    $fields['recipient_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Recipient id'))
      ->setDescription(new TranslatableMarkup('The user id when recipient_type is user; NULL otherwise.'));

    $fields['contact_value'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Contact value'))
      ->setDescription(new TranslatableMarkup('The raw contact value when there is no user id.'))
      ->setSetting('max_length', 255);

    $fields['channel'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Channel'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    $fields['address'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Address'))
      ->setDescription(new TranslatableMarkup('The transport address the channel resolved.'))
      ->setSetting('max_length', 255);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
        'cancelled' => 'Cancelled',
      ])
      ->setDefaultValue('pending');

    $fields['attempt_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Attempt count'))
      ->setDefaultValue(0);

    $fields['next_attempt'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Next attempt'))
      ->setDescription(new TranslatableMarkup('When the worker may try again; 0 means immediately.'))
      ->setDefaultValue(0);

    $fields['last_error_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Last error code'))
      ->setSetting('max_length', 255);

    $fields['last_error_message'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Last error message'))
      ->setSetting('max_length', 255);

    $fields['provider_message_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Provider message id'))
      ->setSetting('max_length', 255);

    $fields['idempotency_key'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Idempotency key'))
      ->setDescription(new TranslatableMarkup('Guards against a double send for the same recipient/channel.'))
      ->setSetting('max_length', 255);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'));

    $fields['sent'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Sent'))
      ->setDescription(new TranslatableMarkup('When the channel reported the message sent; NULL until then.'))
      ->setDefaultValue(NULL);

    return $fields;
  }

}
