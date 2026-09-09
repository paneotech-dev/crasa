/* CRASA Digital Anchor Ecosystem — the dialog opener.
 *
 * ONE JOB: open a <dialog> that is already in the document.
 *
 * The focus trap, the Escape key and the inert background are the
 * browser's, from showModal(). Reimplementing them is how a modal ends up
 * lettings Tab walk behind it.
 *
 * With this file absent or broken the dialog is an ordinary section of the
 * page -- see .a-modal under html:not(.has-js) -- and the button that would
 * have opened it is not drawn. Nothing here is the only way to read
 * anything.
 */
(function () {
  'use strict';

  if (typeof HTMLDialogElement !== 'function') return;

  document.addEventListener('click', function (event) {
    var opener = event.target.closest ? event.target.closest('[data-modal-open]') : null;

    if (opener) {
      var dialog = document.getElementById(opener.getAttribute('data-modal-open'));

      if (dialog && typeof dialog.showModal === 'function') {
        event.preventDefault();
        dialog.showModal();
      }

      return;
    }

    var closer = event.target.closest ? event.target.closest('[data-modal-close]') : null;

    if (closer) {
      var open = closer.closest('dialog');

      if (open) {
        event.preventDefault();
        open.close();
      }
    }
  });

  /* A click on the backdrop closes it. The dialog itself covers only its
     own box, so a click whose target is the dialog element is a click
     outside the panel. */
  document.addEventListener('click', function (event) {
    if (event.target instanceof HTMLDialogElement && event.target.open) {
      event.target.close();
    }
  });
})();
