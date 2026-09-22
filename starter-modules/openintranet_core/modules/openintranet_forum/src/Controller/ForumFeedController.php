<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\openintranet_forum\Service\ForumActivityFeedInterface;
use Drupal\openintranet_forum\Service\ForumPageContextInterface;
use Drupal\openintranet_forum\Service\ForumTrendingEmbedInterface;

/**
 * Controller for the personalized forum feed page.
 */
final class ForumFeedController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  /**
   * Constructs a ForumFeedController object.
   */
  public function __construct(
    private readonly ForumActivityFeedInterface $activityFeed,
    private readonly ForumPageContextInterface $pageContext,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ForumTrendingEmbedInterface $trendingEmbed,
  ) {}

  /**
   * Returns the personalized feed page render array.
   */
  public function build(): array {
    $module_path = $this->moduleExtensionList->getPath('openintranet_forum');
    $featured = $this->trendingEmbed->build();
    $sidebar_second = $this->pageContext->buildRegionRenderArray('forum_sidebar_second');
    $left_rail = $this->activityFeed->buildCurrentUserFeed(10, 'sidebar', TRUE);

    return [
      '#theme' => 'openintranet_forum_feed_page',
      '#hero_title' => $this->t('Welcome to the forum'),
      '#hero_text' => $this->t('Share ideas, ask practical questions, and connect with colleagues across Open Intranet. The forum brings conversations, announcements, and everyday help into one place.'),
      '#hero_image' => base_path() . $module_path . '/images/forum-hero-banner.png',
      '#featured' => $featured,
      '#has_featured' => !Element::isEmpty($featured),
      '#left_rail' => $left_rail,
      '#has_left_rail' => $left_rail !== [],
      '#center_rail' => views_embed_view('forum_latest_posts', 'block_1') ?? [],
      '#right_rail' => $sidebar_second,
      '#has_right_rail' => $this->pageContext->regionHasBlocks($sidebar_second),
      '#tabs' => $this->pageContext->buildForumPageTabs('forum_latest_posts'),
      '#attached' => [
        'library' => [
          'openintranet_forum/forum.base',
          'openintranet_forum/forum.page',
        ],
      ],
      '#cache' => [
        'contexts' => [
          'languages:language_interface',
          'user',
          'user.node_grants:view',
        ],
        'tags' => ['comment_list', 'node_list', 'user_list'],
        'max-age' => 0,
      ],
    ];
  }

}
