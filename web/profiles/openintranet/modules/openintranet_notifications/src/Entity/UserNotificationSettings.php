<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Entity\Handler\UserNotificationSettingsAccessControlHandler;

/**
 * Defines the user_notification_settings content entity.
 *
 * One row per user (1:1): the preference matrix, quiet hours and the language
 * override (00-synteza §4.4).
 */
#[ContentEntityType(
  id: 'user_notification_settings',
  label: new TranslatableMarkup('User notification settings'),
  label_collection: new TranslatableMarkup('User notification settings'),
  label_singular: new TranslatableMarkup('user notification settings'),
  label_plural: new TranslatableMarkup('user notification settings'),
  label_count: [
    'singular' => '@count user notification settings',
    'plural' => '@count user notification settings',
  ],
  handlers: [
    'access' => UserNotificationSettingsAccessControlHandler::class,
  ],
  base_table: 'user_notification_settings',
  admin_permission: 'administer own notification preferences',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
)]
final class UserNotificationSettings extends ContentEntityBase implements UserNotificationSettingsInterface {

  /**
   * {@inheritdoc}
   */
  public function getPreferences(): array {
    // The map field stores the whole array directly on the item (no nested
    // "value" property), so getValue() already returns the matrix.
    return $this->get('preferences')->first()?->getValue() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getQuietHours(): ?array {
    $start = $this->get('quiet_hours_start')->value;
    $end = $this->get('quiet_hours_end')->value;
    if ($start === NULL || $end === NULL) {
      return NULL;
    }
    return [
      'start' => $start,
      'end' => $end,
      'tz' => $this->get('quiet_hours_tz')->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('User'))
      ->setDescription(new TranslatableMarkup('The user these settings belong to (1:1).'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->addConstraint('UniqueField');

    $fields['preferences'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Preferences'))
      ->setDescription(new TranslatableMarkup('The {type:{channel:bool}} preference matrix.'));

    $fields['quiet_hours_start'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Quiet hours start'))
      ->setDescription(new TranslatableMarkup('Local start of the quiet window, HH:MM.'))
      ->setSetting('max_length', 5);

    $fields['quiet_hours_end'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Quiet hours end'))
      ->setDescription(new TranslatableMarkup('Local end of the quiet window, HH:MM.'))
      ->setSetting('max_length', 5);

    $fields['quiet_hours_tz'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Quiet hours timezone'))
      ->setSetting('max_length', 64);

    $fields['language_override'] = BaseFieldDefinition::create('language')
      ->setLabel(new TranslatableMarkup('Language override'))
      ->setDescription(new TranslatableMarkup('Forces a delivery language regardless of the user preferred language.'));

    return $fields;
  }

}
