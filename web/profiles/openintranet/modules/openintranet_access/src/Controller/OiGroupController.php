<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\openintranet_access\Entity\OiGroupInterface;
use Drupal\openintranet_access\Service\OiGroupManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for OI Group pages.
 */
final class OiGroupController extends ControllerBase {

  /**
   * Constructs the controller.
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
   * Displays a single group.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $oi_group
   *   The group entity.
   *
   * @return array
   *   A render array.
   */
  public function viewGroup(OiGroupInterface $oi_group): array {
    $build = [];

    // Group info.
    $build['info'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['oi-group-info']],
    ];

    // Parent.
    $parent = $oi_group->getParent();
    if ($parent) {
      $build['info']['parent'] = [
        '#type' => 'item',
        '#title' => $this->t('Parent group'),
        '#markup' => Link::createFromRoute(
          $parent->getName(),
          'entity.oi_group.canonical',
          ['oi_group' => $parent->id()]
        )->toString(),
      ];
    }

    // Description.
    $description = $oi_group->getDescription();
    if ($description) {
      $build['info']['description'] = [
        '#type' => 'item',
        '#title' => $this->t('Description'),
        '#markup' => $description,
      ];
    }

    // Status.
    $build['info']['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Status'),
      '#markup' => $oi_group->isActive() ? $this->t('Active') : $this->t('Inactive'),
    ];

    // Path (hierarchy).
    $path = $oi_group->getPath();
    if (count($path) > 1) {
      $build['info']['path'] = [
        '#type' => 'item',
        '#title' => $this->t('Hierarchy'),
        '#markup' => implode(' → ', $path),
      ];
    }

    // Members section.
    $members = $this->groupManager->getGroupMembers($oi_group, FALSE);
    $member_count = count($members);

    $build['members'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Members (@count)', ['@count' => $member_count]),
    ];

    if (empty($members)) {
      $build['members']['empty'] = [
        '#markup' => '<p>' . $this->t('No members in this group.') . '</p>',
      ];
    }
    else {
      $member_items = [];
      $displayed = 0;
      foreach ($members as $member) {
        if ($displayed >= 10) {
          $member_items[] = $this->t('... and @count more', ['@count' => $member_count - 10]);
          break;
        }
        $member_items[] = $member->getDisplayName() . ' (' . $member->getEmail() . ')';
        $displayed++;
      }

      $build['members']['list'] = [
        '#theme' => 'item_list',
        '#items' => $member_items,
      ];
    }

    $build['members']['manage_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage members'),
      '#url' => Url::fromRoute('openintranet_access.group_members', ['oi_group' => $oi_group->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    // Subgroups section.
    $children = $this->groupManager->getChildren($oi_group);

    if (!empty($children)) {
      $build['subgroups'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Subgroups (@count)', ['@count' => count($children)]),
      ];

      $child_items = [];
      foreach ($children as $child) {
        $child_member_count = $this->groupManager->getMemberCount($child);
        $child_items[] = Link::createFromRoute(
          $child->getName() . ' (' . $child_member_count . ' ' . $this->t('members') . ')',
          'entity.oi_group.canonical',
          ['oi_group' => $child->id()]
        )->toString();
      }

      $build['subgroups']['list'] = [
        '#theme' => 'item_list',
        '#items' => $child_items,
      ];
    }

    // Add cache tags.
    $build['#cache'] = [
      'tags' => $oi_group->getCacheTags(),
    ];

    return $build;
  }

  /**
   * Title callback for group view.
   *
   * @param \Drupal\openintranet_access\Entity\OiGroupInterface $oi_group
   *   The group entity.
   *
   * @return string
   *   The page title.
   */
  public function groupTitle(OiGroupInterface $oi_group): string {
    return $oi_group->getName();
  }

}
