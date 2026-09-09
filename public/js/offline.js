/* The offline page's saved list, screen 15's "Manage saved items".
 *
 * With no script, or no service worker, the empty state on the page stands
 * and it is the truth: nothing has been saved because nothing could be.
 */
(function () {
  'use strict';

  var retry = document.querySelector('[data-offline-retry]');

  if (retry) {
    retry.addEventListener('click', function () { window.location.reload(); });
  }

  var list = document.querySelector('[data-saved-list]');
  var empty = document.querySelector('[data-saved-empty]');

  if (!list || !navigator.serviceWorker || !navigator.serviceWorker.controller) return;

  navigator.serviceWorker.addEventListener('message', function (event) {
    var data = event.data || {};

    if (data.type !== 'saved-list') return;

    if (!data.urls.length) return;

    if (empty) empty.remove();

    data.urls.forEach(function (url) {
      var item = document.createElement('li');
      item.className = 'c-saved__item';

      var link = document.createElement('a');
      link.href = url;
      // The filename, which is what the reader saved and recognises.
      link.textContent = decodeURIComponent(url.split('/').pop()) || url;
      item.appendChild(link);

      var forget = document.createElement('button');
      forget.type = 'button';
      forget.className = 'c-btn c-btn--secondary c-saved__forget';
      forget.textContent = list.getAttribute('data-forget-label') || 'Remove';
      forget.addEventListener('click', function () {
        navigator.serviceWorker.controller.postMessage({ type: 'forget-document', url: url });
        item.remove();
      });
      item.appendChild(forget);

      list.appendChild(item);
    });
  });

  navigator.serviceWorker.controller.postMessage({ type: 'list-saved' });
})();
