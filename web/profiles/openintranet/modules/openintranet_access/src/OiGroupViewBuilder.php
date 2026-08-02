<?php

declare(strict_types=1);

namespace Drupal\openintranet_access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * View builder for OiGroup entities.
 *
 * Uses Drupal's standard render elements that Gin theme styles nicely.
 */
final class OiGroupViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    /** @var \Drupal\openintranet_access\Entity\OiGroupInterface $entity */
    // Don't call parent to avoid default field rendering.
    $build = [
      '#cache' => [
        'tags' => $entity->getCacheTags(),
        'contexts' => ['user.permissions'],
      ],
    ];

    // Get group manager service.
    $groupManager = \Drupal::service('openintranet_access.group_manager');

    // Build the view using Drupal's standard elements for Gin compatibility.
    $build['group_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Group Information'),
      '#open' => TRUE,
      '#weight' => -10,
    ];

    // Description.
    $description = $entity->getDescription();
    if ($description) {
      $build['group_details']['description'] = [
        '#type' => 'item',
        '#title' => $this->t('Description'),
        '#markup' => $description,
      ];
    }

    // Parent group.
    $parent = $entity->getParent();
    if ($parent) {
      $build['group_details']['parent'] = [
        '#type' => 'item',
        '#title' => $this->t('Parent group'),
        '#markup' => Link::createFromRoute(
          $parent->getName(),
          'entity.oi_group.canonical',
          ['oi_group' => $parent->id()]
        )->toString(),
      ];
    }

    // Hierarchy path.
    $path = $entity->getPath();
    if (count($path) > 1) {
      $build['group_details']['hierarchy'] = [
        '#type' => 'item',
        '#title' => $this->t('Full path'),
        '#markup' => implode(' → ', $path),
      ];
    }

    // Status.
    $build['group_details']['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Status'),
      '#markup' => $entity->isActive()
        ? '<span class="color-success">' . $this->t('Active') . '</span>'
        : '<span class="color-warning">' . $this->t('Inactive') . '</span>',
    ];

    // Members section.
    $members = $groupManager->getGroupMembers($entity, FALSE);
    $member_count = count($members);

    $build['members'] = [
      '#type' => 'details',
      '#title' => $this->t('Members (@count)', ['@count' => $member_count]),
      '#open' => TRUE,
      '#weight' => 0,
    ];

    if (empty($members)) {
      $build['members']['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('No members in this group yet.'),
        '#attributes' => ['class' => ['messages', 'messages--status']],
      ];
    }
    else {
      // Build a proper table for members.
      $rows = [];
      $displayed = 0;
      foreach ($members as $member) {
        if ($displayed >= 10) {
          break;
        }
        $rows[] = [
          Link::createFromRoute($member->getDisplayName(), 'entity.user.canonical', ['user' => $member->id()]),
          $member->getEmail() ?: '-',
        ];
        $displayed++;
      }

      $build['members']['table'] = [
        '#type' => 'table',
        '#header' => [$this->t('Name'), $this->t('Email')],
        '#rows' => $rows,
        '#empty' => $this->t('No members.'),
      ];

      if ($member_count > 10) {
        $build['members']['more'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('... and @count more members', ['@count' => $member_count - 10]),
          '#attributes' => ['class' => ['description']],
        ];
      }
    }

    // Manage members button.
    $build['members']['actions'] = [
      '#type' => 'actions',
    ];
    $build['members']['actions']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage members'),
      '#url' => Url::fromRoute('openintranet_access.group_members', ['oi_group' => $entity->id()]),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    // Subgroups section.
    $children = $groupManager->getChildren($entity);

    if (!empty($children)) {
      $build['subgroups'] = [
        '#type' => 'details',
        '#title' => $this->t('Subgroups (@count)', ['@count' => count($children)]),
        '#open' => TRUE,
        '#weight' => 10,
      ];

      // Build table for subgroups.
      $rows = [];
      foreach ($children as $child) {
        $child_member_count = $groupManager->getMemberCount($child);
        $rows[] = [
          Link::createFromRoute($child->getName(), 'entity.oi_group.canonical', ['oi_group' => $child->id()]),
          $child_member_count,
          $child->isActive() ? $this->t('Active') : $this->t('Inactive'),
        ];
      }

      $build['subgroups']['table'] = [
        '#type' => 'table',
        '#header' => [$this->t('Name'), $this->t('Members'), $this->t('Status')],
        '#rows' => $rows,
      ];
    }

    // Metadata section.
    $build['metadata'] = [
      '#type' => 'details',
      '#title' => $this->t('Metadata'),
      '#open' => FALSE,
      '#weight' => 20,
    ];

    $build['metadata']['author'] = [
      '#type' => 'item',
      '#title' => $this->t('Created by'),
      '#markup' => $entity->getOwner() ? Link::createFromRoute(
        $entity->getOwner()->getDisplayName(),
        'entity.user.canonical',
        ['user' => $entity->getOwnerId()]
      )->toString() : $this->t('Unknown'),
    ];

    $created = $entity->get('created')->value;
    $changed = $entity->getChangedTime();

    $build['metadata']['created'] = [
      '#type' => 'item',
      '#title' => $this->t('Created'),
      '#markup' => \Drupal::service('date.formatter')->format((int) $created, 'medium'),
    ];

    if ($changed != $created) {
      $build['metadata']['changed'] = [
        '#type' => 'item',
        '#title' => $this->t('Last updated'),
        '#markup' => \Drupal::service('date.formatter')->format($changed, 'medium'),
      ];
    }

    return $build;
  }

}
