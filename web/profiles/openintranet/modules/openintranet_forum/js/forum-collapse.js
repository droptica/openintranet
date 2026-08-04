/**
 * @file
 * Collapsible reply threads for forum comments.
 *
 * For each comment whose following sibling is an .indented container, inserts
 * a ± toggle below the comment footer.  Threads start collapsed; clicking
 * the toggle shows or hides the replies (and the rails inside .indented).
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Walks past non-comment siblings (e.g. named anchors) to find the next
   * meaningful element.
   */
  function nextStructuralSibling(el) {
    let n = el.nextElementSibling;
    while (
      n &&
      !n.classList.contains('indented') &&
      !n.classList.contains('forum-comment')
    ) {
      n = n.nextElementSibling;
    }
    return n;
  }

  // Maps each .indented replies container to its toggle button.
  const toggles = new WeakMap();

  /**
   * Both link shapes must be handled: core's /comment/37 permalink renders the
   * post in a subrequest, so it carries no fragment at all.
   */
  function targetCommentId() {
    const hash = window.location.hash;
    if (/^#comment-\d+$/.test(hash)) {
      return hash.slice(1);
    }
    const path = window.location.pathname.match(/(?:^|\/)comment\/(\d+)$/);
    return path ? 'comment-' + path[1] : null;
  }

  /**
   * Expands every collapsed thread above the linked comment and scrolls to it.
   */
  function revealLinkedComment() {
    const id = targetCommentId();
    if (!id) {
      return;
    }
    const target = document.getElementById(id);
    if (!target) {
      return;
    }
    let node = target.parentElement;
    while (node) {
      if (node.classList.contains('indented')) {
        const toggle = toggles.get(node);
        if (toggle && toggle.getAttribute('aria-expanded') !== 'true') {
          toggle.click();
        }
        // scrollIntoView also scrolls overflow:hidden ancestors, and .indented
        // has a huge scrollable area (the -9999px connector) — an internal
        // scrollTop shifts every reply up and leaves a blank band below.
        node.scrollTop = 0;
      }
      node = node.parentElement;
    }
    const rect = target.getBoundingClientRect();
    window.scrollTo({ top: rect.top + window.scrollY - window.innerHeight / 3 });
  }

  Drupal.behaviors.forumCommentCollapse = {
    attach(context) {
      once('forum-collapse', '.forum-comment', context).forEach((comment) => {
        const children = nextStructuralSibling(comment);
        if (!children || !children.classList.contains('indented')) {
          return;
        }

        const replyCount = children.querySelectorAll('.forum-comment').length;
        if (!replyCount) {
          return;
        }

        const collapsedLabel = Drupal.formatPlural(
          replyCount,
          '1 more reply',
          '@count more replies',
        );
        const expandedLabel = Drupal.t('Hide replies');

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'forum-comment__toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML =
          '<span class="forum-comment__toggle-icon" aria-hidden="true">+</span>' +
          '<span class="forum-comment__toggle-label"></span>';

        const iconEl = toggle.querySelector('.forum-comment__toggle-icon');
        const labelEl = toggle.querySelector('.forum-comment__toggle-label');

        const apply = (expanded) => {
          children.classList.toggle('forum-comment__children--open', expanded);
          toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          iconEl.textContent = expanded ? '−' : '+';
          labelEl.textContent = expanded ? expandedLabel : collapsedLabel;
        };

        toggle.addEventListener('click', () => {
          const currentlyExpanded = toggle.getAttribute('aria-expanded') === 'true';
          apply(!currentlyExpanded);
        });

        apply(false);
        toggles.set(children, toggle);

        const main = comment.querySelector(':scope > .forum-comment__main');
        (main || comment).appendChild(toggle);
      });

      once('forum-collapse-target', 'body', context).forEach(revealLinkedComment);
    },
  };
})(Drupal, once);
