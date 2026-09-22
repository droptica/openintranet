<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_forum\Service\ForumCategoryTree;
use Drupal\taxonomy\TermInterface;

/**
 * Provides the full forum categories accordion block.
 *
 * Renders every top-level forum_category with its children and post counts.
 * Displayed on the category browser and single forum post pages.
 */
#[Block(
  id: 'openintranet_forum_categories_block',
  admin_label: new TranslatableMarkup('Forum: categories'),
  category: new TranslatableMarkup('Open Intranet Forum'),
)]
final class ForumCategoriesBlock extends ForumCategorySidebarBlockBase {

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
    $per_term_counts = $this->categoryTreeService->loadPostCountsPerTerm($all_term_ids);
    $total_count = (int) array_sum($per_term_counts);

    $active_top_level_id = $tree->resolveTopLevel($active_category_id);
    $categories = $this->buildAccordionItems($tree, $active_category_id, $active_top_level_id, $per_term_counts);

    if ($categories === []) {
      return [];
    }

    $categories_browser_url = $this->categoriesBrowserUrl();
    array_unshift($categories, [
      'term_id' => 0,
      'title' => $this->t('All'),
      'icon' => '',
      'is_open' => FALSE,
      'is_active' => $active_category_id === 0,
      'url' => $categories_browser_url,
      'children' => [],
      'count' => $total_count,
    ]);

    return [
      '#type' => 'component',
      '#component' => 'openintranet_forum:categories',
      '#props' => [
        'panel_title' => $this->t('Categories'),
        'categories' => $categories,
        'categories_browser_url' => $categories_browser_url,
      ],
      '#cache' => $this->categoryCacheMetadata(),
    ];
  }

  /**
   * Builds accordion items for the template.
   */
  private function buildAccordionItems(ForumCategoryTree $tree, int $active_id, int $active_top_id, array $per_term_counts): array {
    $items = [];
    $categories_browser_url = $this->categoriesBrowserUrl();

    foreach ($tree->topLevelIds as $top_id) {
      $term = $tree->terms[$top_id] ?? NULL;
      if (!$term instanceof TermInterface) {
        continue;
      }

      $items[] = [
        'term_id' => $top_id,
        'title' => $term->label(),
        'icon' => $this->categoryTreeService->buildIcon($term),
        'is_open' => FALSE,
        'is_active' => $active_id === $top_id || $active_top_id === $top_id,
        'url' => $categories_browser_url . '?category=' . $top_id,
        'children' => [],
        'count' => $tree->aggregateCount($top_id, $per_term_counts),
      ];
    }

    return $items;
  }

}
