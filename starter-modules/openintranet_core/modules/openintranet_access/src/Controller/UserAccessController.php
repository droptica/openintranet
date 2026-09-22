<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\openintranet_access\Service\OiGroupManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying user access information.
 */
final class UserAccessController extends ControllerBase {

  /**
   * Constructs a UserAccessController object.
   */
  public function __construct(
    protected readonly OiGroupManagerInterface $groupManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('openintranet_access.group_manager'),
    );
  }

  /**
   * Displays user access information.
   */
  public function userAccess(UserInterface $user): array {
    $build = [
      '#cache' => [
        'tags' => ['user:' . $user->id(), 'oi_group_list', 'oi_group_membership:' . $user->id()],
        'contexts' => ['user.permissions'],
      ],
    ];

    // Section 1: Groups.
    $build['groups'] = [
      '#type' => 'details',
      '#title' => $this->t('Group memberships'),
      '#open' => TRUE,
      '#weight' => 0,
    ];

    $groups = $this->groupManager->getUserGroups($user);

    if (empty($groups)) {
      $build['groups']['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('@user is not a member of any group.', [
          '@user' => $user->getDisplayName(),
        ]),
        '#attributes' => ['class' => ['messages', 'messages--status']],
      ];
    }
    else {
      $rows = [];
      foreach ($groups as $group) {
        $path = $group->getPath();
        $rows[] = [
          Link::createFromRoute($group->getName(), 'entity.oi_group.canonical', ['oi_group' => $group->id()]),
          count($path) > 1 ? implode(' → ', $path) : '-',
          $group->isActive() ? $this->t('Active') : $this->t('Inactive'),
        ];
      }

      $build['groups']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Group'),
          $this->t('Hierarchy'),
          $this->t('Status'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No groups.'),
      ];
    }

    // Section 2: Direct entity access.
    $build['entities'] = [
      '#type' => 'details',
      '#title' => $this->t('Direct entity access'),
      '#open' => TRUE,
      '#weight' => 10,
    ];

    // Query oi_access_record for user grants.
    $database = \Drupal::database();
    $records = $database->select('oi_access_record', 'oar')
      ->fields('oar', ['entity_type', 'entity_id'])
      ->condition('grant_type', 'user')
      ->condition('target_id', (int) $user->id())
      ->execute()
      ->fetchAll();

    if (empty($records)) {
      $build['entities']['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('@user does not have direct access to any entities.', [
          '@user' => $user->getDisplayName(),
        ]),
        '#attributes' => ['class' => ['messages', 'messages--status']],
      ];
    }
    else {
      // Group by entity type.
      $grouped = [];
      foreach ($records as $record) {
        $grouped[$record->entity_type][] = $record->entity_id;
      }

      foreach ($grouped as $entityType => $entityIds) {
        $storage = $this->entityTypeManager()->getStorage($entityType);
        $entities = $storage->loadMultiple($entityIds);

        if (empty($entities)) {
          continue;
        }

        $entityTypeDefinition = $this->entityTypeManager()->getDefinition($entityType);
        $label = $entityTypeDefinition->getPluralLabel() ?: $entityType;

        $rows = [];
        foreach ($entities as $entity) {
          $linkRoute = 'entity.' . $entityType . '.canonical';
          try {
            $link = Link::createFromRoute(
              $entity->label() ?: $this->t('(no title)'),
              $linkRoute,
              [$entityType => $entity->id()]
            );
            $rows[] = [
              $link,
              $entity->id(),
            ];
          }
          catch (\Exception $e) {
            $rows[] = [
              $entity->label() ?: $this->t('(no title)'),
              $entity->id(),
            ];
          }
        }

        $build['entities'][$entityType] = [
          '#type' => 'details',
          '#title' => $this->t('@type (@count)', [
            '@type' => $label,
            '@count' => count($rows),
          ]),
          '#open' => TRUE,
        ];

        $build['entities'][$entityType]['table'] = [
          '#type' => 'table',
          '#header' => [
            $this->t('Title'),
            $this->t('ID'),
          ],
          '#rows' => $rows,
        ];
      }
    }

    return $build;
  }

  /**
   * Title callback for the user access page.
   */
  public function title(UserInterface $user): string {
    return (string) $this->t('Access - @user', ['@user' => $user->getDisplayName()]);
  }

}
