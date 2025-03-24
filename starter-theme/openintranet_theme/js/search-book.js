(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.bookMenuSearch = {
    attach: function (context, settings) {
      var $searchInput = $('#block-openintranet-theme-knowledge-base-book #search-book-menu', context);
      var $menuNavs = $('#block-openintranet-theme-knowledge-base-book .book-block-menu', context);

      $searchInput.on('input', Drupal.debounce(function () {
        var searchText = $searchInput.val().toLowerCase();

        $menuNavs.each(function () {
          var $menu = $(this);
          var $accordionItems = $menu.find('.accordion-item'); // Poprawka - zamiast .accordion-item wewnątrz każdej sekcji szukamy po całym menu

          if (searchText === '') {
            $accordionItems.show();
            $menu.find('.accordion-collapse').removeClass('show');
            return;
          }

          $accordionItems.each(function () {
            var $item = $(this);
            var $link = $item.find('a.nav-link'); // Szukamy linków w .accordion-item
            var text = $link.text().toLowerCase();
            var match = text.includes(searchText);

            if (match) {
              $item.show().parents('.accordion-item').show();
              $item.children('.accordion-collapse').addClass('show');
            } else {
              $item.hide();
            }
          });
        });
      }, 300));
    }
  };
})(jQuery, Drupal, once);
