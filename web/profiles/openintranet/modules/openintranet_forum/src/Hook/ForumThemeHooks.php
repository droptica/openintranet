<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Hook;

use Drupal\comment\CommentInterface;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Render\Element;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\Service\ForumActivityFeedInterface;
use Drupal\openintranet_forum\Service\ForumPageContextInterface;
use Drupal\openintranet_forum\Service\ForumPostRendererInterface;
use Drupal\openintranet_forum\Service\ForumStatisticsInterface;
use Drupal\openintranet_forum\Service\ForumTrendingEmbedInterface;
use Drupal\views\ViewExecutable;

/**
 * Theme and render pipeline hook implementations for the forum module.
 */
final class ForumThemeHooks implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['stripReplyImageTabledrag'];
  }

  /**
   * Implements hook_element_info_alter().
   *
   * Prepends a #pre_render to the table element so it runs before core's
   * Table::preRenderTable (which calls drupal_attach_tabledrag).
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    if (isset($info['table'])) {
      array_unshift($info['table']['#pre_render'], [static::class, 'stripReplyImageTabledrag']);
    }
  }

  /**
   * Removes tabledrag from the comment image field's multi-value table.
   *
   * Reordering comment attachments is pointless (the handles are hidden) and
   * core tabledrag.js throws "Cannot read properties of null" on the restyled
   * table after an AJAX upload. Clearing #tabledrag before preRenderTable stops
   * the tabledrag settings/library from being attached. Runs for every table;
   * a cheap id check keeps it scoped to the reply-images widget.
   */
  public static function stripReplyImageTabledrag(array $element): array {
    $id = $element['#attributes']['id'] ?? '';
    if (is_string($id) && str_contains($id, 'field-forum-reply-images') && !empty($element['#tabledrag'])) {
      $element['#tabledrag'] = [];
    }
    return $element;
  }

  public function __construct(
    private readonly ForumPostRendererInterface $postRenderer,
    private readonly ForumPageContextInterface $pageContext,
    private readonly ForumStatisticsInterface $statistics,
    private readonly ForumActivityFeedInterface $activityFeed,
    private readonly AccountProxyInterface $currentUser,
    private readonly RouteMatchInterface $routeMatch,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ForumTrendingEmbedInterface $trendingEmbed,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    $template_path = $path . '/templates';
    $field = $existing['field'] ?? [];
    $comment = $existing['comment'] ?? [];
    $views_view = $existing['views_view'] ?? [];
    $views_exposed_form = $existing['views_exposed_form'] ?? [];
    $node = $existing['node'] ?? [];
    $page = $existing['page'] ?? [];

    $hooks = [
      'views_view__openintranet_forum_page' => [
        'base hook' => 'views_view',
        'template' => 'views-view--openintranet-forum-page',
        'path' => $template_path,
        'variables' => $views_view['variables'] ?? [],
        'preprocess functions' => $views_view['preprocess functions'] ?? [],
      ],
      'views_view__openintranet_forum_trending' => [
        'base hook' => 'views_view',
        'template' => 'views-view--forum-trending--block-1',
        'path' => $template_path,
        'variables' => $views_view['variables'] ?? [],
        'preprocess functions' => $views_view['preprocess functions'] ?? [],
      ],
      // Feed centre list. Registered under a non-colliding name (not the
      // natural views_view__forum_latest_posts__block_1) for the same reason
      // as trending: a hook named exactly like a Views auto-suggestion bypasses
      // the base preprocess. Applied via the suggestion alter below.
      'views_view__openintranet_forum_latest' => [
        'base hook' => 'views_view',
        'template' => 'views-view--forum-latest-posts--block-1',
        'path' => $template_path,
        'variables' => $views_view['variables'] ?? [],
        'preprocess functions' => $views_view['preprocess functions'] ?? [],
      ],
      'openintranet_forum_categories_browser' => [
        'template' => 'openintranet-forum-categories-browser',
        'path' => $template_path,
        'variables' => [
          'header_title' => '',
          'breadcrumb_title' => '',
          'page_title' => '',
          'groups' => [],
          'filters' => [],
          'main_search_hidden' => [],
          'categories_browser_url' => '',
          'forum_page_featured' => [],
          'forum_page_has_featured' => FALSE,
          'forum_page_sidebar_first' => [],
          'forum_page_has_sidebar_first' => FALSE,
          'forum_page_sidebar_second' => [],
          'forum_page_has_sidebar_second' => FALSE,
        ],
      ],
      'views_exposed_form__forum_search__page_1' => [
        'base hook' => 'views_exposed_form',
        'template' => 'views-exposed-form--forum-search--page-1',
        'path' => $template_path,
        'render element' => $views_exposed_form['render element'] ?? 'form',
        'preprocess functions' => $views_exposed_form['preprocess functions'] ?? [],
      ],
      'field__node__comment_forum__forum_post__full' => [
        'base hook' => 'field',
        'template' => 'field--node--comment-forum--forum-post--full',
        'path' => $template_path,
        'render element' => $field['render element'] ?? 'element',
        'preprocess functions' => $field['preprocess functions'] ?? [],
      ],
      'comment__comment_forum__forum_post' => [
        'base hook' => 'comment',
        'template' => 'comment--comment-forum--forum-post',
        'path' => $template_path,
        'render element' => $comment['render element'] ?? 'elements',
        'preprocess functions' => $comment['preprocess functions'] ?? [],
      ],
      'openintranet_forum_browser_filters' => [
        'template' => 'openintranet-forum-browser-filters',
        'path' => $template_path,
        'variables' => [
          'panel' => 'filters',
          'url' => '',
          'filters' => [],
          'filter_options' => [],
          'search_hidden' => [],
          'filter_hidden' => [],
        ],
      ],
      'openintranet_forum_feed_page' => [
        'template' => 'openintranet-forum-feed-page',
        'path' => $template_path,
        'variables' => [
          'hero_title' => '',
          'hero_text' => '',
          'hero_image' => '',
          'featured' => [],
          'has_featured' => FALSE,
          'left_rail' => [],
          'has_left_rail' => FALSE,
          'center_rail' => [],
          'right_rail' => [],
          'has_right_rail' => FALSE,
          'tabs' => [],
        ],
      ],
      'page__node__forum_post' => [
        'base hook' => 'page',
        'template' => 'page--node--forum-post',
        'path' => $template_path,
        'render element' => $page['render element'] ?? 'page',
        'preprocess functions' => $page['preprocess functions'] ?? [],
      ],
      'views_view_unformatted__forum_posts' => [
        'base hook' => 'views_view_unformatted',
        'template' => 'views-view-unformatted--forum-posts',
        'path' => $template_path,
      ],
      'views_mini_pager__openintranet_forum' => [
        'base hook' => 'views_mini_pager',
        'template' => 'views-mini-pager--openintranet-forum',
        'path' => $template_path,
      ],
    ];

    foreach (['teaser', 'compact', 'category_browser_row', 'full', 'trending_card'] as $variant) {
      $hooks['node__forum_post__' . $variant] = [
        'base hook' => 'node',
        'template' => 'node--forum-post--' . str_replace('_', '-', $variant),
        'path' => $template_path,
        'render element' => $node['render element'] ?? 'elements',
        'preprocess functions' => $node['preprocess functions'] ?? [],
      ];
    }

    return $hooks;
  }

  /**
   * Implements hook_theme_suggestions_views_view_unformatted_alter().
   */
  #[Hook('theme_suggestions_views_view_unformatted_alter')]
  public function themeSuggestionsViewsViewUnformattedAlter(array &$suggestions, array $variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable) {
      return;
    }

    $view_id = $view->storage->id();
    $display_id = (string) $view->current_display;

    if ($this->pageContext->isForumPageView($view) || ($view_id === 'forum_latest_posts' && $display_id === 'block_1')) {
      $suggestions[] = 'views_view_unformatted__forum_posts';
    }
  }

  /**
   * Implements hook_theme_suggestions_views_mini_pager_alter().
   *
   * The mini-pager render array does not carry the view id, so the active
   * forum listing view is identified from the current route name
   * (view.<view_id>.<display_id>).
   */
  #[Hook('theme_suggestions_views_mini_pager_alter')]
  public function themeSuggestionsViewsMiniPagerAlter(array &$suggestions, array $variables): void {
    $route_name = (string) $this->routeMatch->getRouteName();
    if (!str_starts_with($route_name, 'view.')) {
      return;
    }

    $forum_views = [
      'forum_latest_posts',
      'forum_popular',
      'forum_unanswered',
      'forum_active',
      'forum_category',
      'forum_tag',
      'forum_my_posts',
    ];

    foreach ($forum_views as $view_id) {
      if (str_starts_with($route_name, 'view.' . $view_id . '.')) {
        $suggestions[] = 'views_mini_pager__openintranet_forum';
        return;
      }
    }
  }

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'forum_post') {
      return;
    }

    $this->postRenderer->preparePostTemplateVariables(
      $variables,
      $node,
      (string) ($variables['view_mode'] ?? 'full'),
    );

    $variables['#cache']['contexts'][] = 'user';
  }

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    if ($view->storage->id() === 'forum_popular_tags') {
      $view->element['#attached']['library'][] = 'openintranet_forum/forum.popular-tags';
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for views_view.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable) {
      return;
    }

    $view_id = $view->storage->id();
    $display_id = (string) $view->current_display;

    if ($view_id === 'forum_trending' && $display_id === 'block_1') {
      $variables['#attached']['library'][] = 'openintranet_forum/forum_trending';
      $module_path = $this->moduleExtensionList->getPath('openintranet_forum');
      $variables['forum_trending_arrow_src'] = base_path() . $module_path . '/images/icons/arrow-right.svg';
      $attributes = &$variables['attributes'];
      if (isset($attributes['class']) && is_array($attributes['class'])) {
        $strip = ['block', 'block-background', 'd-flex', 'flex-column', 'justify-content-center'];
        $attributes['class'] = array_values(array_filter(
          $attributes['class'],
          static fn(string $c) => !in_array($c, $strip, TRUE),
        ));
      }
      // The trending view must never embed itself: stop before the listing
      // logic below, which injects the trending carousel as the featured slot.
      return;
    }

    if (!$this->pageContext->isForumPageView($view) && !($view_id === 'forum_latest_posts' && $display_id === 'block_1')) {
      return;
    }

    $variables['forum_page_summary_title'] = $this->pageContext->getForumPageTitle();

    $variables['forum_page_section_title'] = $this->pageContext->getForumPageSectionTitle($view);

    // Surface the trending carousel as the featured slot on the four primary
    // listing tabs shown in the mockups. Other listing displays (category, tag,
    // search, unanswered) keep the region-based featured slot.
    $trending_tabs = ['forum_latest_posts', 'forum_popular', 'forum_my_posts', 'forum_active'];
    if (in_array($view_id, $trending_tabs, TRUE) && str_starts_with($display_id, 'page_')) {
      $variables['forum_page_featured'] = $this->trendingEmbed->build();
      $variables['forum_page_has_featured'] = !Element::isEmpty($variables['forum_page_featured']);
    }
    else {
      $variables['forum_page_featured'] = $this->pageContext->buildRegionRenderArray('forum_featured_top');
      $variables['forum_page_has_featured'] = $this->pageContext->regionHasBlocks($variables['forum_page_featured']);
    }
    $variables['forum_page_sidebar_second'] = $this->pageContext->buildRegionRenderArray('forum_sidebar_second');
    $variables['forum_page_has_sidebar_second'] = $this->pageContext->regionHasBlocks($variables['forum_page_sidebar_second']);
    $variables['forum_page_breadcrumb'] = $this->pageContext->buildListingBreadcrumb($view);
    $page_view_id = $variables['view']->storage->id();
    $variables['forum_page_tabs'] = $this->pageContext->buildForumPageTabs($page_view_id);

    if ($view_id === 'forum_active' && $display_id === 'page_1') {
      $variables['rows'] = $this->activityFeed
        ->buildUserActivityFeed((int) $this->currentUser->id(), 20, 'page');
      $variables['empty'] = [];
      $variables['pager'] = [];
      $variables['more'] = [];
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for page.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'forum_post') {
      return;
    }

    $featured = $this->pageContext->buildRegionRenderArray('forum_featured_top');
    $sidebar = $this->pageContext->buildRegionRenderArray('forum_sidebar_second');

    $summary_title = $this->pageContext->getForumPageTitle();

    $variables['forum_post_page'] = TRUE;
    $variables['forum_post_summary_title'] = $summary_title;
    $variables['forum_post_featured'] = $featured;
    $variables['forum_post_has_featured'] = $this->pageContext->regionHasBlocks($featured);
    $variables['forum_post_sidebar'] = $sidebar;
    $variables['forum_post_has_sidebar'] = $this->pageContext->regionHasBlocks($sidebar);
    $variables['forum_post_breadcrumb'] = $this->pageContext->buildPostBreadcrumb($node);
  }

  /**
   * Implements hook_theme_suggestions_views_view_alter().
   */
  #[Hook('theme_suggestions_views_view_alter')]
  public function themeSuggestionsViewsViewAlter(array &$suggestions, array $variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable) {
      return;
    }

    if ($view->storage->id() === 'forum_trending' && (string) $view->current_display === 'block_1') {
      $suggestions[] = 'views_view__openintranet_forum_trending';
      return;
    }

    if ($view->storage->id() === 'forum_latest_posts' && (string) $view->current_display === 'block_1') {
      $suggestions[] = 'views_view__openintranet_forum_latest';
      return;
    }

    if (!$this->pageContext->isForumPageView($view)) {
      return;
    }

    $suggestions[] = 'views_view__openintranet_forum_page';
  }

  /**
   * Implements hook_theme_suggestions_page_alter().
   */
  #[Hook('theme_suggestions_page_alter')]
  public function themeSuggestionsPageAlter(array &$suggestions, array $variables): void {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'forum_post') {
      return;
    }
    $suggestions[] = 'page__node__forum_post';
  }

  /**
   * Implements hook_theme_suggestions_field_alter().
   */
  #[Hook('theme_suggestions_field_alter')]
  public function themeSuggestionsFieldAlter(array &$suggestions, array $variables): void {
    $element = $variables['element'] ?? [];
    $node = $element['#object'] ?? NULL;

    if (!$node instanceof NodeInterface || $node->bundle() !== 'forum_post') {
      return;
    }

    if (($element['#field_name'] ?? '') !== 'comment_forum' || ($element['#view_mode'] ?? '') !== 'full') {
      return;
    }

    $suggestions[] = 'field__node__comment_forum__forum_post__full';
  }

  /**
   * Implements hook_preprocess_field() for the forum post comment field.
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    $element = $variables['element'] ?? [];
    $node = $element['#object'] ?? NULL;

    if (!$node instanceof NodeInterface
      || $node->bundle() !== 'forum_post'
      || ($element['#field_name'] ?? '') !== 'comment_forum'
      || ($element['#view_mode'] ?? '') !== 'full') {
      return;
    }

    $comment_count = 0;
    if ($node->hasField('comment_forum')) {
      $comment_count = (int) ($node->get('comment_forum')->comment_count ?? 0);
    }
    $variables['forum_comment_count'] = $comment_count;
  }

  /**
   * Implements hook_preprocess_HOOK() for comment.
   */
  #[Hook('preprocess_comment')]
  public function preprocessComment(array &$variables): void {
    $comment = $variables['comment'] ?? NULL;
    if (!$comment instanceof CommentInterface || $comment->bundle() !== 'forum_reply') {
      return;
    }

    $commented_entity = $comment->getCommentedEntity();
    if (!$commented_entity instanceof NodeInterface || $commented_entity->bundle() !== 'forum_post') {
      return;
    }

    $pid = (int) ($comment->get('pid')->target_id ?? 0);
    $thread = (string) ($comment->get('thread')->value ?? '');
    $reaction_counts = $this->statistics->getCommentReactionCounts((int) $comment->id());
    $is_soft_deleted = $comment->hasField('field_forum_reply_soft_deleted')
      && !$comment->get('field_forum_reply_soft_deleted')->isEmpty()
      && (bool) $comment->get('field_forum_reply_soft_deleted')->value;

    $variables['forum_comment_is_reply'] = $pid > 0;
    $variables['forum_comment_depth'] = $thread === '' ? 0 : substr_count($thread, '.');
    $variables['forum_comment_like_count'] = $reaction_counts['up'];
    $variables['forum_comment_dislike_count'] = $reaction_counts['down'];
    $variables['forum_comment_user_vote'] = $this->statistics->getCommentUserVote((int) $comment->id());
    $share_url = Url::fromUri('internal:/comment/' . (int) $comment->id())->setAbsolute()->toString();
    $variables['forum_comment_soft_deleted'] = $is_soft_deleted;
    $variables['forum_comment_actions'] = [];

    if ($commented_entity->hasField('comment_forum')
      && (int) $commented_entity->get('comment_forum')->status === CommentItemInterface::OPEN
      && $this->currentUser->hasPermission('post comments')) {
      $reply_target = ($variables['forum_comment_depth'] >= 2 && $pid > 0)
        ? $pid
        : (int) $comment->id();
      $variables['forum_comment_actions'][] = [
        'name' => 'reply',
        'title' => (string) $this->t('Reply'),
        'url' => Url::fromUri('internal:/comment/reply/node/' . (int) $commented_entity->id() . '/comment_forum/' . $reply_target)->toString(),
      ];
    }

    $variables['forum_comment_actions'][] = [
      'name' => 'share',
      'title' => (string) $this->t('Share'),
      'url' => $share_url,
    ];

    if ($comment->access('delete') && !$is_soft_deleted) {
      $variables['forum_comment_actions'][] = [
        'name' => 'delete',
        'title' => (string) $this->t('Delete'),
        'url' => Url::fromRoute('openintranet_forum.comment_soft_delete', ['comment' => $comment->id()])->toString(),
      ];
    }

    if ($comment->access('update') && !$is_soft_deleted) {
      $variables['forum_comment_actions'][] = [
        'name' => 'edit',
        'title' => (string) $this->t('Edit'),
        'url' => Url::fromUri('internal:/comment/' . (int) $comment->id() . '/edit')->toString(),
      ];
    }
  }

}
