/**
 * @file
 * Inline comment reply form for forum posts.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Opens the hidden upload field's file input from the helper button.
   */
  Drupal.behaviors.forumCommentUploadTrigger = {
    attach(context) {
      once('forum-upload-trigger', '.forum-comment-form__helper', context).forEach(
        (helper) => {
          helper.addEventListener('click', () => {
            const form = helper.closest('form');
            if (!form) {
              return;
            }
            const fileInput = form.querySelector(
              '.forum-comment-form__upload-field input[type="file"]',
            );
            if (fileInput) {
              fileInput.click();
            }
          });
        },
      );
    },
  };

  /**
   * Core prepends managed_file messages into the widget element, which this
   * module hides until a thumbnail exists — so relocate them out.
   */
  Drupal.behaviors.forumCommentUploadMessages = {
    attach(context) {
      // Relocated messages sit outside the form's lifecycle: drop the previous
      // one or a stale error survives a later success and a second rejection
      // stacks. Gated to the widget's own ajax; others wipe unread rejections.
      if (
        context.closest &&
        context.closest('.forum-comment-form__upload-field')
      ) {
        document.body
          .querySelectorAll(':scope > [data-once~="forum-upload-messages"]')
          .forEach((stale) => stale.remove());
      }

      const selector =
        '.forum-comment-form__upload-field .toast-container,' +
        '.forum-comment-form__upload-field .messages';
      once('forum-upload-messages', selector, context).forEach((el) => {
        document.body.appendChild(el);
        // Once dismissed or auto-hidden the bare .toast must leave the DOM, or
        // the theme's toast behavior re-shows it on every later attach.
        el.addEventListener('hidden.bs.toast', () => el.remove());
      });
    },
  };

  Drupal.behaviors.forumCommentReply = {
    attach(context) {
      // The form is moved, not fetched: a `$.get`-fetched form's
      // drupalSettings.ajax never merges, so managed_file upload is dead.
      const state = Drupal.behaviors.forumCommentReply._state || (
        Drupal.behaviors.forumCommentReply._state = {
          form: null,
          placeholder: null,
          originalAction: null,
          wrapper: null,
          homeDraft: null,
        }
      );

      const resolveForm = () => {
        if (state.form) {
          return state.form;
        }
        const form = document.querySelector(
          '.forum-comments__add-comment form.comment-form',
        );
        if (!form) {
          return null;
        }
        state.form = form;
        state.originalAction = form.getAttribute('action');
        state.placeholder = document.createElement('span');
        state.placeholder.className = 'forum-reply-inline__placeholder';
        state.placeholder.hidden = true;
        form.parentNode.insertBefore(state.placeholder, form);
        return form;
      };

      // The server entity builder needs the hidden pid: a managed_file upload
      // caches the form state with pid = NULL.
      const setReplyPid = (value) => {
        const input = state.form && state.form.querySelector('input[name="forum_reply_pid"]');
        if (input) {
          input.value = value;
        }
      };

      const restoreForm = () => {
        if (!state.wrapper) {
          return;
        }
        if (state.form && state.placeholder) {
          state.placeholder.parentNode.insertBefore(state.form, state.placeholder.nextSibling);
          state.form.setAttribute('action', state.originalAction);
          setReplyPid('');
          // Cancel discards the reply draft: restore the body the top-level
          // form held before the move (the moved node keeps field state).
          const body = state.form.querySelector('textarea');
          if (body && state.homeDraft !== null) {
            body.value = state.homeDraft;
          }
          state.homeDraft = null;
          state.placeholder
            .closest('.forum-comments__add-comment')
            ?.classList.remove('forum-comments__add-comment--form-away');
        }
        state.wrapper.remove();
        state.wrapper = null;
      };

      once('forum-reply', '.forum-comment__action--reply', context).forEach(
        (link) => {
          link.addEventListener('click', (e) => {
            e.preventDefault();

            const $link = $(link);
            const $comment = $link.closest('.forum-comment');
            const url = $link.attr('href');

            const form = resolveForm();
            if (!form) {
              window.location.href = url;
              return;
            }

            if (state.wrapper && $comment[0].contains(state.wrapper)) {
              $(state.wrapper).find('textarea, input[type="text"]').first().trigger('focus');
              return;
            }

            const previousWrapper = state.wrapper;

            if (!previousWrapper) {
              const body = form.querySelector('textarea');
              state.homeDraft = body ? body.value : null;
              state.placeholder
                .closest('.forum-comments__add-comment')
                ?.classList.add('forum-comments__add-comment--form-away');
            }

            const cancelLabel = Drupal.t('Cancel');
            const $cancelBtn = $('<button type="button" class="forum-reply-inline__cancel">' + cancelLabel + '</button>');
            const $wrapper = $('<div class="forum-reply-inline"></div>')
              .append($cancelBtn)
              .append(form);

            form.setAttribute('action', url);
            const cidMatch = url.replace(/[?#].*$/, '').match(/(\d+)$/);
            setReplyPid(cidMatch ? cidMatch[1] : '');
            $comment.find('.forum-comment__main').first().after($wrapper);

            state.wrapper = $wrapper[0];
            $cancelBtn.on('click', restoreForm);

            if (previousWrapper) {
              previousWrapper.remove();
            }

            // Window-level scroll only: scrollIntoView would also scroll the
            // overflow:hidden .indented ancestors (see forum-collapse.js).
            const wrapperRect = $wrapper[0].getBoundingClientRect();
            if (wrapperRect.bottom > window.innerHeight || wrapperRect.top < 0) {
              window.scrollTo({
                top: wrapperRect.top + window.scrollY - window.innerHeight / 3,
                behavior: 'smooth',
              });
            }
            $wrapper.find('textarea, input[type="text"]').first().trigger('focus');
          });
        },
      );
    },
  };

})(jQuery, Drupal, once);
