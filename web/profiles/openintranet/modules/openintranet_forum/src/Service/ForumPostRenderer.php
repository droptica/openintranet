<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default implementation of the forum post renderer service.
 *
 * Builds the template variables shared by the forum post card view modes
 * (teaser, compact, full, and trending card).
 */
final class ForumPostRenderer implements ForumPostRendererInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly AccountProxyInterface $currentUser,
    private readonly TimeInterface $time,
    private readonly ForumStatisticsInterface $statistics,
    private readonly ContainerInterface $container,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function preparePostTemplateVariables(array &$variables, NodeInterface $node, string $viewMode): void {
    $module_path = $this->moduleExtensionList->getPath('openintranet_forum');
    $icon_base = base_path() . $module_path . '/images/icons';
    $owner = $node->getOwner();
    $author_url = $this->buildAuthorProfileUrl($owner);
    $picture = $this->buildUserPictureRenderArray($owner);
    $created = (int) $node->getCreatedTime();

    $variables['forum_post_icon_base'] = $icon_base;
    $variables['forum_post_url'] = $node->toUrl()->toString();
    $variables['forum_post_absolute_url'] = $node->toUrl()->setAbsolute(TRUE)->toString();
    $variables['forum_post_view_mode'] = $viewMode;
    $nid = (int) $node->id();
    $account = $this->currentUser;
    $variables['forum_post_like_count'] = $this->statistics->getReactionLikeCount($nid);
    $variables['forum_post_user_liked'] = !$account->isAnonymous() && $this->statistics->hasUserLiked($nid, (int) $account->id());
    $variables['forum_post_reply_count'] = (int) ($node->get('field_forum_reply_count')->value ?? 0);
    $variables['forum_post_share_count'] = $this->statistics->getShareCount($nid);
    $variables['forum_post_view_count'] = (int) ($node->get('field_forum_views')->value ?? 0);
    $variables['forum_post_author_name'] = $owner->getDisplayName();
    $variables['forum_post_author_profile_url'] = $author_url;
    $variables['forum_post_author_picture'] = $picture;
    $variables['forum_post_has_author_picture'] = $picture !== [];
    $variables['forum_post_date'] = $this->formatTrendingCardDate($created);
    $variables['forum_post_datetime_attr'] = $this->dateFormatter->format($created, 'html_datetime');
    $variables['forum_post_bookmark'] = $this->buildBookmarkLink($node);
    $variables['forum_post_actions'] = $this->buildPostActions($node);
    $variables['forum_post_show_meta'] = $viewMode !== 'category_browser_row';
    $variables['forum_post_has_image'] = $node->hasField('field_forum_image') && !$node->get('field_forum_image')->isEmpty();
    $variables['forum_post_excerpt'] = $this->buildPostExcerpt(
      $node,
      $viewMode === 'compact' ? 120 : 220,
    );
    $variables['forum_post_has_excerpt'] = $variables['forum_post_excerpt'] !== '';
    $variables['forum_post_categories'] = $this->buildTermLinks($node, 'field_forum_category', 'category');
    $variables['forum_post_tags'] = $this->buildTermLinks($node, 'field_forum_tags', 'tag');

    if ($viewMode === 'trending_card') {
      $variables['forum_trending_icon_base'] = $icon_base;
      $variables['forum_trending_like_count'] = $variables['forum_post_like_count'];
      $variables['forum_trending_reply_count'] = $variables['forum_post_reply_count'];
      $variables['forum_trending_share_count'] = $variables['forum_post_share_count'];
      $variables['forum_trending_absolute_url'] = $variables['forum_post_absolute_url'];
      $variables['forum_trending_author_name'] = $variables['forum_post_author_name'];
      $variables['forum_trending_author_profile_url'] = $variables['forum_post_author_profile_url'];
      $variables['forum_trending_author_picture'] = $variables['forum_post_author_picture'];
      $variables['forum_trending_has_author_picture'] = $variables['forum_post_has_author_picture'];
      $variables['forum_trending_date'] = $variables['forum_post_date'];
      $variables['forum_trending_datetime_attr'] = $variables['forum_post_datetime_attr'];
      $variables['forum_trending_bookmark'] = $variables['forum_post_bookmark'];
      $variables['forum_trending_has_image'] = $variables['forum_post_has_image'];
      $variables['forum_trending_actions'] = $variables['forum_post_actions'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPostExcerpt(NodeInterface $node, int $length): string {
    if (!$node->hasField('body') || $node->get('body')->isEmpty()) {
      return '';
    }

    $body = (string) ($node->get('body')->summary ?: $node->get('body')->value ?: '');
    $body = trim(Html::decodeEntities(strip_tags($body)));

    if ($body === '') {
      return '';
    }

    return Unicode::truncate($body, $length, TRUE, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function buildTermLinks(NodeInterface $node, string $fieldName, string $pathPrefix): array {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return [];
    }

    $links = [];
    foreach ($node->get($fieldName)->referencedEntities() as $term) {
      $links[] = [
        'title' => $term->label(),
        'url' => Url::fromUri('internal:/forum/' . $pathPrefix . '/' . $term->id())->toString(),
      ];
    }

    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function buildBookmarkLink(NodeInterface $node): array {
    if (!$this->moduleHandler->moduleExists('flag')) {
      return [];
    }
    $flag = $this->container->get('flag')->getFlagById('bookmark_forum_post');
    if ($flag === NULL) {
      return [];
    }
    $link = $this->container->get('flag.link_builder')->build(
      $node->getEntityTypeId(),
      (string) $node->id(),
      $flag->id()
    );

    // Flag returns a cache-only array (no #theme) when the user may not see the
    // link, and Twig's "is not empty" renders that as an empty kebab.
    return isset($link['#theme']) ? $link : [];
  }

  /**
   * Builds the owner action links (Edit / Delete) for the kebab menu.
   *
   * Mirrors the access-checked owner-action pattern used for comments in
   * ForumThemeHooks::preprocessComment().
   *
   * @param \Drupal\node\NodeInterface $node
   *   The forum post node.
   *
   * @return array
   *   A list of action items, each with 'title', 'url' and 'icon' keys. Empty
   *   when the current user may neither edit nor delete the post.
   */
  private function buildPostActions(NodeInterface $node): array {
    $actions = [];

    if ($node->access('update')) {
      $actions[] = [
        'title' => (string) $this->t('Edit'),
        'url' => $node->toUrl('edit-form')->toString(),
        'icon' => 'edit.svg',
      ];
    }

    if ($node->access('delete')) {
      $actions[] = [
        'title' => (string) $this->t('Delete'),
        'url' => $node->toUrl('delete-form')->toString(),
        'icon' => 'delete.svg',
      ];
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildAuthorProfileUrl(?AccountInterface $account, ?string $fallback = NULL): string {
    if ($account instanceof UserInterface && $account->id()) {
      return Url::fromRoute('entity.user.canonical', ['user' => $account->id()])->toString();
    }

    return $fallback ?? Url::fromRoute('<front>')->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function buildUserPictureRenderArray(UserInterface $account): array {
    if (!$account->hasField('user_picture') || $account->get('user_picture')->isEmpty()) {
      return [];
    }
    $view_builder = $this->entityTypeManager->getViewBuilder('user');
    return $view_builder->viewField($account->get('user_picture'), [
      'label' => 'hidden',
      'type' => 'image',
      'settings' => [
        'image_style' => 'thumbnail',
        'image_link' => '',
      ],
      'third_party_settings' => [],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function formatTrendingCardDate(int $createdTimestamp): string|TranslatableMarkup {
    $request_time = (int) $this->time->getRequestTime();
    $time_part = $this->dateFormatter->format($createdTimestamp, 'custom', 'H:i');

    $day_start = strtotime('today', $request_time);
    $yesterday_start = strtotime('yesterday', $request_time);
    if ($createdTimestamp >= $day_start) {
      return $this->t('@day, @time', ['@day' => $this->t('today'), '@time' => $time_part]);
    }
    if ($createdTimestamp >= $yesterday_start && $createdTimestamp < $day_start) {
      return $this->t('@day, @time', ['@day' => $this->t('yesterday'), '@time' => $time_part]);
    }

    return $this->dateFormatter->format($createdTimestamp, 'custom', 'm/d/Y');
  }

}
