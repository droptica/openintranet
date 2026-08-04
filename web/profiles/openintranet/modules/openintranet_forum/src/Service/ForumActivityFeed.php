<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Default implementation of the forum activity feed service.
 */
final class ForumActivityFeed implements ForumActivityFeedInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ForumStatisticsInterface $statistics,
    private readonly ForumPostRendererInterface $postRenderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildCurrentUserFeed(int $limit, string $variant = 'sidebar', bool $showMore = FALSE): array {
    if ($this->currentUser->isAnonymous()) {
      return [];
    }

    $activity = $this->loadRecentActivityForUser((int) $this->currentUser->id());

    return $this->buildFeedComponent(
      array_slice($activity, 0, $limit),
      count($activity) > $limit,
      $this->t('There is no recent activity on your posts yet.'),
      Url::fromUri('internal:/forum/your-posts')->toString(),
      $variant,
      $showMore,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildUserActivityFeed(int $uid, int $limit, string $variant = 'sidebar', bool $showMore = FALSE): array {
    if ($uid < 1) {
      return [];
    }

    $activity = $this->loadActivityOnOthers($uid);

    return $this->buildFeedComponent(
      array_slice($activity, 0, $limit),
      count($activity) > $limit,
      $this->t('You have not interacted with any forum posts yet.'),
      Url::fromUri('internal:/forum/active')->toString(),
      $variant,
      $showMore,
    );
  }

  /**
   * Builds the activity-feed component render array.
   *
   * @param array $items
   *   The prepared feed items.
   * @param bool $hasMore
   *   Whether more items exist beyond the displayed slice.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $emptyMessage
   *   Message shown when there are no items.
   * @param string $moreUrl
   *   URL for the "view more" link.
   * @param string $variant
   *   The component variant.
   * @param bool $showMore
   *   Whether to show the "view more" link.
   *
   * @return array
   *   The component render array.
   */
  private function buildFeedComponent(array $items, bool $hasMore, string|TranslatableMarkup $emptyMessage, string $moreUrl, string $variant, bool $showMore): array {
    $module_path = $this->moduleExtensionList->getPath('openintranet_forum');

    return [
      '#type' => 'component',
      '#component' => 'openintranet_forum:activity-feed',
      '#props' => [
        'items' => $items,
        'empty_message' => $emptyMessage,
        'icon_base' => base_path() . $module_path . '/images/icons',
        'more_url' => $moreUrl,
        'show_more' => $showMore && $hasMore,
        'variant' => $variant,
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

  /**
   * Builds a single activity-feed item from raw activity data.
   *
   * @param array $item
   *   The raw activity record.
   * @param \Drupal\node\NodeInterface $node
   *   The forum post node the activity targets.
   * @param int $likeCount
   *   The post's like count.
   * @param array $actorPicture
   *   The actor's picture render array (or []).
   * @param string $actorName
   *   The actor's display name.
   * @param string $actorProfileUrl
   *   The actor's profile URL (or '').
   * @param string $actionText
   *   The call-to-action label (or '' to hide it).
   *
   * @return array
   *   The prepared feed item.
   */
  private function buildFeedItem(array $item, NodeInterface $node, int $likeCount, array $actorPicture, string $actorName, string $actorProfileUrl, string $actionText): array {
    return [
      'kind' => $item['kind'],
      'label' => $item['label'],
      'actor_name' => $actorName,
      'actor_profile_url' => $actorProfileUrl,
      'actor_picture' => $actorPicture,
      'has_actor_picture' => $actorPicture !== [],
      'timestamp' => $this->postRenderer->formatTrendingCardDate((int) $item['timestamp']),
      'timestamp_attr' => $this->dateFormatter->format((int) $item['timestamp'], 'html_datetime'),
      'post_title' => $node->label(),
      'post_url' => $node->toUrl()->toString(),
      'like_count' => $likeCount,
      'reply_count' => (int) ($node->get('field_forum_reply_count')->value ?? 0),
      'share_count' => 0,
      'preview_text' => $item['preview_text'],
      'action_text' => $actionText,
      'action_url' => $item['action_url'],
    ];
  }

  /**
   * Loads the given user's replies and reactions on posts they do not own.
   */
  private function loadActivityOnOthers(int $uid): array {
    if ($uid < 1) {
      return [];
    }

    $activity = array_merge(
      $this->queryUserRepliesOnOthers($uid),
      $this->queryUserReactionsOnOthers($uid),
    );

    if ($activity === []) {
      return [];
    }

    usort($activity, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

    $node_ids = array_values(array_unique(array_map(static fn(array $item): int => (int) $item['nid'], $activity)));
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($node_ids);
    $like_counts = $this->statistics->loadVoteSumTotals($node_ids);

    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    $actor_picture = $account instanceof UserInterface
      ? $this->postRenderer->buildUserPictureRenderArray($account)
      : [];
    $actor_name = $account instanceof UserInterface ? $account->getDisplayName() : (string) $this->t('You');
    $actor_profile_url = $this->postRenderer->buildAuthorProfileUrl($account, '');

    $items = [];
    foreach ($activity as $item) {
      $node = $nodes[$item['nid']] ?? NULL;
      if (!$node instanceof NodeInterface || $node->bundle() !== 'forum_post' || !$node->isPublished()) {
        continue;
      }

      $items[] = $this->buildFeedItem(
        $item,
        $node,
        (int) ($like_counts[(int) $node->id()] ?? 0),
        $actor_picture,
        $actor_name,
        $actor_profile_url,
        $item['kind'] === 'reaction' ? '' : (string) $this->t('Open post'),
      );
    }

    return $items;
  }

  /**
   * Loads recent activity affecting forum posts authored by the given user.
   */
  private function loadRecentActivityForUser(int $uid): array {
    if ($uid < 1) {
      return [];
    }

    $activity = array_merge(
      $this->queryReplyActivity($uid),
      $this->queryReactionActivity($uid),
    );

    if ($activity === []) {
      return [];
    }

    usort($activity, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

    $node_ids = array_values(array_unique(array_map(static fn(array $item): int => (int) $item['nid'], $activity)));
    $actor_ids = array_values(array_unique(array_filter(array_map(static fn(array $item): int => (int) ($item['actor_uid'] ?? 0), $activity))));

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($node_ids);
    $users = $actor_ids === []
      ? []
      : $this->entityTypeManager->getStorage('user')->loadMultiple($actor_ids);
    $like_counts = $this->statistics->loadVoteSumTotals($node_ids);

    $items = [];
    foreach ($activity as $item) {
      $node = $nodes[$item['nid']] ?? NULL;
      if (!$node instanceof NodeInterface || $node->bundle() !== 'forum_post' || !$node->isPublished()) {
        continue;
      }

      $actor = NULL;
      if (!empty($item['actor_uid']) && isset($users[(int) $item['actor_uid']])) {
        $candidate = $users[(int) $item['actor_uid']];
        if ($candidate instanceof UserInterface) {
          $actor = $candidate;
        }
      }

      $actor_picture = $actor instanceof UserInterface
        ? $this->postRenderer->buildUserPictureRenderArray($actor)
        : [];

      $items[] = $this->buildFeedItem(
        $item,
        $node,
        (int) ($like_counts[(int) $node->id()] ?? 0),
        $actor_picture,
        $actor?->getDisplayName() ?: ($item['actor_name'] ?: (string) $this->t('Someone')),
        $this->postRenderer->buildAuthorProfileUrl($actor, ''),
        $item['kind'] === 'reaction' ? '' : (string) $this->t('Reply'),
      );
    }

    return $items;
  }

  /**
   * Queries replies written by the user on forum posts they do not own.
   */
  private function queryUserRepliesOnOthers(int $uid): array {
    $query = $this->database->select('comment_field_data', 'c');
    $query->fields('c', ['cid', 'created', 'entity_id', 'pid']);
    $query->addField('cb', 'comment_body_value', 'preview_text');
    $query->join('node_field_data', 'n', 'c.entity_id = n.nid');
    $query->leftJoin('comment__comment_body', 'cb', 'cb.entity_id = c.cid AND cb.deleted = 0');
    $query->condition('n.type', 'forum_post');
    $query->condition('n.status', 1);
    $query->condition('n.uid', $uid, '<>');
    $query->condition('c.entity_type', 'node');
    $query->condition('c.comment_type', 'forum_reply');
    $query->condition('c.status', 1);
    $query->condition('c.uid', $uid);
    $query->orderBy('c.created', 'DESC');

    $items = [];
    foreach ($query->execute()->fetchAllAssoc('cid') as $record) {
      $items[] = [
        'kind' => 'reply',
        'label' => (int) $record->pid > 0
          ? $this->t('You replied to a comment on:')
          : $this->t('You replied to:'),
        'timestamp' => (int) $record->created,
        'nid' => (int) $record->entity_id,
        'preview_text' => $this->sanitizePreview($record->preview_text ?? NULL),
        'action_url' => Url::fromUri('internal:/node/' . (int) $record->entity_id)->toString() . '#comment-' . (int) $record->cid,
      ];
    }

    return $items;
  }

  /**
   * Queries reactions the user made on forum posts they do not own.
   */
  private function queryUserReactionsOnOthers(int $uid): array {
    if (!$this->hasVotingApiVoteTable()) {
      return [];
    }

    $query = $this->database->select('votingapi_vote', 'v');
    $query->fields('v', ['id', 'timestamp', 'entity_id']);
    $query->join('node_field_data', 'n', 'v.entity_id = n.nid');
    $query->condition('v.entity_type', 'node');
    $query->condition('n.type', 'forum_post');
    $query->condition('n.status', 1);
    $query->condition('n.uid', $uid, '<>');
    $query->condition('v.user_id', $uid);
    $query->orderBy('v.timestamp', 'DESC');

    $items = [];
    foreach ($query->execute()->fetchAllAssoc('id') as $record) {
      $items[] = [
        'kind' => 'reaction',
        'label' => $this->t('You liked:'),
        'timestamp' => (int) $record->timestamp,
        'nid' => (int) $record->entity_id,
        'preview_text' => '',
        'action_url' => '',
      ];
    }

    return $items;
  }

  /**
   * Queries reply activity on forum posts owned by the given user.
   */
  private function queryReplyActivity(int $uid): array {
    $query = $this->database->select('comment_field_data', 'c');
    $query->fields('c', ['cid', 'created', 'uid', 'name', 'entity_id', 'pid']);
    $query->addField('p', 'uid', 'parent_uid');
    $query->addField('cb', 'comment_body_value', 'preview_text');
    $query->join('node_field_data', 'n', 'c.entity_id = n.nid');
    $query->leftJoin('comment_field_data', 'p', 'c.pid = p.cid');
    $query->leftJoin('comment__comment_body', 'cb', 'cb.entity_id = c.cid AND cb.deleted = 0');
    $query->condition('n.type', 'forum_post');
    $query->condition('n.status', 1);
    $query->condition('n.uid', $uid);
    $query->condition('c.entity_type', 'node');
    $query->condition('c.comment_type', 'forum_reply');
    $query->condition('c.status', 1);
    $query->condition('c.uid', $uid, '<>');
    $query->orderBy('c.created', 'DESC');

    $items = [];
    foreach ($query->execute()->fetchAllAssoc('cid') as $record) {
      $items[] = [
        'kind' => 'reply',
        'label' => ((int) $record->pid > 0 && (int) $record->parent_uid === $uid)
          ? $this->t('Reply to your comment:')
          : $this->t('Reply to your post:'),
        'timestamp' => (int) $record->created,
        'actor_uid' => (int) $record->uid,
        'actor_name' => (string) ($record->name ?? ''),
        'nid' => (int) $record->entity_id,
        'preview_text' => $this->sanitizePreview($record->preview_text ?? NULL),
        'action_url' => Url::fromUri('internal:/comment/reply/node/' . (int) $record->entity_id . '/comment_forum/' . (int) $record->cid)->toString(),
      ];
    }

    return $items;
  }

  /**
   * Queries reaction activity on forum posts owned by the given user.
   */
  private function queryReactionActivity(int $uid): array {
    if (!$this->hasVotingApiVoteTable()) {
      return [];
    }

    $query = $this->database->select('votingapi_vote', 'v');
    $query->fields('v', ['id', 'timestamp', 'user_id', 'entity_id']);
    $query->join('node_field_data', 'n', 'v.entity_id = n.nid');
    $query->condition('v.entity_type', 'node');
    $query->condition('n.type', 'forum_post');
    $query->condition('n.status', 1);
    $query->condition('n.uid', $uid);
    $query->condition('v.user_id', $uid, '<>');
    $query->orderBy('v.timestamp', 'DESC');

    $items = [];
    foreach ($query->execute()->fetchAllAssoc('id') as $record) {
      $items[] = [
        'kind' => 'reaction',
        'label' => $this->t('Someone liked your post:'),
        'timestamp' => (int) $record->timestamp,
        'actor_uid' => (int) $record->user_id,
        'actor_name' => '',
        'nid' => (int) $record->entity_id,
        'preview_text' => '',
        'action_url' => '',
      ];
    }

    return $items;
  }

  /**
   * Strips, decodes and truncates raw comment body text for previews.
   *
   * @param string|null $raw
   *   The raw comment body value.
   *
   * @return string
   *   The sanitized preview text (max 140 chars).
   */
  private function sanitizePreview(?string $raw): string {
    return Unicode::truncate(
      trim(Html::decodeEntities(strip_tags((string) ($raw ?? '')))),
      140,
      TRUE,
      TRUE,
    );
  }

  /**
   * Whether the votingapi_vote table exists.
   */
  private function hasVotingApiVoteTable(): bool {
    return $this->database->schema()->tableExists('votingapi_vote');
  }

}
