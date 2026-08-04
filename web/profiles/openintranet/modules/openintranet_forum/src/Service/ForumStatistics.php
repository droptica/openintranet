<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Service for forum statistics operations.
 */
final class ForumStatistics implements ForumStatisticsInterface {

  /**
   * Constructs a ForumStatistics object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getReplyCount(int $nid): int {
    $query = $this->database->select('comment_field_data', 'c');
    $query->condition('c.entity_id', $nid);
    $query->condition('c.entity_type', 'node');
    $query->condition('c.comment_type', 'forum_reply');
    $query->condition('c.status', 1);
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function updateReplyCount(int $nid): void {
    try {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if (!$node || $node->bundle() !== 'forum_post') {
        return;
      }

      if (!$node->hasField('field_forum_reply_count')) {
        return;
      }

      $node->set('field_forum_reply_count', $this->getReplyCount($nid));
      $node->save();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('openintranet_forum')->error('Failed to update reply count for node @nid: @message', [
        '@nid' => $nid,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCommentReactionCounts(int $commentId): array {
    if (!$this->isVotingApiAvailable() || $commentId < 1) {
      return ['up' => 0, 'down' => 0];
    }

    $query = $this->database->select('votingapi_vote', 'vv');
    $query->addExpression('SUM(CASE WHEN vv.value > 0 THEN 1 ELSE 0 END)', 'up_votes');
    $query->addExpression('SUM(CASE WHEN vv.value < 0 THEN 1 ELSE 0 END)', 'down_votes');
    $query->condition('vv.entity_type', 'comment');
    $query->condition('vv.entity_id', $commentId);
    $query->condition('vv.type', 'vote');

    $result = $query->execute()->fetchAssoc() ?: [];

    return [
      'up' => (int) ($result['up_votes'] ?? 0),
      'down' => (int) ($result['down_votes'] ?? 0),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCommentUserVote(int $commentId): ?string {
    if (!$this->isVotingApiAvailable() || $commentId < 1) {
      return NULL;
    }

    if ($this->currentUser->isAnonymous()) {
      return NULL;
    }

    $vote_storage = $this->entityTypeManager->getStorage('vote');
    $existing_ids = $vote_storage->getUserVotes(
      (int) $this->currentUser->id(),
      'vote',
      'comment',
      $commentId,
    );

    if (empty($existing_ids)) {
      return NULL;
    }

    /** @var \Drupal\votingapi\Entity\Vote $vote */
    $vote = $vote_storage->load(reset($existing_ids));
    $value = (int) ($vote->get('value')->value ?? 0);

    return $value > 0 ? 'up' : 'down';
  }

  /**
   * {@inheritdoc}
   */
  public function getVoteSumTotal(int $nid): int {
    if ($nid < 1 || !$this->isVotingApiAvailable()) {
      return 0;
    }

    $query = $this->voteSumQuery();
    $query->condition('vr.entity_id', $nid);

    return (int) $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getShareCount(int $nid): int {
    if ($nid < 1) {
      return 0;
    }

    $result = $this->database
      ->select('node__field_forum_share_count', 'f')
      ->fields('f', ['field_forum_share_count_value'])
      ->condition('entity_id', $nid)
      ->condition('bundle', 'forum_post')
      ->execute()
      ->fetchField();

    return (int) $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getReactionLikeCount(int $nid): int {
    if ($nid < 1 || !$this->isVotingApiAvailable()) {
      return 0;
    }

    return (int) $this->reactionLikeQuery($nid)->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function hasUserLiked(int $nid, int $uid): bool {
    if ($nid < 1 || $uid < 1 || !$this->isVotingApiAvailable()) {
      return FALSE;
    }

    $query = $this->reactionLikeQuery($nid);
    $query->condition('v.user_id', $uid);

    return (int) $query->execute()->fetchField() > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function loadVoteSumTotals(array $nodeIds): array {
    if ($nodeIds === [] || !$this->database->schema()->tableExists('votingapi_result')) {
      return [];
    }

    $query = $this->voteSumQuery();
    $query->addField('vr', 'entity_id');
    $query->condition('vr.entity_id', $nodeIds, 'IN');
    $query->groupBy('vr.entity_id');

    $totals = [];
    foreach ($query->execute()->fetchAll() as $record) {
      $totals[(int) $record->entity_id] = (int) $record->total;
    }

    return $totals;
  }

  /**
   * {@inheritdoc}
   */
  public function getForumPostAuthorOptions(): array {
    $author_ids = $this->database->select('node_field_data', 'n')
      ->fields('n', ['uid'])
      ->condition('n.type', 'forum_post')
      ->condition('n.status', 1)
      ->distinct()
      ->execute()
      ->fetchCol();

    if (!$author_ids) {
      return [];
    }

    /** @var \Drupal\user\UserInterface[] $users */
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($author_ids);
    $options = [];
    foreach ($users as $user) {
      $options[(int) $user->id()] = $user->getDisplayName();
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /**
   * Whether the VotingAPI module is installed.
   */
  private function isVotingApiAvailable(): bool {
    return $this->moduleHandler->moduleExists('votingapi');
  }

  /**
   * Base count query for reaction_like votes on a forum post node.
   *
   * @param int $nid
   *   The forum post node id.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query, to which callers may add further conditions.
   */
  private function reactionLikeQuery(int $nid): SelectInterface {
    $query = $this->database->select('votingapi_vote', 'v');
    $query->addExpression('COUNT(*)', 'cnt');
    $query->condition('v.entity_id', $nid);
    $query->condition('v.entity_type', 'node');
    $query->condition('v.type', 'reaction_like');

    return $query;
  }

  /**
   * Base vote_sum aggregate query over votingapi_result for node entities.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query (SUM aliased 'total'), to which callers add the
   *   entity_id condition and any grouping.
   */
  private function voteSumQuery(): SelectInterface {
    $query = $this->database->select('votingapi_result', 'vr');
    $query->addExpression('COALESCE(SUM(vr.value), 0)', 'total');
    $query->condition('vr.entity_type', 'node');
    $query->condition('vr.function', 'vote_sum');

    return $query;
  }

}
