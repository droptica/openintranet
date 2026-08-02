<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\openintranet_access\Entity\OiGroupInterface;
use Drupal\openintranet_access\Service\OiGroupManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for managing group members.
 */
final class OiGroupMembersForm extends FormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    protected readonly OiGroupManagerInterface $groupManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('openintranet_access.group_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_access_group_members_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?OiGroupInterface $oi_group = NULL): array {
    if ($oi_group === NULL) {
      $this->messenger()->addError($this->t('Group not found.'));
      return $form;
    }

    $form_state->set('oi_group', $oi_group);

    $form['#title'] = $this->t('Manage members: @group', ['@group' => $oi_group->getName()]);

    // Current members.
    $members = $this->groupManager->getGroupMembers($oi_group, FALSE);

    $form['current_members'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Current members (@count)', ['@count' => count($members)]),
    ];

    if (empty($members)) {
      $form['current_members']['empty'] = [
        '#markup' => '<p>' . $this->t('No members in this group.') . '</p>',
      ];
    }
    else {
      $form['current_members']['members'] = [
        '#type' => 'tableselect',
        '#header' => [
          'name' => $this->t('Name'),
          'email' => $this->t('Email'),
          'status' => $this->t('Status'),
        ],
        '#options' => [],
        '#empty' => $this->t('No members.'),
      ];

      foreach ($members as $member) {
        $form['current_members']['members']['#options'][$member->id()] = [
          'name' => $member->getDisplayName(),
          'email' => $member->getEmail(),
          'status' => $member->isActive() ? $this->t('Active') : $this->t('Blocked'),
        ];
      }

      $form['current_members']['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove selected'),
        '#submit' => ['::removeMembers'],
      ];
    }

    // Add new members.
    $form['add_member'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add members'),
    ];

    $form['add_member']['users'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Users'),
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#selection_settings' => [
        'include_anonymous' => FALSE,
      ],
      '#description' => $this->t('Start typing to search for users. You can add multiple users separated by comma.'),
    ];

    $form['add_member']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add members'),
      '#submit' => ['::addMembers'],
    ];

    // Back link.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to group'),
      '#url' => Url::fromRoute('entity.oi_group.canonical', ['oi_group' => $oi_group->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Main form doesn't have a submit - handled by sub-submit handlers.
  }

  /**
   * Submit handler for adding members.
   */
  public function addMembers(array &$form, FormStateInterface $form_state): void {
    $group = $form_state->get('oi_group');
    $usersValue = $form_state->getValue('users');

    if (empty($usersValue)) {
      $this->messenger()->addError($this->t('Please select at least one user.'));
      return;
    }

    // Handle both single value and array from #tags => TRUE.
    $userIds = [];
    if (is_array($usersValue)) {
      foreach ($usersValue as $item) {
        if (is_array($item) && isset($item['target_id'])) {
          $userIds[] = $item['target_id'];
        }
        elseif (is_numeric($item)) {
          $userIds[] = $item;
        }
      }
    }
    else {
      $userIds[] = $usersValue;
    }

    if (empty($userIds)) {
      $this->messenger()->addError($this->t('Please select at least one user.'));
      return;
    }

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($userIds);
    $added = 0;
    $skipped = 0;

    foreach ($users as $user) {
      if ($this->groupManager->isMember($group, $user)) {
        $skipped++;
        continue;
      }
      $this->groupManager->addMember($group, $user);
      $added++;
    }

    if ($added > 0) {
      $this->messenger()->addStatus($this->t('@count user(s) added to the group.', [
        '@count' => $added,
      ]));
    }

    if ($skipped > 0) {
      $this->messenger()->addWarning($this->t('@count user(s) were already members.', [
        '@count' => $skipped,
      ]));
    }
  }

  /**
   * Submit handler for removing members.
   */
  public function removeMembers(array &$form, FormStateInterface $form_state): void {
    $group = $form_state->get('oi_group');
    $selected = array_filter($form_state->getValue('members') ?? []);

    if (empty($selected)) {
      $this->messenger()->addWarning($this->t('No members selected.'));
      return;
    }

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($selected);
    $removed = 0;

    foreach ($users as $user) {
      $this->groupManager->removeMember($group, $user);
      $removed++;
    }

    $this->messenger()->addStatus($this->t('Removed @count member(s) from the group.', [
      '@count' => $removed,
    ]));
  }

}
