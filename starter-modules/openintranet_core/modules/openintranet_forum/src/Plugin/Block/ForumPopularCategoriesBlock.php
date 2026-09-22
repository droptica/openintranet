<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Plugin\Block;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_forum\Service\ForumCategoryTree;
use Drupal\openintranet_forum\Service\ForumCategoryTreeServiceInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the "Popular Categories" accordion block for forum listing pages.
 *
 * Shows the top-N top-level forum_category terms ordered by post count in the
 * last 30 days, each expandable to reveal its child categories as plain topic
 * links.
 */
#[Block(
  id: 'openintranet_forum_popular_categories_block',
  admin_label: new TranslatableMarkup('Forum: popular categories'),
  category: new TranslatableMarkup('Open Intranet Forum'),
)]
final class ForumPopularCategoriesBlock extends ForumCategorySidebarBlockBase {

  private const DEFAULT_LIMIT = 6;

  /**
   * Constructs a ForumPopularCategoriesBlock object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\openintranet_forum\Service\ForumCategoryTreeServiceInterface $categoryTreeService
   *   The forum category tree service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    RequestStack $requestStack,
    ForumCategoryTreeServiceInterface $categoryTreeService,
    private readonly TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $requestStack, $categoryTreeService);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $active_category_id = $this->activeCategoryId();
    $tree = $this->categoryTreeService->loadTree();

    if ($tree->topLevelIds === []) {
      return [];
    }

    $all_term_ids = array_keys($tree->terms);
    $thirty_days_ago = $this->time->getRequestTime() - 30 * 86400;
    $per_term_counts = $this->categoryTreeService->loadPostCountsPerTerm($all_term_ids, $thirty_days_ago);

    $categories_browser_url = $this->categoriesBrowserUrl();
    $active_top_level_id = $tree->resolveTopLevel($active_category_id);
    $categories = $this->buildPopularItems(
      $tree,
      $active_category_id,
      $active_top_level_id,
      $per_term_counts,
      $categories_browser_url,
      self::DEFAULT_LIMIT,
    );

    if ($categories === []) {
      return [];
    }

    return [
      '#type' => 'component',
      '#component' => 'openintranet_forum:popular-categories',
      '#props' => [
        'panel_title' => $this->t('Popular Categories'),
        'categories' => $categories,
        'categories_browser_url' => $categories_browser_url,
      ],
      '#cache' => $this->categoryCacheMetadata(),
    ];
  }

  /**
   * Builds the accordion items sorted by post popularity.
   */
  private function buildPopularItems(
    ForumCategoryTree $tree,
    int $active_id,
    int $active_top_id,
    array $per_term_counts,
    string $browser_url,
    int $limit,
  ): array {
    $scored = [];
    foreach ($tree->topLevelIds as $top_id) {
      $term = $tree->terms[$top_id] ?? NULL;
      if (!$term instanceof TermInterface) {
        continue;
      }

      $scored[] = [
        'term' => $term,
        'top_id' => $top_id,
        'count' => $tree->aggregateCount($top_id, $per_term_counts),
      ];
    }

    usort($scored, static function (array $a, array $b): int {
      if ($b['count'] !== $a['count']) {
        return $b['count'] <=> $a['count'];
      }
      return strnatcasecmp($a['term']->label(), $b['term']->label());
    });

    $scored = array_slice($scored, 0, $limit);

    $items = [];
    foreach ($scored as $entry) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $entry['term'];
      $top_id = $entry['top_id'];

      $is_active_top = $active_id === $top_id || $active_top_id === $top_id;
      $children = $this->buildChildTopics(
        $top_id,
        $tree,
        $active_id,
        $browser_url,
      );

      $items[] = [
        'term_id' => $top_id,
        'title' => $term->label(),
        'icon' => $this->categoryTreeService->buildIcon($term),
        'is_open' => $is_active_top && $children !== [],
        'is_active' => $is_active_top,
        'url' => $browser_url . '?category=' . $top_id,
        'children' => $children,
      ];
    }

    return $items;
  }

  /**
   * Builds topic links shown when a category is expanded.
   */
  private function buildChildTopics(int $parent_id, ForumCategoryTree $tree, int $active_id, string $browser_url): array {
    $child_ids = $tree->childrenByParent[$parent_id] ?? [];
    $children = [];

    foreach ($child_ids as $child_id) {
      $term = $tree->terms[$child_id] ?? NULL;
      if (!$term instanceof TermInterface) {
        continue;
      }

      $children[] = [
        'term_id' => $child_id,
        'title' => $term->label(),
        'icon' => $this->categoryTreeService->buildIcon($term),
        'is_active' => $active_id === $child_id,
        'url' => $browser_url . '?category=' . $child_id,
      ];
    }

    return $children;
  }

}
