/**
 * @file
 * Light-dismiss for the forum post owner kebab menu.
 *
 * The menu itself is a native <details>/<summary> disclosure (toggle + open
 * state come for free). This only adds the floating-dropdown niceties details
 * lacks: close the open menu on an outside click or Escape, returning focus to
 * the kebab on Escape.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Closes every open kebab menu except one to keep open.
   *
   * @param {?Element} keep
   *   A <details> to leave open (the one containing the click), or null.
   */
  function closeOthers(keep) {
    document
      .querySelectorAll('details.forum-post-meta__menu-wrap[open]')
      .forEach((menu) => {
        if (menu !== keep) {
          menu.open = false;
        }
      });
  }

  Drupal.behaviors.forumPostMenu = {
    attach(context) {
      once('forum-post-menu-doc', 'html', context).forEach((html) => {
        html.addEventListener('click', (event) => {
          closeOthers(event.target.closest('details.forum-post-meta__menu-wrap'));
        });

        html.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape') {
            return;
          }
          const open = document.querySelector('details.forum-post-meta__menu-wrap[open]');
          if (!open) {
            return;
          }
          open.open = false;
          const summary = open.querySelector('.forum-post-meta__menu-toggle');
          if (summary) {
            summary.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
