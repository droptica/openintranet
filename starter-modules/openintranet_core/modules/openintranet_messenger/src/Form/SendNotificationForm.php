<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_messenger\Channel\ChannelPluginManager;
use Drupal\openintranet_messenger\Recipient\RecipientResolver;
use Drupal\openintranet_messenger\Service\NotificationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for sending notifications.
 */
final class SendNotificationForm extends FormBase {

  /**
   * Constructs a SendNotificationForm object.
   *
   * @param \Drupal\openintranet_messenger\Service\NotificationServiceInterface $notificationService
   *   The notification service.
   * @param \Drupal\openintranet_messenger\Recipient\RecipientResolver $recipientResolver
   *   The recipient resolver.
   * @param \Drupal\openintranet_messenger\Channel\ChannelPluginManager $channelManager
   *   The channel plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly NotificationServiceInterface $notificationService,
    private readonly RecipientResolver $recipientResolver,
    private readonly ChannelPluginManager $channelManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('openintranet_messenger.notification_service'),
      $container->get('openintranet_messenger.recipient_resolver'),
      $container->get('plugin.manager.notification_channel'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'send_notification_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'core/drupal.ajax';

    // Get configured vocabulary for grouping.
    $vocabulary_id = $this->recipientResolver->getConfiguredVocabulary();
    $vocabulary_label = $this->getVocabularyLabel($vocabulary_id);

    // Recipients section.
    $form['recipients'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Recipients'),
    ];

    // External contacts.
    $form['recipients']['contacts'] = [
      '#type' => 'details',
      '#title' => $this->t('External Contacts'),
      '#open' => TRUE,
    ];

    // Build contact selection options.
    $contact_options = [
      'none' => $this->t('None'),
      'all' => $this->t('All active contacts'),
    ];

    // Only add "by taxonomy term" option if vocabulary is configured.
    if ($vocabulary_id) {
      $contact_options['taxonomy_term'] = $this->t('By @vocabulary (taxonomy)', ['@vocabulary' => $vocabulary_label]);
    }

    $contact_options['selected'] = $this->t('Selected contacts');

    $form['recipients']['contacts']['contact_selection'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select contacts'),
      '#options' => $contact_options,
      '#default_value' => 'none',
    ];

    // Taxonomy term selection (only if vocabulary is configured).
    if ($vocabulary_id) {
      $taxonomy_options = $this->getTaxonomyOptionsTree($vocabulary_id);
      $form['recipients']['contacts']['contact_taxonomy_term'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Select @vocabulary', ['@vocabulary' => strtolower($vocabulary_label)]),
        '#options' => $taxonomy_options,
        '#states' => [
          'visible' => [
            ':input[name="contact_selection"]' => ['value' => 'taxonomy_term'],
          ],
        ],
      ];
    }

    // Individual contacts selection.
    $form['recipients']['contacts']['selected_contacts'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select contacts'),
      '#target_type' => 'messenger_contact',
      '#tags' => TRUE,
      '#selection_settings' => [
        'match_operator' => 'CONTAINS',
      ],
      '#states' => [
        'visible' => [
          ':input[name="contact_selection"]' => ['value' => 'selected'],
        ],
      ],
    ];

    // Drupal users.
    $form['recipients']['users'] = [
      '#type' => 'details',
      '#title' => $this->t('Drupal Users'),
      '#open' => FALSE,
    ];

    $form['recipients']['users']['user_selection'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select users'),
      '#options' => [
        'none' => $this->t('None'),
        'role' => $this->t('By role'),
        'selected' => $this->t('Selected users'),
      ],
      '#default_value' => 'none',
    ];

    // Role selection.
    $roles = $this->getRoleOptions();
    $form['recipients']['users']['user_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $roles,
      '#empty_option' => $this->t('- Select role -'),
      '#states' => [
        'visible' => [
          ':input[name="user_selection"]' => ['value' => 'role'],
        ],
        'required' => [
          ':input[name="user_selection"]' => ['value' => 'role'],
        ],
      ],
    ];

    // Individual users selection.
    $form['recipients']['users']['selected_users'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select users'),
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'match_operator' => 'CONTAINS',
      ],
      '#states' => [
        'visible' => [
          ':input[name="user_selection"]' => ['value' => 'selected'],
        ],
      ],
    ];

    // Channel selection.
    $form['channel_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Channel'),
    ];

    $channel_options = $this->channelManager->getChannelOptions(TRUE);
    $channel_options = ['preference' => $this->t("Recipient's preference")] + $channel_options;

    $form['channel_wrapper']['channel'] = [
      '#type' => 'radios',
      '#title' => $this->t('Notification channel'),
      '#options' => $channel_options,
      '#default_value' => 'preference',
      '#description' => $this->t('Choose how to send the notification.'),
    ];

    // Message section.
    $form['message_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Message'),
    ];

    $form['message_wrapper']['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['message_wrapper']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#rows' => 5,
      '#description' => $this->t('The notification message content. For SMS, this will be truncated to the configured maximum length.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Notification'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validate that at least one recipient group is selected.
    $contact_selection = $form_state->getValue('contact_selection');
    $user_selection = $form_state->getValue('user_selection');

    if ($contact_selection === 'none' && $user_selection === 'none') {
      $form_state->setErrorByName('contact_selection', $this->t('Please select at least one recipient.'));
    }

    // Validate taxonomy term selection.
    if ($contact_selection === 'taxonomy_term') {
      $taxonomy_values = array_filter($form_state->getValue('contact_taxonomy_term') ?? []);
      if (empty($taxonomy_values)) {
        $form_state->setErrorByName('contact_taxonomy_term', $this->t('Please select at least one taxonomy term.'));
      }
    }

    // Validate role selection.
    if ($user_selection === 'role' && empty($form_state->getValue('user_role'))) {
      $form_state->setErrorByName('user_role', $this->t('Please select a role.'));
    }

    // Validate selected contacts.
    if ($contact_selection === 'selected' && empty($form_state->getValue('selected_contacts'))) {
      $form_state->setErrorByName('selected_contacts', $this->t('Please select at least one contact.'));
    }

    // Validate selected users.
    if ($user_selection === 'selected' && empty($form_state->getValue('selected_users'))) {
      $form_state->setErrorByName('selected_users', $this->t('Please select at least one user.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $recipients = $this->collectRecipients($form_state);

    if (empty($recipients)) {
      $this->messenger()->addWarning($this->t('No valid recipients found.'));
      return;
    }

    $channel = $form_state->getValue('channel');
    if ($channel === 'preference') {
      $channel = NULL;
    }

    $subject = $form_state->getValue('subject');
    $message = $form_state->getValue('message');

    // Use queue for sending to avoid blocking the request.
    $queued_count = $this->notificationService->queueBulk($recipients, $subject, $message, $channel);

    if ($queued_count > 0) {
      $this->messenger()->addStatus($this->t('@count notification(s) queued for sending. They will be processed in the background during cron runs.', [
        '@count' => $queued_count,
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('No notifications were queued. Please check the logs for details.'));
    }

    $form_state->setRedirect('openintranet_messenger.log');
  }

  /**
   * Collects recipients based on form selections.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface[]
   *   Array of recipients.
   */
  private function collectRecipients(FormStateInterface $form_state): array {
    $recipients = [];

    // Collect contacts.
    $contact_selection = $form_state->getValue('contact_selection');
    switch ($contact_selection) {
      case 'all':
        $recipients = array_merge($recipients, $this->recipientResolver->resolveAllActiveContacts());
        break;

      case 'taxonomy_term':
        $term_ids = array_filter($form_state->getValue('contact_taxonomy_term') ?? []);
        foreach ($term_ids as $term_id) {
          $recipients = array_merge($recipients, $this->recipientResolver->resolveContactsByTaxonomy((int) $term_id));
        }
        // Remove duplicates (contact might be in multiple terms).
        $recipients = $this->deduplicateRecipients($recipients);
        break;

      case 'selected':
        $selected = $form_state->getValue('selected_contacts');
        if (!empty($selected)) {
          $ids = array_column($selected, 'target_id');
          $recipients = array_merge($recipients, $this->recipientResolver->resolveContactsByIds($ids));
        }
        break;
    }

    // Collect users.
    $user_selection = $form_state->getValue('user_selection');
    switch ($user_selection) {
      case 'role':
        $role = $form_state->getValue('user_role');
        $recipients = array_merge($recipients, $this->recipientResolver->resolveUsersByRole($role));
        break;

      case 'selected':
        $selected = $form_state->getValue('selected_users');
        if (!empty($selected)) {
          $ids = array_column($selected, 'target_id');
          $recipients = array_merge($recipients, $this->recipientResolver->resolveUsersByIds($ids));
        }
        break;
    }

    return $recipients;
  }

