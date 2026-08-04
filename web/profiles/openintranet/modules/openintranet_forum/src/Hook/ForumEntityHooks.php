<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\ForumEngagementTrackingTrait;
use Drupal\openintranet_forum\Service\ForumNotificationsInterface;
use Drupal\openintranet_forum\Service\ForumStatisticsInterface;
use Drupal\votingapi_reaction\Plugin\Field\FieldType\VotingApiReactionItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Entity lifecycle and form alter hook implementations for the forum module.
 */
final class ForumEntityHooks {

  use ForumEngagementTrackingTrait;
  use StringTranslationTrait;

  /**
   * Constructs a ForumEntityHooks object.
   *
   * @param \Drupal\openintranet_forum\Service\ForumStatisticsInterface $statistics
   *   The forum statistics service.
   * @param \Drupal\openintranet_forum\Service\ForumNotificationsInterface $notifications
   *   The forum notifications service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param object|null $engagementTracker
   *   An OiEngagementTrackerInterface instance, or NULL when the
   *   openintranet_engagement module is not installed. Typed as object so the
   *   container can inject via @?openintranet_engagement.tracker without
   *   requiring the contrib module to be present at compile time.
   */
  public function __construct(
    private readonly ForumStatisticsInterface $statistics,
    private readonly ForumNotificationsInterface $notifications,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly AccountProxyInterface $currentUser,
    private readonly RequestStack $requestStack,
    private readonly ?object $engagementTracker = NULL,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_presave() for node.
   *
   * VotingApiReactionItem::isEmpty() always returns FALSE, so the field is
   * always written. When the reaction-status widget is hidden (user lacks the
   * permission) no value is submitted and the status column is NULL, violating
   * NOT NULL. Default to OPEN so the insert never fails.
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->bundle() !== 'forum_post') {
      return;
    }

    if (!$node->hasField('field_forum_post_reaction')) {
      return;
    }

    $field = $node->get('field_forum_post_reaction');
    if ($field->isEmpty() || $field->status === NULL || $field->status === '') {
      $node->set('field_forum_post_reaction', ['status' => VotingApiReactionItemInterface::OPEN]);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for node.
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    if ($node->bundle() !== 'forum_post') {
      return;
    }

    $this->trackEngagement('forum_post_create', $node->getOwner(), [
      'value' => 10,
      'entity_type' => 'node',
      'entity_id' => $node->id(),
    ]);
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for node.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $node, $display, $view_mode): void {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'forum_post' || $view_mode !== 'full') {
      return;
    }

    if ($this->currentUser->id() == $node->getOwnerId()) {
      return;
    }

    $this->trackEngagement('forum_post_view', NULL, [
      'value' => 1,
      'entity_type' => 'node',
      'entity_id' => $node->id(),
    ]);
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for comment.
   */
  #[Hook('comment_insert')]
  public function commentInsert(CommentInterface $comment): void {
    $node = $this->forumReplyTarget($comment);
    if ($node === NULL) {
      return;
    }

    $this->statistics->updateReplyCount((int) $node->id());

    $this->trackEngagement('forum_reply_create', $comment->getOwner(), [
      'value' => 5,
      'entity_type' => 'comment',
      'entity_id' => $comment->id(),
    ]);

    $this->notifications->notifyFollowers(
      (int) $node->id(),
      'new_reply'
    );
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $node = $this->forumReplyTarget($entity);
    if ($node !== NULL) {
      $this->statistics->updateReplyCount((int) $node->id());
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $node = $this->forumReplyTarget($entity);
    if ($node !== NULL) {
      $this->statistics->updateReplyCount((int) $node->id());
    }
  }

  /**
   * Returns the forum_post node a forum reply comment is attached to.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to inspect.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The parent forum_post node, or NULL when $entity is not a forum reply on
   *   a forum post.
   */
  private function forumReplyTarget(EntityInterface $entity): ?NodeInterface {
    if ($entity->getEntityTypeId() !== 'comment' || $entity->bundle() !== 'forum_reply' || !$entity instanceof CommentInterface) {
      return NULL;
    }

    $node = $entity->getCommentedEntity();

    return $node instanceof NodeInterface && $node->bundle() === 'forum_post' ? $node : NULL;
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for views_exposed_form.
   *
   * Converts the Person filter from a username text input to a dropdown of
   * users who have authored at least one forum post.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {
    if (($form['#id'] ?? '') !== 'views-exposed-form-forum-search-page-1') {
      return;
    }
    if (!isset($form['person'])) {
      return;
    }

    $options = $this->statistics->getForumPostAuthorOptions();
    $default = '';
    $request = $this->requestStack->getCurrentRequest();
    $input = $request !== NULL ? $request->query->get('person') : NULL;
    if (is_scalar($input) && (string) $input !== '') {
      $default = (string) $input;
    }

    $form['person'] = [
      '#type' => 'select',
      '#title' => $form['person']['#title'] ?? $this->t('Person'),
      '#options' => ['' => (string) $this->t('- Any -')] + $options,
      '#default_value' => $default,
      '#weight' => $form['person']['#weight'] ?? 0,
      '#element_validate' => ['openintranet_forum_person_filter_validate'],
    ];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for forum replies.
   */
  #[Hook('form_comment_forum_reply_form_alter')]
  public function formCommentForumReplyFormAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!method_exists($form_object, 'getEntity')) {
      return;
    }

    $comment = $form_object->getEntity();
    if (!$comment instanceof CommentInterface) {
      return;
    }

    $node = $comment->getCommentedEntity();
    if (!$node instanceof NodeInterface || $node->bundle() !== 'forum_post') {
      return;
    }

    $form['#attributes']['class'][] = 'forum-comment-form';

    // Repointing the form action is not enough: a managed_file upload caches
    // the form state, and the final POST rebuilds the comment from that cache
    // (pid = NULL) rather than from the route.
    $form['forum_reply_pid'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => ['data-forum-reply-pid' => 'true'],
    ];
    $form['#entity_builders'][] = [self::class, 'setInlineReplyParent'];

    if (isset($form['subject'])) {
      $form['subject']['#access'] = FALSE;
    }

    if (isset($form['comment_body'][0]['value'])) {
      $form['comment_body'][0]['value']['#attributes']['placeholder'] = (string) $this->t('Add comment here');
    }

    if (isset($form['actions']['preview'])) {
      $form['actions']['preview']['#access'] = FALSE;
    }

    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = $this->t('Send');
      $form['actions']['submit']['#attributes']['class'][] = 'forum-comment-form__submit';
    }

    if (!isset($form['actions'])) {
      return;
    }

    $form['actions']['#attributes']['class'][] = 'forum-comment-form__actions';
    $actions = $form['actions'];
    unset($form['actions']);

    if (isset($form['field_forum_reply_images'])) {
      $form['field_forum_reply_images']['#weight'] = 85;
      $form['field_forum_reply_images']['#attributes']['class'][] = 'forum-comment-form__upload-field';
    }

    // forum-reply.js opens the file picker from here; the upload field's own
    // chrome is hidden in CSS, so this trigger is the only visible control.
    $helper = [
      '#type' => 'container',
      '#attributes' => ['class' => ['forum-comment-form__helper']],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => (string) $this->t('Add Image to your comment'),
        '#attributes' => ['class' => ['forum-comment-form__helper-text']],
      ],
    ];

