/* Back office workflow (Day 2, fa).
 *
 * 1. Confirmation before a destructive action. Any form with data-confirm
 *    opens a <dialog> that says what happens. showModal() gives the focus
 *    trap, the inert background and Escape; focus moves to Cancel on open
 *    and returns to the button that asked on close. Without this file the
 *    form submits as before, and the server still checks everything.
 * 2. The new page address follows the English title until it is edited.
 */
(function () {
  'use strict';

  var dialog = null;
  var pendingForm = null;
  var returnTo = null;

  function build() {
    if (dialog || typeof HTMLDialogElement !== 'function') return dialog;
    var lang = document.documentElement.lang || 'en';
    var cancelText = { fr: 'Annuler', pt: 'Cancelar' }[lang.slice(0, 2)] || 'Cancel';

    dialog = document.createElement('dialog');
    dialog.className = 'aw-dialog';
    dialog.setAttribute('aria-labelledby', 'aw-dialog-title');
    dialog.setAttribute('aria-describedby', 'aw-dialog-body');
    dialog.innerHTML =
      '<div class="aw-dialog__inner">' +
      '<h2 id="aw-dialog-title"></h2>' +
      '<p id="aw-dialog-body"></p>' +
      '<div class="aw-dialog__actions">' +
      '<button type="button" class="a-btn a-btn--secondary" data-aw-cancel></button>' +
      '<button type="button" class="a-btn a-btn--danger" data-aw-confirm></button>' +
      '</div></div>';
    dialog.querySelector('[data-aw-cancel]').textContent = cancelText;
    document.body.appendChild(dialog);

    dialog.querySelector('[data-aw-cancel]').addEventListener('click', function () { dialog.close('cancel'); });
    dialog.querySelector('[data-aw-confirm]').addEventListener('click', function () {
      var form = pendingForm;
      dialog.close('confirm');
      if (form) {
        form.setAttribute('data-aw-confirmed', '1');
        if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
      }
    });
    dialog.addEventListener('close', function () {
      pendingForm = null;
      if (returnTo && typeof returnTo.focus === 'function') returnTo.focus();
      returnTo = null;
    });
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close('cancel');
    });
    return dialog;
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.matches || !form.matches('form[data-confirm]')) return;
    if (form.getAttribute('data-aw-confirmed') === '1') { form.removeAttribute('data-aw-confirmed'); return; }
    if (!build()) return;

    event.preventDefault();
    pendingForm = form;
    returnTo = event.submitter || form.querySelector('[type="submit"]') || document.activeElement;
    dialog.querySelector('#aw-dialog-title').textContent = form.getAttribute('data-confirm-title') || '';
    dialog.querySelector('#aw-dialog-body').textContent = form.getAttribute('data-confirm') || '';
    dialog.querySelector('[data-aw-confirm]').textContent = form.getAttribute('data-confirm-action') || (returnTo && returnTo.textContent.trim()) || 'OK';
    dialog.showModal();
    dialog.querySelector('[data-aw-cancel]').focus();
  });

  var title = document.querySelector('[data-title-source]');
  var slug = document.querySelector('[data-slug]');
  if (title && slug) {
    var touched = slug.value !== '';
    slug.addEventListener('input', function () { touched = true; });
    title.addEventListener('input', function () {
      if (touched) return;
      slug.value = title.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
    });
  }
})();
