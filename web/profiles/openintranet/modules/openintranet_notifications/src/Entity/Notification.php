<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Entity\Handler\NotificationAccessControlHandler;
use Drupal\openintranet_notifications\Entity\Handler\NotificationListBuilder;
use Drupal\views\EntityViewsData;

/**
 * Defines the openintranet_notification content entity.
 *
 * Inbox + runtime/audit record; one row per recipient (00-synteza §4.2).
 */
#[ContentEntityType(
  id: 'openintranet_notification',
  label: new TranslatableMarkup('Notification'),
  label_collection: new TranslatableMarkup('Notifications'),
  label_singular: new TranslatableMarkup('notification'),
  label_plural: new TranslatableMarkup('notifications'),
  label_count: [
    'singular' => '@count notification',
    'plural' => '@count notifications',
  ],
  handlers: [
    'access' => NotificationAccessControlHandler::class,
    'list_builder' => NotificationListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'views_data' => EntityViewsData::class,
  ],
  base_table: 'openintranet_notification',
  admin_permission: 'view notification logs',
  entity_keys: [
    'id' => 'id',
    'label' => 'subject',
    'uuid' => 'uuid',
  ],
  links: [
    'canonical' => '/notifications/{openintranet_notification}',
    'collection' => '/admin/openintranet/notifications',
  ],
)]
final class Notification extends ContentEntityBase implements NotificationInterface {

  /**
   * {@inheritdoc}
   */
  public function setRead(): void {
    $this->set('read_at', \Drupal::time()->getRequestTime());
  }

  /**
   * {@inheritdoc}
   */
  public function setSeen(): void {
    $this->set('seen_at', \Drupal::time()->getRequestTime());
  }

  /**
   * {@inheritdoc}
   */
  public function isRead(): bool {
    return $this->get('read_at')->value !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isSeen(): bool {
    return $this->get('seen_at')->value !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function markDigested(): void {
    $this->set('digested', \Drupal::time()->getRequestTime());
  }

  /**
   * {@inheritdoc}
   */
  public function isDigested(): bool {
    return $this->get('digested')->value !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Notification type'))
      ->setDescription(new TranslatableMarkup('The notification_type machine name.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Subject'))
      ->setSetting('max_length', 255);

    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Body'));

    $fields['summary'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Summary'))
      ->setSetting('max_length', 255);

    $fields['payload'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Payload'))
      ->setDescription(new TranslatableMarkup('Structured data carried with the notification.'));

    $fields['url'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('URL'))
      ->setDescription(new TranslatableMarkup('Optional target the notification links to.'));

    $fields['source_entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(new TranslatableMarkup('Source entity'))
      ->setDescription(new TranslatableMarkup('The entity that triggered the notification.'));

    $fields['actor_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Actor'))
      ->setDescription(new TranslatableMarkup('The user whose action triggered the notification.'))
      ->setSetting('target_type', 'user');

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Recipient'))
      ->setDescription(new TranslatableMarkup('The inbox recipient.'))
      ->setSetting('target_type', 'user');

    $fields['priority'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Priority'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
      ])
      ->setDefaultValue('normal');

    $fields['dedupe_key'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Dedupe key'))
      ->setSetting('max_length', 255);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    $fields['read_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Read at'))
      ->setDescription(new TranslatableMarkup('When the recipient read the notification; NULL means unread.'))
      ->setDefaultValue(NULL);

    $fields['seen_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Seen at'))
      ->setDescription(new TranslatableMarkup('When the recipient saw the notification; NULL means unseen.'))
      ->setDefaultValue(NULL);

    $fields['digested'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Digested at'))
      ->setDescription(new TranslatableMarkup('When the notification was included in a digest; NULL means not yet digested.'))
      ->setDefaultValue(NULL);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'created' => 'Created',
        'resolving' => 'Resolving',
        'queued' => 'Queued',
        'delivered' => 'Delivered',
        'partial' => 'Partial',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
      ])
      ->setDefaultValue('created');

    return $fields;
  }

}
