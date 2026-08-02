/**
 * @file
 * Toggles the notification bell dropdown.
 *
 * The bell markup is theme-agnostic (no Bootstrap data-attributes), so the
 * dropdown is opened/closed here: clicking the toggle flips aria-expanded and
 * an is-open class; an outside click or Escape closes it.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.openintranetNotificationBell = {
    attach: function (context) {
      once('oi-notification-bell', '.notification-bell', context).forEach(function (bell) {
        var toggle = bell.querySelector('.notification-bell__toggle');
        if (toggle === null) {
          return;
        }

        var close = function () {
          bell.classList.remove('is-open');
          toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', function (event) {
          event.stopPropagation();
          var isOpen = bell.classList.toggle('is-open');
          toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function (event) {
          if (!bell.contains(event.target)) {
            close();
          }
        });

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            close();
          }
        });
      });
    }
  };
})(Drupal, once);
