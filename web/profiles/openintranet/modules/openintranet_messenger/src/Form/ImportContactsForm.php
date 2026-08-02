<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing contacts from CSV file.
 */
final class ImportContactsForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs an ImportContactsForm.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerFactory->get('openintranet_messenger');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_messenger_import_contacts';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Upload a CSV file to import contacts. The file must have the following columns:') . '</p>',
    ];

    $form['columns_info'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Column'),
        $this->t('Required'),
        $this->t('Description'),
      ],
      '#rows' => [
        ['name', $this->t('Yes'), $this->t('Full name of the contact')],
        ['email', $this->t('Yes'), $this->t('Email address')],
        ['phone', $this->t('No'), $this->t('Phone number (international format, e.g., +48123456789)')],
        ['department', $this->t('No'), $this->t('Taxonomy term name for grouping')],
        ['preferred_channel', $this->t('No'), $this->t('email, sms, or both (default: email)')],
      ],
    ];

    $form['sample_csv'] = [
      '#type' => 'markup',
      '#markup' => '<p><strong>' . $this->t('Sample CSV:') . '</strong></p>
<pre>name,email,phone,department,preferred_channel
Jan Kowalski,jan.kowalski@example.com,+48123456789,Production,sms
Anna Nowak,anna.nowak@example.com,+48987654321,Office,email
Piotr Wiśniewski,piotr.w@example.com,,Field,both</pre>',
    ];

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Upload a CSV file with contacts to import. Allowed extensions: csv, txt'),
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'csv txt'],
      ],
    ];

    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Import Options'),
    ];

    $form['options']['update_existing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update existing contacts'),
      '#description' => $this->t('If checked, contacts with matching email will be updated. If unchecked, duplicates will be skipped.'),
      '#default_value' => FALSE,
    ];

    $form['options']['skip_first_row'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('First row contains headers'),
      '#description' => $this->t('Skip the first row of the CSV file.'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // File validation happens in submitForm via file_save_upload.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $update_existing = (bool) $form_state->getValue('update_existing');
    $skip_first_row = (bool) $form_state->getValue('skip_first_row');

    // Save uploaded file.
    // file_save_upload with delta=0 returns single File entity or FALSE.
    $validators = [
      'FileExtension' => ['extensions' => 'csv txt'],
    ];

    /** @var \Drupal\file\FileInterface|false $file */
    $file = file_save_upload('csv_file', $validators, 'temporary://messenger_import', 0);

    if (!$file) {
      $this->messenger()->addError($this->t('Please upload a valid CSV file.'));
      return;
    }
    $uri = $file->getFileUri();
    $realpath = \Drupal::service('file_system')->realpath($uri);

    if (!$realpath || !file_exists($realpath)) {
      $this->messenger()->addError($this->t('Could not read the uploaded file.'));
      return;
    }

    $this->logger->info('Messenger/Import: Starting CSV import. File: @file', [
      '@file' => $file->getFilename(),
    ]);

    $handle = fopen($realpath, 'r');
    if (!$handle) {
      $this->messenger()->addError($this->t('Could not open the file for reading.'));
      return;
    }

    $stats = [
      'created' => 0,
      'updated' => 0,
      'skipped' => 0,
      'errors' => 0,
    ];

    $row_number = 0;
    $headers = [];

    while (($row = fgetcsv($handle)) !== FALSE) {
      $row_number++;

      // Skip first row if it contains headers.
      if ($row_number === 1 && $skip_first_row) {
        $headers = array_map('strtolower', array_map('trim', $row));
        continue;
      }

      // Map row to associative array.
      if (!empty($headers)) {
        $data = array_combine($headers, $row);
      }
      else {
        // Default column order if no headers.
        $data = [
          'name' => $row[0] ?? '',
          'email' => $row[1] ?? '',
          'phone' => $row[2] ?? '',
          'department' => $row[3] ?? '',
          'preferred_channel' => $row[4] ?? 'email',
        ];
      }

      // Validate required fields.
      if (empty($data['name']) || empty($data['email'])) {
        $this->logger->warning('Messenger/Import: Row @row skipped - missing name or email', [
          '@row' => $row_number,
        ]);
        $stats['errors']++;
        continue;
      }

      // Validate email format.
      if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $this->logger->warning('Messenger/Import: Row @row skipped - invalid email: @email', [
          '@row' => $row_number,
          '@email' => $data['email'],
        ]);
        $stats['errors']++;
        continue;
      }

      // Check for existing contact by email.
      $existing = $this->entityTypeManager->getStorage('messenger_contact')
        ->loadByProperties(['email' => $data['email']]);

      if (!empty($existing)) {
        if ($update_existing) {
          $contact = reset($existing);
          $this->updateContact($contact, $data);
          $stats['updated']++;
          $this->logger->info('Messenger/Import: Row @row - updated contact @name (@email)', [
            '@row' => $row_number,
            '@name' => $data['name'],
            '@email' => $data['email'],
          ]);
        }
        else {
          $stats['skipped']++;
          $this->logger->notice('Messenger/Import: Row @row - skipped duplicate @email', [
            '@row' => $row_number,
            '@email' => $data['email'],
          ]);
        }
        continue;
      }

      // Create new contact.
      try {
        $this->createContact($data);
        $stats['created']++;
        $this->logger->info('Messenger/Import: Row @row - created contact @name (@email)', [
          '@row' => $row_number,
          '@name' => $data['name'],
          '@email' => $data['email'],
        ]);
      }
      catch (\Exception $e) {
        $stats['errors']++;
        $this->logger->error('Messenger/Import: Row @row - error creating contact: @error', [
          '@row' => $row_number,
          '@error' => $e->getMessage(),
        ]);
      }
    }

    fclose($handle);

    // Log summary.
    $this->logger->info('Messenger/Import: Completed. Created: @created, Updated: @updated, Skipped: @skipped, Errors: @errors', [
      '@created' => $stats['created'],
      '@updated' => $stats['updated'],
      '@skipped' => $stats['skipped'],
      '@errors' => $stats['errors'],
    ]);

    // Show messages.
    if ($stats['created'] > 0) {
      $this->messenger()->addStatus($this->t('Created @count new contacts.', ['@count' => $stats['created']]));
    }
    if ($stats['updated'] > 0) {
      $this->messenger()->addStatus($this->t('Updated @count existing contacts.', ['@count' => $stats['updated']]));
    }
    if ($stats['skipped'] > 0) {
      $this->messenger()->addWarning($this->t('Skipped @count duplicate contacts.', ['@count' => $stats['skipped']]));
    }
    if ($stats['errors'] > 0) {
      $this->messenger()->addError($this->t('@count rows had errors.', ['@count' => $stats['errors']]));
    }

    $form_state->setRedirect('entity.messenger_contact.collection');
  }

  /**
   * Creates a new contact from import data.
   */
  protected function createContact(array $data): void {
    $values = [
      'name' => trim($data['name']),
      'email' => trim($data['email']),
      'active' => TRUE,
    ];

    if (!empty($data['phone'])) {
      $values['phone'] = trim($data['phone']);
    }

    if (!empty($data['preferred_channel'])) {
      $channel = strtolower(trim($data['preferred_channel']));
      if (in_array($channel, ['email', 'sms', 'both'])) {
        $values['preferred_channel'] = $channel;
      }
    }

    // Handle taxonomy term (department).
    if (!empty($data['department'])) {
      $term_id = $this->findOrCreateTerm(trim($data['department']));
      if ($term_id) {
        $values['taxonomy'] = $term_id;
      }
    }

    $contact = $this->entityTypeManager->getStorage('messenger_contact')->create($values);
    $contact->save();
  }

  /**
   * Updates an existing contact with import data.
   */
  protected function updateContact($contact, array $data): void {
    $contact->set('name', trim($data['name']));

    if (!empty($data['phone'])) {
      $contact->set('phone', trim($data['phone']));
    }

    if (!empty($data['preferred_channel'])) {
      $channel = strtolower(trim($data['preferred_channel']));
      if (in_array($channel, ['email', 'sms', 'both'])) {
        $contact->set('preferred_channel', $channel);
      }
    }

    if (!empty($data['department'])) {
      $term_id = $this->findOrCreateTerm(trim($data['department']));
      if ($term_id) {
        $contact->set('taxonomy', $term_id);
      }
    }

    $contact->save();
  }

  /**
   * Finds or creates a taxonomy term.
   */
  protected function findOrCreateTerm(string $name): ?int {
    // Get configured vocabulary.
    $config = \Drupal::config('openintranet_messenger.settings');
    $vocabulary_id = $config->get('taxonomy_vocabulary');

    if (empty($vocabulary_id)) {
      return NULL;
    }

    // Try to find existing term.
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties([
        'name' => $name,
        'vid' => $vocabulary_id,
      ]);

    if (!empty($terms)) {
      $term = reset($terms);
      return (int) $term->id();
    }

    // Create new term.
    try {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
        'name' => $name,
        'vid' => $vocabulary_id,
      ]);
      $term->save();
      return (int) $term->id();
    }
    catch (\Exception $e) {
      $this->logger->warning('Messenger/Import: Could not create taxonomy term @name: @error', [
        '@name' => $name,
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
