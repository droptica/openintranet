/**
 * @file
 * Per-user "like it!" / "liked!" label on the forum post reaction widget.
 *
 * Not config or a form alter: the label lives on the reaction_like vote type
 * (static config, cannot express per-user state), and votingapi_reaction's ajax
 * callback re-sets the option titles after build — so a form alter survives
 * only until the first vote.
 */

(function (Drupal, once) {
  'use strict';

  // Keyed on the radio, not the form: after an ajax vote the replaced form IS
  // the behaviour context, and once() only looks at its descendants.
  const RADIO = '.field--name-field-forum-post-reaction' +
    ' .votingapi-reaction-form input[type="radio"][value="reaction_like"]';

  Drupal.behaviors.forumReactionLabel = {
    attach(context) {
      once('forum-reaction-label', RADIO, context).forEach((radio) => {
        const form = radio.closest('.votingapi-reaction-form');
        if (!form || !radio.id) {
          return;
        }
        const label = form.querySelector(
          'label[for="' + CSS.escape(radio.id) + '"] .votingapi-reaction-label',
        );
        if (!label) {
          return;
        }
        label.textContent = radio.checked ? Drupal.t('liked!') : Drupal.t('like it!');
      });
    },
  };
})(Drupal, once);
