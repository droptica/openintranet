(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.searchResults = {
    attach: function (context) {
      once('searchResults', '.search-result', context).forEach(function(result) {
        const url = result.getAttribute('data-url');
        
        result.style.cursor = 'pointer';
        
        result.addEventListener('click', function(e) {
          // Don't trigger if clicking on an existing link or interactive element
          if (!e.target.closest('a, button, input, select, textarea')) {
            window.location.href = url;
          }
        });
      });
    }
  };
})(Drupal, once); 