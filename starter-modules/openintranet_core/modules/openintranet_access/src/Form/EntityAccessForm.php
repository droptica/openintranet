<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\openintranet_access\Service\OiAccessCheckerInterface;
use Drupal\openintranet_access\Service\OiAccessManagerInterface;
use Drupal\openintranet_access\Service\OiGroupManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for setting entity access restrictions.
 */
final class EntityAccessForm extends FormBase {

  /**
   * The entity being configured.
   */
  protected ?EntityInterface $targetEntity = NULL;

  /**
   * The route match service.
   */
  protected RouteMatchInterface $currentRouteMatch;

  /**
   * Constructs the form.
   */
  public function __construct(
    protected readonly OiAccessCheckerInterface $accessChecker,
    protected readonly OiAccessManagerInterface $accessManager,
    protected readonly OiGroupManagerInterface $groupManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    RouteMatchInterface $route_match,
  ) {
    $this->currentRouteMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('openintranet_access.checker'),
      $container->get('openintranet_access.access_manager'),
      $container->get('openintranet_access.group_manager'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_access_entity_access_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Detect entity from route parameters.
    $this->targetEntity = $this->getEntityFromRoute();

    if (!$this->targetEntity) {
      $this->messenger()->addError($this->t('Entity not found.'));
      return $form;
    }

    $form_state->set('target_entity', $this->targetEntity);

    $form['#title'] = $this->t('Access restrictions: @label', [
      '@label' => $this->targetEntity->label(),
    ]);

    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Configure which groups and users can access this content. Leave empty to use default Drupal permissions.'),
      '#attributes' => ['class' => ['description']],
    ];

    // Groups with access.
    $form['groups'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Groups with access'),
      '#description' => $this->t('Users in selected groups (and their subgroups) will have access.'),
    ];

    $current_groups = $this->accessChecker->getAccessGroups($this->targetEntity);
    $current_group_ids = array_map(fn($g) => (string) $g->id(), $current_groups);

    // Build hierarchical group options.
    $group_options = $this->buildGroupOptions();

    if (empty($group_options)) {
      $form['groups']['no_groups'] = [
        '#markup' => '<p>' . $this->t('No groups available. <a href="@url">Create a group</a> first.', [
          '@url' => Url::fromRoute('entity.oi_group.add_form')->toString(),
        ]) . '</p>',
      ];
    }
    else {
      $form['groups']['access_groups'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Select groups'),
        '#options' => $group_options,
        '#default_value' => $current_group_ids,
        '#description' => $this->t('Subgroups automatically have access when parent is selected.'),
      ];
    }

    // Individual users.
    $form['users'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Individual users with access'),
      '#description' => $this->t('These users will have access regardless of group membership.'),
    ];

    $current_user_ids = $this->accessChecker->getAccessUserIds($this->targetEntity);
    $current_users = [];
    if (!empty($current_user_ids)) {
      $current_users = $this->entityTypeManager->getStorage('user')->loadMultiple($current_user_ids);
    }

    $form['users']['access_users'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Users'),
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#default_value' => array_values($current_users),
      '#selection_settings' => [
        'include_anonymous' => FALSE,
      ],
      '#description' => $this->t('Start typing to search for users.'),
    ];

    // Access summary.
    $config = $this->config('openintranet_access.settings');
    if ($config->get('ui.show_access_summary')) {
      $form['summary'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Current access summary'),
      ];

      $has_restrictions = $this->accessChecker->hasRestrictions($this->targetEntity);

      if (!$has_restrictions) {
        $form['summary']['info'] = [
          '#markup' => '<p>' . $this->t('No restrictions set. All users with appropriate permissions can access this content.') . '</p>',
        ];
      }
      else {
        $total_users = count($this->accessChecker->getAllAllowedUserIds($this->targetEntity));
        $group_count = count($current_groups);
        $direct_user_count = count($current_user_ids);

        $items = [];
        if ($group_count > 0) {
          $items[] = $this->t('@count group(s)', ['@count' => $group_count]);
        }
        if ($direct_user_count > 0) {
          $items[] = $this->t('@count individual user(s)', ['@count' => $direct_user_count]);
        }

        $form['summary']['info'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Access granted to:'),
          '#items' => $items,
        ];

        $form['summary']['total'] = [
          '#markup' => '<p>' . $this->t('Approximately @count unique users have access.', ['@count' => $total_users]) . '</p>',
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save access settings'),
      '#button_type' => 'primary',
    ];

    $form['actions']['clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear all restrictions'),
      '#submit' => ['::clearRestrictions'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->get('target_entity');

    // Process groups (from checkboxes).
    $groups_value = $form_state->getValue('access_groups') ?? [];
    $group_ids = [];
    foreach ($groups_value as $id => $selected) {
      // Checkboxes return the ID if selected, 0 if not.
      if ($selected) {
        $group_ids[] = $id;
      }
    }
    $groups = $this->entityTypeManager->getStorage('oi_group')->loadMultiple($group_ids);
    $this->accessManager->setAccessGroups($entity, $groups);

    // Process users.
    $users_value = $form_state->getValue('access_users') ?? [];
    $user_ids = [];
    foreach ($users_value as $item) {
      if (isset($item['target_id'])) {
        $user_ids[] = (int) $item['target_id'];
      }
    }
    $this->accessManager->setAccessUsers($entity, $user_ids);

    $this->messenger()->addStatus($this->t('Access settings have been saved.'));
  }

  /**
   * Submit handler for clearing all restrictions.
   */
  public function clearRestrictions(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->get('target_entity');
    $this->accessManager->clearAccessRestrictions($entity);
    $this->messenger()->addStatus($this->t('All access restrictions have been removed.'));
  }

  /**
   * Gets the entity from the current route.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity or NULL if not found.
   */
  protected function getEntityFromRoute(): ?EntityInterface {
    // Try common entity parameter names.
    $parameters = ['node', 'oi_document', 'oi_folder'];

    foreach ($parameters as $param) {
      $entity = $this->currentRouteMatch->getParameter($param);
      if ($entity instanceof EntityInterface) {
        return $entity;
      }
    }

    return NULL;
  }

  /**
   * Builds hierarchical group options for checkboxes.
   *
   * @return array
   *   Array of group ID => label with hierarchy indicated by dashes.
   */
  protected function buildGroupOptions(): array {
    $options = [];

    // Load all groups.
    $storage = $this->entityTypeManager->getStorage('oi_group');
    $groups = $storage->loadMultiple();

    if (empty($groups)) {
      return $options;
    }

    // Build tree structure.
    $tree = [];
    $children = [];

    foreach ($groups as $group) {
      $parent_id = $group->get('parent')->target_id;
      if ($parent_id) {
        $children[$parent_id][] = $group;
      }
      else {
        $tree[] = $group;
      }
    }

    // Sort root groups by name.
    usort($tree, fn($a, $b) => strcmp($a->getName(), $b->getName()));

    // Recursively build options.
    $this->addGroupOptionsRecursive($tree, $children, $options, 0);

    return $options;
  }

  /**
   * Recursively adds group options with hierarchy indication.
   *
   * @param array $groups
   *   Groups at current level.
   * @param array $children
   *   Array of parent_id => child groups.
   * @param array &$options
   *   Options array to build.
   * @param int $depth
   *   Current depth for indentation.
   */
  protected function addGroupOptionsRecursive(array $groups, array $children, array &$options, int $depth): void {
    foreach ($groups as $group) {
      $indent = str_repeat('— ', $depth);
      $options[(string) $group->id()] = $indent . $group->getName();

      // Add children if any.
      $group_id = $group->id();
      if (isset($children[$group_id])) {
        $child_groups = $children[$group_id];
        usort($child_groups, fn($a, $b) => strcmp($a->getName(), $b->getName()));
        $this->addGroupOptionsRecursive($child_groups, $children, $options, $depth + 1);
      }
    }
  }

}
