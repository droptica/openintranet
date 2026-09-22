<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_messenger\NotificationLogAccessControlHandler;
use Drupal\openintranet_messenger\NotificationLogInterface;
use Drupal\openintranet_messenger\NotificationLogListBuilder;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the notification log entity class.
 */
#[ContentEntityType(
  id: 'notification_log',
  label: new TranslatableMarkup('Notification Log'),
  label_collection: new TranslatableMarkup('Open Intranet - Messenger - Notification Log'),
  label_singular: new TranslatableMarkup('notification log'),
  label_plural: new TranslatableMarkup('notification logs'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'sent_by',
  ],
  handlers: [
    'list_builder' => NotificationLogListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => NotificationLogAccessControlHandler::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/openintranet/messenger/log',
    'canonical' => '/admin/openintranet/messenger/log/{notification_log}',
  ],
  admin_permission: 'view messenger log',
  base_table: 'notification_log',
  label_count: [
    'singular' => '@count notification log',
    'plural' => '@count notification logs',
  ],
)]
class NotificationLog extends ContentEntityBase implements NotificationLogInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      $this->setOwnerId(\Drupal::currentUser()->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientType(): string {
    return $this->get('recipient_type')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientId(): int {
    return (int) $this->get('recipient_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientName(): string {
    return $this->get('recipient_name')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getChannel(): string {
    return $this->get('channel')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(): string {
    return $this->get('recipient_address')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return $this->get('status')->value ?? self::STATUS_PENDING;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): self {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorMessage(): ?string {
    return $this->get('error_message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setErrorMessage(string $message): self {
    $this->set('error_message', $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSentTime(): int {
    return (int) $this->get('sent_at')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['recipient_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recipient Type'))
      ->setDescription(t('The type of recipient (user or contact).'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 20);

    $fields['recipient_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Recipient ID'))
      ->setDescription(t('The ID of the recipient entity.'))
      ->setRequired(TRUE);

    $fields['recipient_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recipient Name'))
      ->setDescription(t('The name of the recipient (denormalized for display).'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['channel'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Channel'))
      ->setDescription(t('The notification channel used (email, sms, etc.).'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 50);

    $fields['recipient_address'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recipient Address'))
      ->setDescription(t('The address used for delivery (email, phone, etc.).'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subject'))
      ->setDescription(t('The notification subject.'))
      ->setSetting('max_length', 255);

    $fields['message'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Message'))
      ->setDescription(t('The notification message content.'));

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The delivery status.'))
      ->setRequired(TRUE)
      ->setDefaultValue(NotificationLogInterface::STATUS_PENDING)
      ->setSetting('allowed_values', [
        NotificationLogInterface::STATUS_PENDING => 'Pending',
        NotificationLogInterface::STATUS_SENT => 'Sent',
        NotificationLogInterface::STATUS_FAILED => 'Failed',
      ]);

    $fields['error_message'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Error Message'))
      ->setDescription(t('The error message if delivery failed.'))
      ->setSetting('max_length', 1024);

    $fields['sent_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Sent At'))
      ->setDescription(t('The timestamp when the notification was sent.'));

    $fields['sent_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sent By'))
      ->setDescription(t('The user who initiated the notification.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner');

    return $fields;
  }

}
