/**
 * @file
 * Like / dislike voting for forum comments.
 *
 * Intercepts clicks on .js-forum-comment-vote buttons, POSTs to the backend
 * AJAX endpoint, and updates the count + active state in the DOM.
 *
 * The backend requires the X-CSRF-Token header (route requirement
 * _csrf_request_header_token). Token sourcing is delegated to the shared
 * Drupal.openintranetForumCsrf helper from the forum.csrf library.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.forumCommentVote = {
    attach(context) {
      once('forum-comment-vote', '.js-forum-comment-vote', context).forEach(
        (btn) => {
          btn.addEventListener('click', () => {
            if (btn.dataset.inFlight) {
              return;
            }

            const commentId = btn.dataset.commentId;
            const direction = btn.dataset.direction;
            const url = btn.dataset.voteUrl;
            if (!commentId || !url || (direction !== 'up' && direction !== 'down')) {
              return;
            }

            const stats = btn.closest('.forum-comment__stats');
            const likeBtn = stats
              ? stats.querySelector('.forum-comment__stat--like')
              : null;
            const dislikeBtn = stats
              ? stats.querySelector('.forum-comment__stat--dislike')
              : null;

            const getLikeCount = () => {
              const el = likeBtn
                ? likeBtn.querySelector('.forum-comment__stat-count')
                : null;
              return el ? parseInt(el.textContent, 10) || 0 : 0;
            };
            const getDislikeCount = () => {
              const el = dislikeBtn
                ? dislikeBtn.querySelector('.forum-comment__stat-count')
                : null;
              return el ? parseInt(el.textContent, 10) || 0 : 0;
            };

            const wasActive = btn.getAttribute('aria-pressed') === 'true';
            const prevLikes = getLikeCount();
            const prevDislikes = getDislikeCount();

            applyVoteState(
              likeBtn,
              dislikeBtn,
              wasActive ? null : direction,
              prevLikes + (direction === 'up' ? (wasActive ? -1 : 1) : 0),
              prevDislikes + (direction === 'down' ? (wasActive ? -1 : 1) : 0),
            );

            btn.dataset.inFlight = '1';

            Drupal.openintranetForumCsrf.getToken()
              .then((token) =>
                fetch(url, {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token,
                  },
                  body: JSON.stringify({ direction }),
                }),
              )
              .then((res) => {
                if (!res.ok) {
                  throw new Error('Unable to save the vote.');
                }
                return res.json();
              })
              .then((data) => {
                if (typeof data.likes === 'undefined') {
                  return;
                }
                applyVoteState(
                  likeBtn,
                  dislikeBtn,
                  data.voted,
                  data.likes,
                  data.dislikes,
                );
              })
              .catch(() => {
                applyVoteState(
                  likeBtn,
                  dislikeBtn,
                  wasActive ? direction : null,
                  prevLikes,
                  prevDislikes,
                );
              })
              .finally(() => {
                delete btn.dataset.inFlight;
              });
          });
        },
      );
    },
  };

  /**
   * Updates the visual state of the two vote buttons.
   *
   * @param {Element|null} likeBtn
   * @param {Element|null} dislikeBtn
   * @param {string|null} voted  'up', 'down', or null.
   * @param {number} likes
   * @param {number} dislikes
   */
  function applyVoteState(likeBtn, dislikeBtn, voted, likes, dislikes) {
    if (likeBtn) {
      const countEl = likeBtn.querySelector('.forum-comment__stat-count');
      const active = voted === 'up';
      likeBtn.classList.toggle('forum-comment__stat--active', active);
      likeBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (countEl) {
        countEl.textContent = likes;
      }
    }
    if (dislikeBtn) {
      const countEl = dislikeBtn.querySelector('.forum-comment__stat-count');
      const active = voted === 'down';
      dislikeBtn.classList.toggle('forum-comment__stat--active', active);
      dislikeBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (countEl) {
        countEl.textContent = dislikes;
      }
    }
  }

})(Drupal, once);
