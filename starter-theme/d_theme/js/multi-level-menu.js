/**
 * @file
 * Behaviors for the multi-level menu.
 */
(function ($, Drupal, once) {
  'use strict';

  const isMobile = () => window.innerWidth < 992;

  Drupal.behaviors.multiLevelMenu = {
    attach: function (context, settings) {
      // Handle column heights for desktop menu
      once('multiLevelMenuFix', '.mega-menu-wrapper.d-none.d-lg-block', context).forEach(function (wrapper) {
        const columns = wrapper.querySelectorAll('.mega-menu-column');

        function updateColumnHeights() {
          columns.forEach(column => column.style.height = 'auto');

          let maxHeight = 0;
          columns.forEach(column => {
            maxHeight = Math.max(maxHeight, column.scrollHeight);
          });

          columns.forEach(column => {
            column.style.minHeight = `${maxHeight}px`;
          });
        }

        updateColumnHeights();

        let resizeTimer;
        window.addEventListener('resize', function() {
          clearTimeout(resizeTimer);
          resizeTimer = setTimeout(updateColumnHeights, 100);
        });
      });

      // Handle desktop menu interactions
      once('desktopMenuHandlers', '.navbar-nav', context).forEach(function(nav) {
        if (!isMobile()) {
          // Remove any click handlers from level 1 items to allow default link behavior
          const level1Items = nav.querySelectorAll('.nav-item.menu-item--expanded > .nav-link');
          level1Items.forEach(item => {
            item.addEventListener('click', function(e) {
              // Let the link work normally
              //window.location.href = this.href;
            });
          });

          // Only prevent default on level 2+ items with dropdowns
          const level2Items = nav.querySelectorAll('.dropdown-item.menu-item--expanded');

          level2Items.forEach(item => {
            const toggleBtn = item.querySelector('.dropdown-toggle-btn');
            const level3Menu = item.querySelector('.level-3');

            if (toggleBtn && level3Menu) {
              // Handle toggle button click
              toggleBtn.addEventListener('click', function(e) {
                if (!isMobile()) {
                  e.preventDefault();
                  e.stopPropagation();

                  // Toggle current level 3 menu
                  item.classList.toggle('show-level3');
                }
              });
            }
          });

          // Close level 3 menus only when clicking outside
          document.addEventListener('click', function(e) {
            if (!isMobile()) {
              // Check if click target is within any menu item or mega menu wrapper
              const isMenuClick = e.target.closest('.navbar-nav') ||
                e.target.closest('.mega-menu-wrapper') ||
                e.target.closest('.dropdown-item') ||
                e.target.closest('.level-3');

              if (!isMenuClick) {
                nav.querySelectorAll('.show-level3').forEach(item => {
                  item.classList.remove('show-level3');
                });
              }
            }
          });
        }
      });

      // Handle mobile menu interactions
      once('mobileMenuHandlers', '.navbar-nav', context).forEach(function(nav) {
        // Hide all level 3 menus initially
        nav.querySelectorAll('.level-3').forEach(menu => {
          menu.style.display = 'none';
        });

        function toggleMobileSubmenu(item, show = null) {
          const navItem = item.closest('.nav-item, .menu-item--expanded');
          const submenu = navItem.querySelector('.dropdown-menu, .mega-menu-wrapper');
          if (submenu) {
            show = show === null ? !submenu.classList.contains('show') : show;
            submenu.classList.toggle('show', show);
            submenu.style.display = show ? 'block' : 'none';
            navItem.classList.toggle('show', show);

            // If we're hiding a level 1 item, also hide all its level 3 menus
            if (!show && item.classList.contains('nav-item')) {
              const level3Menus = item.querySelectorAll('.level-3');
              level3Menus.forEach(menu => {
                menu.style.display = 'none';
                const parentItem = menu.closest('.menu-item--expanded');
                if (parentItem) {
                  parentItem.classList.remove('show-level3');
                }
              });
            }
          }
        }

        function closeAllDropdowns() {
          if (!isMobile()) return;
          // Close all dropdowns
          nav.querySelectorAll('.nav-item, .dropdown-item.menu-item--expanded').forEach(item => {
            toggleMobileSubmenu(item, false);
          });
          // Hide all level 3 menus
          nav.querySelectorAll('.level-3').forEach(menu => {
            menu.style.display = 'none';
            const parentItem = menu.closest('.menu-item--expanded');
            if (parentItem) {
              parentItem.classList.remove('show-level3');
            }
          });
        }

        // Handle level 1 clicks on mobile
        nav.querySelectorAll('.nav-item.menu-item--expanded > a').forEach(link => {
          link.addEventListener('click', function(e) {
            if (!isMobile()) return;

            e.preventDefault();
            e.stopPropagation();

            const menuItem = this.parentElement;
            const isCurrentlyShown = menuItem.classList.contains('show');

            // Toggle current dropdown without closing others
            toggleMobileSubmenu(menuItem, !isCurrentlyShown);

            // Hide all level 3 menus within this dropdown when closing it
            if (isCurrentlyShown) {
              menuItem.querySelectorAll('.level-3').forEach(menu => {
                menu.style.display = 'none';
                const parentItem = menu.closest('.menu-item--expanded');
                if (parentItem) {
                  parentItem.classList.remove('show-level3');
                }
              });
            }
          });
        });

        // Handle level 2 clicks on mobile
        nav.querySelectorAll('.dropdown-item.menu-item--expanded .dropdown-toggle-btn').forEach(btn => {
          btn.addEventListener('click', function(e) {
            if (!isMobile()) return;

            e.preventDefault();
            e.stopPropagation();

            const menuItem = this.closest('.menu-item--expanded');
            const level3Menu = menuItem.querySelector('.level-3');

            if (level3Menu) {
              const isCurrentlyShown = menuItem.classList.contains('show-level3');

              // Toggle current level 3 menu
              menuItem.classList.toggle('show-level3', !isCurrentlyShown);
              level3Menu.style.display = isCurrentlyShown ? 'none' : 'block';
            }
          });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
          if (!isMobile()) return;
          if (!nav.contains(e.target)) {
            closeAllDropdowns();
          }
        });

        // Initial state
        closeAllDropdowns();
      });
    }
  };
})(jQuery, Drupal, once);
