<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Entity;

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
use Drupal\openintranet_documents\Form\OiFolderForm;
use Drupal\openintranet_documents\Form\OiFolderModalForm;
use Drupal\openintranet_documents\OiFolderAccessControlHandler;
use Drupal\openintranet_documents\OiFolderInterface;
use Drupal\openintranet_documents\OiFolderListBuilder;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the OI Folder entity.
 */
#[ContentEntityType(
  id: 'oi_folder',
  label: new TranslatableMarkup('Folder'),
  label_collection: new TranslatableMarkup('Folders'),
  label_singular: new TranslatableMarkup('folder'),
  label_plural: new TranslatableMarkup('folders'),
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'owner' => 'uid',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => OiFolderListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => OiFolderAccessControlHandler::class,
    'form' => [
      'add' => OiFolderForm::class,
      'add_modal' => OiFolderModalForm::class,
      'edit' => OiFolderForm::class,
      'edit_modal' => OiFolderModalForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/openintranet/oi-folder',
    'add-form' => '/documents/folder/add',
    'canonical' => '/admin/openintranet/oi-folder/{oi_folder}',
    'edit-form' => '/documents/folder/{oi_folder}/edit',
    'delete-form' => '/documents/folder/{oi_folder}/delete',
    'delete-multiple-form' => '/admin/openintranet/oi-folder/delete-multiple',
  ],
  admin_permission: 'administer oi_folder',
  base_table: 'oi_folder',
  label_count: [
    'singular' => '@count folder',
    'plural' => '@count folders',
  ],
)]
final class OiFolder extends ContentEntityBase implements OiFolderInterface {

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
  public function getParent(): ?OiFolderInterface {
    $parent = $this->get('parent')->entity;
    return $parent instanceof OiFolderInterface ? $parent : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setParent(?OiFolderInterface $parent): self {
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
        throw new \InvalidArgumentException('Folder cannot be its own parent.');
      }

      // Check if new parent is descendant of this folder.
      $parent = $storage->load($parentId);
      $maxDepth = 50;
      while ($parent !== NULL && $maxDepth-- > 0) {
        if ((int) $parent->id() === (int) $this->id()) {
          throw new \InvalidArgumentException('Cannot move folder into its own subfolder.');
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
      ->setLabel(t('Parent folder'))
      ->setSetting('target_type', 'oi_folder')
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
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
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
      ->setDescription(t('The time when the folder was created.'))
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
      ->setDescription(t('The time when the folder was last edited.'));

    return $fields;
  }

}