  /**
   * Gets taxonomy term options as hierarchical tree.
   *
   * @param string $vocabulary_id
   *   The vocabulary ID.
   *
   * @return array
   *   Term options keyed by term ID with indentation for hierarchy.
   */
  private function getTaxonomyOptionsTree(string $vocabulary_id): array {
    $options = [];

    try {
      /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $tree = $term_storage->loadTree($vocabulary_id, 0, NULL, TRUE);

      foreach ($tree as $term) {
        // Add indentation based on depth.
        $prefix = str_repeat('— ', $term->depth);
        $options[$term->id()] = $prefix . $term->label();
      }
    }
    catch (\Exception $e) {
      // Vocabulary might not exist yet.
    }

    return $options;
  }

  /**
   * Removes duplicate recipients.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface[] $recipients
   *   Array of recipients.
   *
   * @return \Drupal\openintranet_messenger\Recipient\RecipientInterface[]
   *   Deduplicated array of recipients.
   */
  private function deduplicateRecipients(array $recipients): array {
    $unique = [];
    $seen = [];

    foreach ($recipients as $recipient) {
      $source_info = $recipient->getSourceInfo();
      $key = $source_info['type'] . ':' . $source_info['id'];

      if (!isset($seen[$key])) {
        $seen[$key] = TRUE;
        $unique[] = $recipient;
      }
    }

    return $unique;
  }

  /**
   * Gets the label for a vocabulary.
   *
   * @param string|null $vocabulary_id
   *   The vocabulary ID.
   *
   * @return string
   *   The vocabulary label or default "Group".
   */
  private function getVocabularyLabel(?string $vocabulary_id): string {
    if (!$vocabulary_id) {
      return (string) $this->t('Group');
    }

    try {
      $vocabulary = $this->entityTypeManager
        ->getStorage('taxonomy_vocabulary')
        ->load($vocabulary_id);

      if ($vocabulary) {
        return $vocabulary->label();
      }
    }
    catch (\Exception $e) {
      // Vocabulary might not exist.
    }

    return (string) $this->t('Group');
  }

  /**
   * Gets role options.
   *
   * @return array
   *   Role options.
   */
  private function getRoleOptions(): array {
    $options = [];

    $roles = $this->entityTypeManager
      ->getStorage('user_role')
      ->loadMultiple();

    foreach ($roles as $role) {
      if ($role->id() !== 'anonymous') {
        $options[$role->id()] = $role->label();
      }
    }

    return $options;
  }

}
