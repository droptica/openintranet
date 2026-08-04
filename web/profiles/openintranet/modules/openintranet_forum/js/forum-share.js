/**
 * @file
 * Copies a share URL to the clipboard and shows a brief "Copied!" label.
 *
 * The counter POST fires only when `data-nid` is present; comment share buttons
 * carry a `data-url` but no `data-nid`, so they never hit the post endpoint.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Sets a button's share count, or defers it while "Copied!" is showing.
   */
  function setShareCount(btn, count) {
    const countEl = btn.querySelector('.forum-post__share-count, .forum-trending-card__stat-value');
    if (countEl) {
      countEl.textContent = count;
    }
    else {
      btn.dataset.pendingShareCount = String(count);
    }
  }

  /**
   * Updates every share count element for the given nid.
   */
  function updateShareCounts(nid, count) {
    document.querySelectorAll('.js-forum-share[data-nid="' + nid + '"]').forEach((btn) => {
      setShareCount(btn, count);
    });
  }

  /**
   * Restores a button's original content after the "Copied!" confirmation.
   */
  function restoreButton(btn) {
    if (typeof btn.dataset.originalHtml !== 'undefined') {
      btn.innerHTML = btn.dataset.originalHtml;
      delete btn.dataset.originalHtml;
    }
    btn.classList.remove('forum-post__share-btn--copied');
    delete btn.dataset.copyTimer;

    // Apply any count that arrived while the label was showing.
    if (typeof btn.dataset.pendingShareCount !== 'undefined') {
      setShareCount(btn, btn.dataset.pendingShareCount);
      delete btn.dataset.pendingShareCount;
    }
  }

  /**
   * Swaps the button label to "Copied!" for ~2s, then restores it.
   */
  function showCopiedFeedback(btn) {
    if (typeof btn.dataset.copyTimer !== 'undefined') {
      // Already showing: reset the timer, keep the stored original content.
      clearTimeout(Number(btn.dataset.copyTimer));
    }
    else {
      btn.dataset.originalHtml = btn.innerHTML;
      btn.classList.add('forum-post__share-btn--copied');
      btn.innerHTML = '<span class="forum-share-copied">' + Drupal.t('Copied!') + '</span>';
    }

    const timer = setTimeout(() => {
      restoreButton(btn);
    }, 2000);
    btn.dataset.copyTimer = String(timer);

    Drupal.announce(Drupal.t('Link copied to clipboard'));
  }

  Drupal.behaviors.forumShare = {
    attach(context) {
      once('forum-share', '.js-forum-share[data-url]', context).forEach((btn) => {
        btn.addEventListener('click', () => {
          const shareUrl = btn.dataset.url;
          if (!shareUrl) {
            return;
          }

          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shareUrl).catch(() => {});
          }

          showCopiedFeedback(btn);

          // Only forum posts carry a nid and increment the share counter.
          const nid = btn.dataset.nid;
          const endpoint = btn.dataset.shareUrl;
          if (!nid || !endpoint || btn.dataset.inFlight) {
            return;
          }
          btn.dataset.inFlight = '1';

          Drupal.openintranetForumCsrf.getToken()
            .then((token) =>
              fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': token,
                },
              }),
            )
            .then((res) => {
              if (!res.ok) {
                return null;
              }
              return res.json();
            })
            .then((data) => {
              if (data && typeof data.count !== 'undefined') {
                updateShareCounts(nid, data.count);
              }
            })
            .catch(() => {})
            .finally(() => {
              delete btn.dataset.inFlight;
            });
        });
      });
    },
  };

})(Drupal, once);
