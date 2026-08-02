/**
 * @file
 * Shared CSRF token helper for Open Intranet Forum AJAX endpoints.
 *
 * Exposes Drupal.openintranetForumCsrf.getToken() returning a Promise<string>
 * that resolves with the current session's CSRF token. The token is fetched
 * once and cached for the lifetime of the page.
 *
 * Consumers use the resolved token as the `X-CSRF-Token` header on POST
 * requests to routes declared with `_csrf_request_header_token: 'TRUE'`.
 */
(function (Drupal) {
  'use strict';

  /** @type {Promise<string>|null} */
  let tokenPromise = null;

  Drupal.openintranetForumCsrf = {
    /**
     * Returns a promise resolving with the CSRF session token.
     *
     * @return {Promise<string>}
     */
    getToken() {
      if (tokenPromise === null) {
        tokenPromise = fetch(Drupal.url('session/token'), {
          credentials: 'same-origin',
        })
          .then((res) => res.text())
          .then((token) => token.trim());
      }
      return tokenPromise;
    },
  };
})(Drupal);
