<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_messenger\Form\MessengerContactForm;
use Drupal\openintranet_messenger\MessengerContactAccessControlHandler;
use Drupal\openintranet_messenger\MessengerContactInterface;
use Drupal\openintranet_messenger\MessengerContactListBuilder;
use Drupal\views\EntityViewsData;

/**
 * Defines the messenger contact entity class.
 */
#[ContentEntityType(
  id: 'messenger_contact',
  label: new TranslatableMarkup('Messenger Contact'),
  label_collection: new TranslatableMarkup('Open Intranet - Messenger - Contacts'),
  label_singular: new TranslatableMarkup('messenger contact'),
  label_plural: new TranslatableMarkup('messenger contacts'),
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => MessengerContactListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => MessengerContactAccessControlHandler::class,
    'form' => [
      'add' => MessengerContactForm::class,
      'edit' => MessengerContactForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/openintranet/messenger/contacts',
    'add-form' => '/admin/openintranet/messenger/contacts/add',
    'canonical' => '/admin/openintranet/messenger/contacts/{messenger_contact}',
    'edit-form' => '/admin/openintranet/messenger/contacts/{messenger_contact}/edit',
    'delete-form' => '/admin/openintranet/messenger/contacts/{messenger_contact}/delete',
    'delete-multiple-form' => '/admin/openintranet/messenger/contacts/delete-multiple',
  ],
  admin_permission: 'administer messenger',
  base_table: 'messenger_contact',
  label_count: [
    'singular' => '@count messenger contact',
    'plural' => '@count messenger contacts',
  ],
  field_ui_base_route: 'entity.messenger_contact.settings',
)]
class MessengerContact extends ContentEntityBase implements MessengerContactInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->get('name')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): self {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): ?string {
    return $this->get('email')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): self {
    $this->set('email', $email);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhone(): ?string {
    return $this->get('phone')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPhone(string $phone): self {
    $this->set('phone', $phone);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredChannel(): string {
    return $this->get('preferred_channel')->value ?? 'email';
  }

  /**
   * {@inheritdoc}
   */
  public function setPreferredChannel(string $channel): self {
    $this->set('preferred_channel', $channel);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return (bool) $this->get('active')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive(bool $active): self {
    $this->set('active', $active);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): self {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The full name of the contact.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t('The email address of the contact.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'email_default',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'email_mailto',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->addConstraint('UniqueField');

    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Phone'))
      ->setDescription(t('The phone number in international format (e.g., +48123456789).'))
      ->setSetting('max_length', 20)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -8,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Taxonomy term field for grouping contacts.
    // The actual vocabulary is configured in module settings.
    $fields['taxonomy'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Taxonomy Term'))
      ->setDescription(t('The taxonomy term this contact belongs to. Configure the vocabulary in Messenger settings.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -7,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['preferred_channel'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Preferred Channel'))
      ->setDescription(t('The preferred notification channel for this contact.'))
      ->setRequired(TRUE)
      ->setDefaultValue('email')
      ->setSetting('allowed_values', [
        'email' => 'Email',
        'sms' => 'SMS',
        'both' => 'Both (Email + SMS)',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('Whether the contact is active and can receive notifications.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -5,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'boolean',
        'weight' => -5,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Optional fields for future channel integrations.
    $fields['slack_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Slack ID'))
      ->setDescription(t('The Slack User ID for this contact.'))
      ->setSetting('max_length', 50)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['teams_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('MS Teams ID'))
      ->setDescription(t('The Microsoft Teams ID for this contact.'))
      ->setSetting('max_length', 100)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['nextcloud_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nextcloud ID'))
      ->setDescription(t('The Nextcloud username for this contact.'))
      ->setSetting('max_length', 100)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 12,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the contact was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the contact was last edited.'));

    return $fields;
  }

}
