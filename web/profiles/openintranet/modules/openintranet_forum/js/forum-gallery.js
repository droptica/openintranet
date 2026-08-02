/**
 * @file
 * Gallery lightbox for forum post full view (Bootstrap 5 Modal).
 *
 * Shared by the post gallery and every per-comment image gallery. Multiple
 * .js-forum-gallery containers can exist on one page, but they all share a
 * single #forumGalleryModal. Prev/next/keyboard handlers are bound once at
 * module scope, and each trigger click swaps the active URL list — so Next
 * inside a comment gallery never walks into the post gallery, and vice versa.
 */
(function (Drupal, once) {
  'use strict';

  let modalEl = null;
  let imgEl = null;
  let counter = null;
  let prevBtn = null;
  let nextBtn = null;
  let bsModal = null;
  let activeUrls = [];
  let activeIndex = 0;
  let listenersBound = false;

  function ensureModal() {
    if (modalEl) return modalEl;
    modalEl = document.getElementById('forumGalleryModal');
    if (!modalEl) return null;

    imgEl = modalEl.querySelector('.js-gallery-img');
    counter = modalEl.querySelector('.js-gallery-counter');
    prevBtn = modalEl.querySelector('.js-gallery-prev');
    nextBtn = modalEl.querySelector('.js-gallery-next');

    if (!listenersBound) {
      if (prevBtn) prevBtn.addEventListener('click', function () { show(activeIndex - 1); });
      if (nextBtn) nextBtn.addEventListener('click', function () { show(activeIndex + 1); });
      modalEl.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft')  show(activeIndex - 1);
        if (e.key === 'ArrowRight') show(activeIndex + 1);
      });
      listenersBound = true;
    }
    return modalEl;
  }

  function show(index) {
    if (!activeUrls.length) return;
    activeIndex = Math.max(0, Math.min(index, activeUrls.length - 1));
    if (imgEl) imgEl.src = activeUrls[activeIndex] || '';
    if (counter) counter.textContent = (activeIndex + 1) + ' / ' + activeUrls.length;
    if (prevBtn) prevBtn.disabled = activeIndex === 0;
    if (nextBtn) nextBtn.disabled = activeIndex === activeUrls.length - 1;
  }

  Drupal.behaviors.forumGallery = {
    attach(context) {
      once('forum-gallery', '.js-forum-gallery', context).forEach(function (gallery) {
        if (typeof bootstrap === 'undefined') return;
        if (!ensureModal()) return;

        let urls = [];
        const dataEl = gallery.querySelector('.js-gallery-data');
        if (dataEl) {
          try { urls = JSON.parse(dataEl.textContent); } catch (e) {}
        }

        gallery.querySelectorAll('.js-gallery-open').forEach(function (btn) {
          btn.addEventListener('click', function () {
            activeUrls = urls;
            if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
            show(parseInt(btn.dataset.galleryIndex, 10) || 0);
            bsModal.show();
          });
        });
      });
    },
  };
})(Drupal, once);
