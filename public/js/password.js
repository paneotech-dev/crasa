/* Show password. Added 14 October 2026, after three sign-ins in the
 * presentation failed on a mistyped password that nobody could see.
 *
 * Every password field gets an eye button that switches it between hidden
 * and shown. Without script the field is a plain password field, as before.
 * The labels come from the page (meta crasa-pw-show / crasa-pw-hide), so they
 * are in the reader's language.
 */
(function () {
  'use strict';

  function meta(name, fallback) {
    var el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') : fallback;
  }

  var SHOW = meta('crasa-pw-show', 'Show password');
  var HIDE = meta('crasa-pw-hide', 'Hide password');
  var EYE = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-7-11-7a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19"/><path d="M1 1l22 22"/></svg>';

  function enhance(input) {
    if (input.dataset.pwEnhanced) return;
    input.dataset.pwEnhanced = '1';

    var wrap = document.createElement('span');
    wrap.className = 'c-pw';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'c-pw__toggle';
    button.setAttribute('aria-controls', input.id || '');
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-label', SHOW);
    button.title = SHOW;
    button.innerHTML = EYE;
    wrap.appendChild(button);

    button.addEventListener('click', function () {
      var shown = input.type === 'text';
      input.type = shown ? 'password' : 'text';
      button.setAttribute('aria-pressed', shown ? 'false' : 'true');
      button.setAttribute('aria-label', shown ? SHOW : HIDE);
      button.title = shown ? SHOW : HIDE;
      button.innerHTML = shown ? EYE : EYE_OFF;
      input.focus();
    });

    // Never submit a password field as plain text: put it back first.
    if (input.form) {
      input.form.addEventListener('submit', function () { input.type = 'password'; });
    }
  }

  function run() {
    Array.prototype.forEach.call(document.querySelectorAll('input[type="password"]'), enhance);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
