/**
 * @file
 * Client-side comment thread search + "See all comments" cap.
 *
 * The forum comment field renders top-level comments as flat siblings inside
 * `.forum-comments__list`: each top-level `<article.forum-comment>` is
 * optionally followed by a sibling `<div.indented>` holding its (recursive)
 * replies. A "thread" is therefore a top-level comment plus its following
 * `.indented` block.
 *
 * Features:
 * - Search: debounced, case-insensitive substring match against the whole
 *   thread's text (top-level comment + all replies). Non-matching threads are
 *   hidden; an empty query restores everything. A "no results" message shows
 *   when nothing matches.
 * - Cap: only the first N (data-cap, default 5) top-level threads are visible;
 *   a centered "See all comments" button reveals the rest. While a search is
 *   active the cap is ignored (all matches show); clearing the search re-applies
 *   the cap unless the user already expanded.
 */

(function (Drupal, once) {
  'use strict';

  const DEBOUNCE_MS = 200;

  /**
   * Collects the top-level threads inside a list wrapper.
   *
   * Each thread is { comment, replies } where replies may be null.
   *
   * @param {HTMLElement} list
   *   The `.forum-comments__list` element.
   *
   * @return {Array<{comment: HTMLElement, replies: ?HTMLElement}>}
   *   Ordered list of top-level threads.
   */
  function collectThreads(list) {
    const threads = [];
    Array.prototype.forEach.call(list.children, (child) => {
      if (child.classList && child.classList.contains('forum-comment')) {
        let replies = child.nextElementSibling;
        if (!replies || !replies.classList || !replies.classList.contains('indented')) {
          replies = null;
        }
        threads.push({ comment: child, replies: replies });
      }
    });
    return threads;
  }

  /**
   * Sets the visibility of a whole thread (comment + replies).
   */
  function setThreadHidden(thread, hidden) {
    thread.comment.style.display = hidden ? 'none' : '';
    if (thread.replies) {
      thread.replies.style.display = hidden ? 'none' : '';
    }
  }

  Drupal.behaviors.forumCommentSearch = {
    attach(context) {
      once('forum-comment-search', '.js-forum-comment-search', context).forEach((searchEl) => {
        const section = searchEl.closest('.forum-comments');
        if (!section) {
          return;
        }

        const input = searchEl.querySelector('.js-forum-comment-search-input');
        const list = section.querySelector('.js-forum-comment-thread');
        if (!input || !list) {
          return;
        }

        const emptyMsg = list.querySelector('.js-forum-comment-search-empty');
        const moreWrap = section.querySelector('.js-forum-comment-more');
        const seeAllBtn = section.querySelector('.js-forum-comment-see-all');
        const cap = parseInt(searchEl.getAttribute('data-cap'), 10) || 5;

        const threads = collectThreads(list);

        let expanded = false;

        /**
         * Applies the cap to all threads (only when no search is active).
         */
        const applyCap = () => {
          if (!moreWrap) {
            return;
          }
          if (expanded || threads.length <= cap) {
            threads.forEach((t) => setThreadHidden(t, false));
            moreWrap.hidden = true;
            return;
          }
          threads.forEach((t, i) => setThreadHidden(t, i >= cap));
          moreWrap.hidden = false;
        };

        /**
         * Filters threads by the current query. Ignores the cap entirely.
         */
        const applySearch = (query) => {
          let matches = 0;
          threads.forEach((thread) => {
            const text = (
              (thread.comment.textContent || '') +
              ' ' +
              (thread.replies ? thread.replies.textContent || '' : '')
            ).toLowerCase();
            const hit = text.indexOf(query) !== -1;
            setThreadHidden(thread, !hit);
            if (hit) {
              matches += 1;
            }
          });
          if (moreWrap) {
            moreWrap.hidden = true;
          }
          if (emptyMsg) {
            emptyMsg.hidden = matches !== 0;
          }
        };

        const run = () => {
          const query = input.value.trim().toLowerCase();
          if (emptyMsg) {
            emptyMsg.hidden = true;
          }
          if (query === '') {
            applyCap();
          } else {
            applySearch(query);
          }
        };

        input.addEventListener('input', Drupal.debounce(run, DEBOUNCE_MS));

        if (seeAllBtn && moreWrap) {
          seeAllBtn.addEventListener('click', () => {
            expanded = true;
            threads.forEach((t) => setThreadHidden(t, false));
            moreWrap.hidden = true;
          });
        }

        applyCap();
      });
    },
  };
})(Drupal, once);
