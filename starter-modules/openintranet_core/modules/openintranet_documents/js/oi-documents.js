/**
 * @file
 * Alpine.js components for Open Intranet Documents.
 */

(function () {
  'use strict';

  /**
   * Document browser component with view toggle.
   *
   * Uses Alpine.js $persist plugin to remember user's view preference.
   */
  document.addEventListener('alpine:init', () => {
    Alpine.data('documentBrowser', () => ({
      // View mode: 'list' or 'grid'
      viewMode: Alpine.$persist('list').as('oi_documents_view_mode'),

      setView(mode) {
        this.viewMode = mode;
      },

      isListView() {
        return this.viewMode === 'list';
      },

      isGridView() {
        return this.viewMode === 'grid';
      }
    }));
  });

})();
