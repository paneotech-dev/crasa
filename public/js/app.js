/* Registration, the install prompt, and saving a document for offline.
 * WS-02, screen 15.
 *
 * THE INSTALL PROMPT IS SHOWN ONCE, ON THE SECOND VISIT, AND NEVER OVER
 * CONTENT. Screen 15 says so in its own words: "Shown once, on the second
 * visit, never as an interstitial over content." A prompt on the first visit
 * asks a stranger to install something; a prompt over content is an
 * advertisement. Dismissing it is remembered and it does not come back.
 */
(function () {
  'use strict';

  if (!('serviceWorker' in navigator)) return;

  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {
      // A site that works is the floor. Everything here is on top of it.
    });
  });

  /* The install prompt ---------------------------------------------------- */

  var VISITS = 'crasa-visits';
  var DISMISSED = 'crasa-install-dismissed';

  function remember(key, value) {
    try { window.localStorage.setItem(key, value); } catch (e) { /* private mode */ }
  }

  function recall(key) {
    try { return window.localStorage.getItem(key); } catch (e) { return null; }
  }

  var visits = parseInt(recall(VISITS) || '0', 10) + 1;
  remember(VISITS, String(visits));

  var deferred = null;

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferred = event;

    // The second visit, not the first, and never again once dismissed.
    if (visits < 2 || recall(DISMISSED) === '1') return;

    var banner = document.querySelector('[data-install]');
    if (!banner) return;

    banner.hidden = false;

    var install = banner.querySelector('[data-install-accept]');
    var dismiss = banner.querySelector('[data-install-dismiss]');

    install && install.addEventListener('click', function () {
      banner.hidden = true;
      deferred.prompt();
      deferred = null;
    });

    dismiss && dismiss.addEventListener('click', function () {
      banner.hidden = true;
      remember(DISMISSED, '1');
    });
  });

  /* Save for offline ------------------------------------------------------ */

  var savers = document.querySelectorAll('[data-save-offline]');

  if (!savers.length) return;

  navigator.serviceWorker.ready.then(function () {
    if (!navigator.serviceWorker.controller) return;

    Array.prototype.forEach.call(savers, function (button) {
      // Shown only once a worker is in control. A button that does nothing
      // is worse than a button that is not there.
      button.hidden = false;

      button.addEventListener('click', function () {
        navigator.serviceWorker.controller.postMessage({
          type: 'save-document',
          url: button.getAttribute('data-save-offline')
        });

        button.disabled = true;
        button.textContent = button.getAttribute('data-saving-label') || button.textContent;
      });
    });

    navigator.serviceWorker.addEventListener('message', function (event) {
      var data = event.data || {};
      var button = document.querySelector('[data-save-offline="' + data.url + '"]');

      if (!button) return;

      if (data.type === 'save-done') {
        button.textContent = button.getAttribute('data-saved-label') || button.textContent;

        return;
      }

      if (data.type === 'save-refused' || data.type === 'save-failed') {
        // Refused means the worker would not cache it, which for a
        // document means it is above the reader's clearance or the server
        // marked it private. Say so rather than failing silently.
        button.disabled = false;
        button.textContent = button.getAttribute('data-refused-label') || button.textContent;
      }
    });
  });
})();
