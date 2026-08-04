<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\openintranet_forum\Service\ForumTrendingScorerInterface;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;

/**
 * Hook implementations for the forum trending/popular ORDER BY override.
 */
final class ForumTrendingHooks {

  /**
   * Fallback trending result limit.
   */
  private const DEFAULT_TRENDING_LIMIT = 6;

  public function __construct(
    private readonly ForumTrendingScorerInterface $scorer,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_views_pre_view().
   *
   * Applies the configurable trending window and result limit to the
   * forum_trending:block_1 carousel. The view ships with a hardcoded 7-day
   * window and 6-item pager; these settings let site builders change both
   * without editing the view config.
   */
  #[Hook('views_pre_view')]
  public function viewsPreView(ViewExecutable $view, string $display_id, array &$args): void {
    if ($view->storage->id() !== 'forum_trending' || $display_id !== 'block_1') {
      return;
    }

    $limit = (int) ($this->configFactory
      ->get('openintranet_forum.settings')
      ->get('trending_limit') ?? self::DEFAULT_TRENDING_LIMIT);
    if ($limit < 1) {
      $limit = self::DEFAULT_TRENDING_LIMIT;
    }
    $view->setItemsPerPage($limit);

    $days = $this->scorer->trendingDays();

    // Override the "created >= -N days" offset filter on the display so the
    // recency window matches the configured value. setDisplay() ensures the
    // display handler (and its options) are initialized before we touch them.
    $view->setDisplay($display_id);
    $filters = $view->display_handler->getOption('filters');
    if (isset($filters['created']['value']['type']) && $filters['created']['value']['type'] === 'offset') {
      $filters['created']['value']['value'] = '-' . $days . ' days';
      $view->display_handler->overrideOption('filters', $filters);
    }
  }

  /**
   * Implements hook_views_query_alter().
   *
   * Applies the trending score to both forum_trending (7-day filter) and
   * forum_popular (30-day filter). Same expression, different window — see
   * VIEWS_PLAN.md.
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    $view_id = $view->storage->id();
    if (!in_array($view_id, ['forum_trending', 'forum_popular'], TRUE) || !$query instanceof Sql) {
      return;
    }

    $base_table = $view->storage->get('base_table');
    $views_field_table = $query->ensureTable('node__field_forum_views', $base_table);
    if ($views_field_table === FALSE) {
      return;
    }

    $expression = $this->scorer->buildScoreSql($base_table, $views_field_table);
    $query->orderby = [];
    $query->addOrderBy(NULL, $expression, 'DESC', 'openintranet_forum_trending_score');
  }

}
