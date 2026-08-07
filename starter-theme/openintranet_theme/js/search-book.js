(function ($, Drupal, once) {
  'use strict';

  /**
   * Filter Knowledge Base book outlines by title.
   */
  Drupal.behaviors.bookMenuSearch = {
    attach: function (context) {
      once('book-menu-search', '#block-openintranet-theme-knowledge-base-book #search-book-menu', context).forEach(function (element) {
        var $searchInput = $(element);
        var $block = $searchInput.closest('#block-openintranet-theme-knowledge-base-book');
        var $menuNavs = $block.find('.book-block-menu');

        $searchInput.on('input', Drupal.debounce(function () {
          var searchText = $searchInput.val().toLowerCase().trim();

          $menuNavs.each(function () {
            var $menu = $(this);
            var $accordionItems = $menu.find('.accordion-item');
            var hasVisibleMatch = false;

            if (searchText === '') {
              $accordionItems.show();
              $menu.show();
              $menu.find('.accordion-collapse').each(function () {
                var $collapse = $(this);
                var keepOpen = $collapse.closest('.accordion-item').hasClass('active-trail');
                $collapse.toggleClass('show', keepOpen);
              });
              return;
            }

            $accordionItems.each(function () {
              var $item = $(this);
              var text = $item.children('.accordion-header').find('a.nav-link').first().text().toLowerCase();
              var match = text.indexOf(searchText) !== -1;
              var childMatch = $item.find('.accordion-item').filter(function () {
                var childText = $(this).children('.accordion-header').find('a.nav-link').first().text().toLowerCase();
                return childText.indexOf(searchText) !== -1;
              }).length > 0;

              if (match || childMatch) {
                $item.show().parents('.accordion-item').show();
                $item.children('.accordion-collapse').addClass('show');
                hasVisibleMatch = true;
              }
              else {
                $item.hide();
              }
            });

            $menu.toggle(hasVisibleMatch);
          });
        }, 300));
      });
    }
  };

  /**
   * Collapse the Knowledge Base outline on small screens so content comes first.
   */
  Drupal.behaviors.bookMenuMobileCollapse = {
    attach: function (context) {
      once('book-menu-mobile-collapse', '#block-openintranet-theme-knowledge-base-book', context).forEach(function (element) {
        var collapse = element.querySelector('#block-openintranet-theme-knowledge-base-book--collapse');
        var toggle = element.querySelector('.accordion-item-block > .accordion-header > .accordion-button');
        if (!collapse) {
          return;
        }

        var sync = function () {
          var isMobile = window.matchMedia('(max-width: 991.98px)').matches;
          if (isMobile) {
            collapse.classList.remove('show');
            if (toggle) {
              toggle.classList.add('collapsed');
              toggle.setAttribute('aria-expanded', 'false');
            }
          }
          else {
            collapse.classList.add('show');
            if (toggle) {
              toggle.classList.remove('collapsed');
              toggle.setAttribute('aria-expanded', 'true');
            }
          }
        };

        sync();
        window.addEventListener('resize', Drupal.debounce(sync, 150));
      });
    }
  };
})(jQuery, Drupal, once);
