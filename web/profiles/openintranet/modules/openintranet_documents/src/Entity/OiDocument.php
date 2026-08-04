<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Form\RevisionDeleteForm;
use Drupal\Core\Entity\Form\RevisionRevertForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\openintranet_documents\Form\OiDocumentForm;
use Drupal\openintranet_documents\Form\OiDocumentModalForm;
use Drupal\openintranet_documents\OiDocumentAccessControlHandler;
use Drupal\openintranet_documents\OiDocumentInterface;
use Drupal\openintranet_documents\OiDocumentListBuilder;
use Drupal\openintranet_documents\OiFolderInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the OI Document entity.
 */
#[ContentEntityType(
  id: 'oi_document',
  label: new TranslatableMarkup('Document'),
  label_collection: new TranslatableMarkup('Documents'),
  label_singular: new TranslatableMarkup('document'),
  label_plural: new TranslatableMarkup('documents'),
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'label' => 'title',
    'owner' => 'uid',
    'published' => 'status',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => OiDocumentListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => OiDocumentAccessControlHandler::class,
    'form' => [
      'add' => OiDocumentForm::class,
      'add_modal' => OiDocumentModalForm::class,
      'edit' => OiDocumentForm::class,
      'edit_modal' => OiDocumentModalForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
      'revision-delete' => RevisionDeleteForm::class,
      'revision-revert' => RevisionRevertForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
      'revision' => RevisionHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/openintranet/oi-document',
    'add-form' => '/documents/add',
    'canonical' => '/documents/document/{oi_document}',
    'edit-form' => '/documents/document/{oi_document}/edit',
    'delete-form' => '/documents/document/{oi_document}/delete',
    'delete-multiple-form' => '/admin/openintranet/oi-document/delete-multiple',
    'revision' => '/documents/document/{oi_document}/revision/{oi_document_revision}/view',
    'revision-delete-form' => '/documents/document/{oi_document}/revision/{oi_document_revision}/delete',
    'revision-revert-form' => '/documents/document/{oi_document}/revision/{oi_document_revision}/revert',
    'version-history' => '/documents/document/{oi_document}/revisions',
  ],
  admin_permission: 'administer oi_document',
  base_table: 'oi_document',
  revision_table: 'oi_document_revision',
  show_revision_ui: TRUE,
  label_count: [
    'singular' => '@count document',
    'plural' => '@count documents',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
)]
final class OiDocument extends EditorialContentEntityBase implements OiDocumentInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): self {
    $this->set('title', $title);
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
  public function getFolder(): ?OiFolderInterface {
    $folder = $this->get('folder')->entity;
    return $folder instanceof OiFolderInterface ? $folder : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setFolder(?OiFolderInterface $folder): self {
    $this->set('folder', $folder);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFolderId(): ?int {
    $value = $this->get('folder')->target_id;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(): ?FileInterface {
    $file = $this->get('file')->entity;
    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setFile(?FileInterface $file): self {
    $this->set('file', $file);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceType(): string {
    return $this->get('source_type')->value ?? 'local_file';
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceType(string $type): self {
    $this->set('source_type', $type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceUrl(): ?string {
    return $this->get('source_url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceUrl(?string $url): self {
    $this->set('source_url', $url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(t('Title'))
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
      ->setRevisionable(TRUE)
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
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['folder'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(t('Folder'))
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
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Source type - determines which plugin handles this document.
    $fields['source_type'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(t('Source type'))
      ->setDescription(t('The document source plugin ID'))
      ->setDefaultValue('local_file')
      ->setRequired(TRUE)
      ->setSetting('max_length', 64)
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayConfigurable('form', FALSE);

    // Source URL - for external sources (Google Drive, OneDrive, etc.).
    $fields['source_url'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(t('Source URL'))
      ->setDescription(t('External document URL'))
      ->setSetting('max_length', 2048)
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayConfigurable('form', FALSE);

    // File field - NOW OPTIONAL (only for local_file source).
    $fields['file'] = BaseFieldDefinition::create('file')
      ->setRevisionable(TRUE)
      ->setLabel(t('File'))
      ->setDescription(t('The document file (for local uploads)'))
      ->setRequired(FALSE)
      ->setSettings([
        'file_directory' => 'documents/[date:custom:Y]/[date:custom:m]',
        'file_extensions' => 'pdf doc docx xls xlsx ppt pptx txt rtf odt ods odp jpg jpeg png gif',
        'max_filesize' => '50 MB',
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'file_default',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Published'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Published')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => ['display_label' => FALSE],
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 10,
        'settings' => ['format' => 'enabled-disabled'],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
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
      ->setDescription(t('The time when the document was created.'))
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
      ->setDescription(t('The time when the document was last edited.'));

    return $fields;
  }

}
