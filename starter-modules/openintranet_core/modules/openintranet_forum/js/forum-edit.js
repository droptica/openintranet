/**
 * @file
 * Inline comment edit form for forum posts.
 *
 * When an "Edit" action link is clicked, fetches the Drupal comment edit
 * form and swaps it in place of the comment body instead of navigating
 * to a separate page.  A "Cancel" button is injected to dismiss the form
 * and restore the original comment content.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.forumCommentEdit = {
    attach(context) {
      once('forum-edit', '.forum-comment__action--edit', context).forEach(
        (link) => {
          link.addEventListener('click', (e) => {
            e.preventDefault();

            const $link = $(link);
            const $comment = $link.closest('.forum-comment');
            const $main = $comment.find('.forum-comment__main').first();
            const url = $link.attr('href');

            if (!$main.length) {
              window.location.href = url;
              return;
            }

            const $existing = $comment.find('.forum-edit-inline');
            if ($existing.length) {
              $existing.find('textarea, input[type="text"]').first().trigger('focus');
              return;
            }

            $link.addClass('forum-comment__action--loading');

            $.get(url)
              .done((html) => {
                $link.removeClass('forum-comment__action--loading');

                const $page = $('<div>').html(html);
                const $form = $page.find('form.comment-form');

                if (!$form.length) {
                  window.location.href = url;
                  return;
                }

                // Hide, not remove: the hidden `fids`/alt inputs must still
                // submit or a text-only edit wipes the existing image. Inline
                // ajax cannot work in a `$.get`-fetched form.
                $form.find('.forum-comment-form__upload-field').hide();
                $form.find('.forum-comment-form__helper').remove();

                const manageLabel = Drupal.t('Manage images');
                const $manageLink = $('<a class="forum-edit-inline__manage-images"></a>')
                  .attr('href', url)
                  .text(manageLabel);

                const cancelLabel = Drupal.t('Cancel');
                const $cancelBtn = $('<button type="button" class="forum-edit-inline__cancel">' + cancelLabel + '</button>');
                const $wrapper = $('<div class="forum-edit-inline"></div>')
                  .append($cancelBtn)
                  .append($form)
                  .append($manageLink);

                $main.hide();
                $main.after($wrapper);

                $cancelBtn.on('click', () => {
                  $wrapper.remove();
                  $main.show();
                });

                Drupal.attachBehaviors($wrapper[0]);

                $wrapper[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                $wrapper.find('textarea, input[type="text"]').first().trigger('focus');
              })
              .fail(() => {
                $link.removeClass('forum-comment__action--loading');
                window.location.href = url;
              });
          });
        },
      );
    },
  };

})(jQuery, Drupal, once);
