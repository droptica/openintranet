<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default implementation of the forum filter options builder service.
 */
final class ForumFilterOptionsBuilder implements ForumFilterOptionsBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function readFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    $sort = (string) $request->query->get('sort', 'recent');

    return [
      'q' => trim((string) $request->query->get('q', '')),
      'category' => max(0, (int) $request->query->get('category', 0)),
      'tag' => max(0, (int) $request->query->get('tag', 0)),
      'author' => max(0, (int) $request->query->get('author', 0)),
      'sort' => in_array($sort, ['recent', 'popular', 'active'], TRUE) ? $sort : 'recent',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildHiddenFields(array $filters, array $exclude): array {
    $hidden_fields = [];

    foreach ($filters as $name => $value) {
      if (in_array($name, $exclude, TRUE)) {
        continue;
      }
      if ($name === 'sort' && $value === 'recent') {
        continue;
      }
      if (($name !== 'sort' && (int) $value === 0) || $value === '') {
        continue;
      }
      $hidden_fields[$name] = $value;
    }

    return $hidden_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function buildCategoryOptions(ForumCategoryTree $tree): array {
    $options = $this->anyOption();

    foreach ($tree->tree as $item) {
      $prefix = str_repeat('- ', (int) $item->depth);
      $options[(int) $item->tid] = $prefix . $item->name;
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildTagOptions(): array {
    $options = $this->anyOption();
    $tags = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('forum_tag', 0, NULL, FALSE);

    foreach ($tags as $tag) {
      $options[(int) $tag->tid] = $tag->name;
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildAuthorOptions(): array {
    $uids = $this->database->select('node_field_data', 'n')
      ->fields('n', ['uid'])
      ->condition('n.type', 'forum_post')
      ->condition('n.status', 1)
      ->distinct()
      ->range(0, 500)
      ->execute()
      ->fetchCol();

    $uids = array_values(array_filter(
      array_map('intval', $uids),
      static fn (int $uid): bool => $uid > 0,
    ));

    if ($uids === []) {
      return $this->anyOption();
    }

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
    $author_options = [];
    foreach ($users as $user) {
      $uid = (int) $user->id();
      if ($uid === 0) {
        continue;
      }
      $author_options[$uid] = $user->getDisplayName();
    }

    uasort(
      $author_options,
      static fn (string $a, string $b): int => strnatcasecmp($a, $b),
    );

    return $this->anyOption() + $author_options;
  }

  /**
   * The "- Any -" placeholder option keyed at 0.
   *
   * @return array
   *   A single-entry option list.
   */
  private function anyOption(): array {
    return [0 => $this->t('- Any -')];
  }

}
