/**
 * @file
 * Behaviors for dropdown menu interactions.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.dropdownMenu = {
    attach: function (context, settings) {
      once('dropdownMenuHandler', '.dropdown-item .menu-item-wrapper', context).forEach(function (dropdown) {
        const link = dropdown.querySelector('a.dropdown-toggle');
        const button = dropdown.querySelector('.dropdown-toggle-btn');

        if (!link || !button) {
          return;
        }

        if (link.getAttribute('href')) {
          return
        }

        link.addEventListener('click', function (event) {
          event.preventDefault();
          button.click();
        });
      });
    }
  };
})(Drupal, once);
