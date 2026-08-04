<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Form;

use Drupal\comment\CommentInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Confirmation form for soft-deleting a forum reply.
 *
 * Flags the reply as deleted instead of removing it so existing child replies
 * stay intact (Reddit-style tombstone).
 */
final class CommentSoftDeleteConfirmForm extends ConfirmFormBase {

  /**
   * The forum reply being soft-deleted.
   *
   * @var \Drupal\comment\CommentInterface|null
   */
  private ?CommentInterface $comment = NULL;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_forum_comment_soft_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CommentInterface $comment = NULL): array {
    $this->comment = $comment;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete this comment?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('The comment body will be replaced with a placeholder. Existing replies stay visible so the conversation thread is preserved.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    $node = $this->comment?->getCommentedEntity();
    return $node ? $node->toUrl() : Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->comment instanceof CommentInterface) {
      return;
    }

    if ($this->comment->hasField('field_forum_reply_soft_deleted')) {
      $this->comment->set('field_forum_reply_soft_deleted', TRUE);
    }

    if ($this->comment->hasField('comment_body')) {
      $this->comment->set('comment_body', [
        'value' => '',
        'format' => 'basic_html',
      ]);
    }

    if ($this->comment->hasField('field_forum_reply_images')) {
      $this->comment->set('field_forum_reply_images', []);
    }

    $this->comment->save();

    $this->messenger()->addStatus($this->t('Comment was deleted.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
