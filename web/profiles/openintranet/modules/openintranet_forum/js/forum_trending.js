/**
 * @file
 * Horizontal scroll control for the forum trending carousel.
 *
 * Scrolls the card grid one viewport-width forward on each button click.
 * Hides the button when there is nothing more to scroll.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.openintranetForumTrending = {
    attach(context) {
      once('forum-trending-carousel', '.forum-trending__carousel', context).forEach((carousel) => {
        const viewport = carousel.querySelector('.forum-trending__viewport');
        const grid = carousel.querySelector('.views-view-responsive-grid');
        const nextBtn = carousel.querySelector('.forum-trending__next');
        const prevBtn = carousel.querySelector('.forum-trending__prev');

        if (!grid || !viewport) {
          return;
        }

        function remainingRight() {
          return grid.scrollWidth - grid.scrollLeft - grid.clientWidth;
        }

        function updateButtons() {
          if (nextBtn) {
            if (remainingRight() <= 2) {
              nextBtn.setAttribute('hidden', '');
            } else {
              nextBtn.removeAttribute('hidden');
            }
          }
          if (prevBtn) {
            if (grid.scrollLeft <= 2) {
              prevBtn.setAttribute('hidden', '');
            } else {
              prevBtn.removeAttribute('hidden');
            }
          }
        }

        if (nextBtn) {
          nextBtn.addEventListener('click', () => {
            grid.scrollBy({ left: viewport.clientWidth, behavior: 'smooth' });
          });
        }
        if (prevBtn) {
          prevBtn.addEventListener('click', () => {
            grid.scrollBy({ left: -viewport.clientWidth, behavior: 'smooth' });
          });
        }

        grid.addEventListener('scroll', updateButtons, { passive: true });
        updateButtons();
      });
    },
  };
}(Drupal, once));
