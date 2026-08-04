<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_access\Form\OiGroupForm;
use Drupal\openintranet_access\OiGroupAccessControlHandler;
use Drupal\openintranet_access\OiGroupListBuilder;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the OI Group entity.
 */
#[ContentEntityType(
  id: 'oi_group',
  label: new TranslatableMarkup('Group'),
  label_collection: new TranslatableMarkup('Open Intranet - Groups'),
  label_singular: new TranslatableMarkup('group'),
  label_plural: new TranslatableMarkup('groups'),
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'owner' => 'uid',
    'uuid' => 'uuid',
  ],
  handlers: [
    'view_builder' => \Drupal\openintranet_access\OiGroupViewBuilder::class,
    'list_builder' => OiGroupListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => OiGroupAccessControlHandler::class,
    'form' => [
      'add' => OiGroupForm::class,
      'edit' => OiGroupForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/openintranet/oi-groups',
    'add-form' => '/admin/openintranet/oi-groups/add',
    'canonical' => '/admin/openintranet/oi-groups/{oi_group}',
    'edit-form' => '/admin/openintranet/oi-groups/{oi_group}/edit',
    'delete-form' => '/admin/openintranet/oi-groups/{oi_group}/delete',
    'delete-multiple-form' => '/admin/openintranet/oi-groups/delete-multiple',
  ],
  admin_permission: 'administer oi_group',
  base_table: 'oi_group',
  label_count: [
    'singular' => '@count group',
    'plural' => '@count groups',
  ],
)]
final class OiGroup extends ContentEntityBase implements OiGroupInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

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
  public function getDescription(): ?string {
    return $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getParent(): ?OiGroupInterface {
    $parent = $this->get('parent')->entity;
    return $parent instanceof OiGroupInterface ? $parent : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setParent(?OiGroupInterface $parent): self {
    $this->set('parent', $parent);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId(): ?int {
    $value = $this->get('parent')->target_id;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive(bool $active): self {
    $this->set('status', $active);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath(): array {
    $path = [$this->getName()];
    $parent = $this->getParent();
    $maxDepth = 50;

    while ($parent !== NULL && $maxDepth-- > 0) {
      array_unshift($path, $parent->getName());
      $parent = $parent->getParent();
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getDepth(): int {
    $depth = 0;
    $parent = $this->getParent();
    $maxDepth = 50;

    while ($parent !== NULL && $maxDepth-- > 0) {
      $depth++;
      $parent = $parent->getParent();
    }

    return $depth;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }

    // Prevent circular reference.
    $parentId = $this->getParentId();
    if ($parentId !== NULL && $this->id() !== NULL) {
      if ($parentId === (int) $this->id()) {
        throw new \InvalidArgumentException('Group cannot be its own parent.');
      }

      // Check if new parent is descendant of this group.
      $parent = $storage->load($parentId);
      $maxDepth = 50;
      while ($parent !== NULL && $maxDepth-- > 0) {
        if ((int) $parent->id() === (int) $this->id()) {
          throw new \InvalidArgumentException('Cannot set a child group as parent (circular reference).');
        }
        $parent = $parent->getParent();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the group.'))
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

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setDescription(t('A description of the group.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -5,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['parent'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent group'))
      ->setDescription(t('The parent group for hierarchy.'))
      ->setSetting('target_type', 'oi_group')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
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
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('Whether the group is active.'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Active')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => ['display_label' => FALSE],
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 20,
        'settings' => ['format' => 'enabled-disabled'],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user who created the group.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the group was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the group was last edited.'));

    return $fields;
  }

}
