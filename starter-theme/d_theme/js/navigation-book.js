(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.contentNavigation = {
    attach: function (context, settings) {
      var $contentDiv = $('.field--name-body', context);
      var $navDiv = $('#accordionItemNavigation', context);

      if (!$contentDiv.length || !$navDiv.length) {
        return;
      }

      $navDiv.html('');
      var $list = $('<ul></ul>');

      $contentDiv.find('h2, h3, h4').each(function () {
        var $header = $(this);
        var headerText = $header.text().trim();

        if (!headerText) {
          return;
        }

        if (!$header.attr('id')) {
          $header.attr('id', 'header-' + Math.random().toString(36).substr(2, 9));
        }

        var $link = $('<a></a>', {
          href: '#' + $header.attr('id'),
          text: headerText,
          click: function (event) {
            event.preventDefault();

            $('#accordionItemNavigation a').removeClass('active');
            $(this).addClass('active');

            $('html, body').animate({ scrollTop: $header.offset().top }, 'smooth');
          }
        });

        var $listItem = $('<li></li>', {
          class: 'accordion-item-navigation__' + $header.prop('tagName').toLowerCase()
        }).append($link);

        $list.append($listItem);
      });

      $navDiv.append($list);
    }
  };
})(jQuery, Drupal, once);