    $form['forum_footer'] = [
      '#type' => 'container',
      '#weight' => 90,
      '#attributes' => ['class' => ['forum-comment-form__footer']],
      'helper' => $helper,
      'actions' => $actions,
    ];
  }

  /**
   * Entity builder that applies the inline-reply parent comment id.
   *
   * Guarded so it can never re-parent an edit or graft a reply onto a comment
   * from a different entity or field.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment being built.
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function setInlineReplyParent(string $entity_type, CommentInterface $comment, array &$form, FormStateInterface $form_state): void {
    if (!$comment->isNew()) {
      return;
    }

    $raw = $form_state->getValue('forum_reply_pid');
    if (!is_numeric($raw) || (int) $raw <= 0) {
      return;
    }
    $pid = (int) $raw;

    $parent = \Drupal::entityTypeManager()->getStorage('comment')->load($pid);
    if (!$parent instanceof CommentInterface || !$parent->isPublished()) {
      return;
    }

    // Only graft within the same entity + field, never across posts.
    if ((string) $parent->getCommentedEntityId() !== (string) $comment->getCommentedEntityId()
      || $parent->getCommentedEntityTypeId() !== $comment->getCommentedEntityTypeId()
      || $parent->getFieldName() !== $comment->getFieldName()) {
      return;
    }

    $comment->set('pid', $pid);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for the forum post add/edit forms.
   *
   * Gives regular authors a focused single-column form while preserving the
   * full revision interface for content administrators.
   */
  #[Hook('form_node_forum_post_form_alter')]
  #[Hook('form_node_forum_post_edit_form_alter')]
  public function formNodeForumPostFormAlter(array &$form): void {
    $form['#attributes']['class'][] = 'forum-post-form';
    $form['#attached']['library'][] = 'openintranet_forum/forum.post_form';

    if (!$this->currentUser->hasPermission('administer nodes')) {
      $is_edit = ($form['#form_id'] ?? '') === 'node_forum_post_edit_form';
      $form['#attributes']['class'][] = 'forum-post-form--single-column';
      $form['forum_post_form_header'] = [
        '#type' => 'container',
        '#weight' => -100,
        '#attributes' => [
          'class' => ['forum-post-form__header', 'mb-4'],
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#value' => $is_edit
            ? $this->t('Edit forum post')
            : $this->t('Create a forum post'),
          '#attributes' => [
            'class' => ['fs-2', 'fw-bold', 'mb-2'],
          ],
        ],
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $is_edit
            ? $this->t('Update the discussion details below.')
            : $this->t('Share a question, idea, or update with your colleagues.'),
          '#attributes' => [
            'class' => ['text-muted', 'mb-0'],
          ],
        ],
      ];

      if (isset($form['revision_information'])) {
        $form['revision_information']['#access'] = FALSE;
      }
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] = $is_edit
          ? $this->t('Save changes')
          : $this->t('Publish post');
      }
      if (isset($form['actions']['preview'])) {
        $form['actions']['preview']['#attributes']['class'][] = 'btn-outline-secondary';
      }
    }
  }

}
