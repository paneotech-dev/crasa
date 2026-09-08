/* CRASA Digital Anchor Ecosystem — predictive suggestions.
 *
 * Fills a native <datalist>. No custom dropdown, so there is no keyboard
 * trap to get wrong, no ARIA combobox to implement incorrectly, and the
 * field works exactly as well with this file absent.
 */
(function () {
  'use strict';

  var input = document.querySelector('[data-search-input]');
  var list = document.querySelector('[data-suggestions]');
  if (!input || !list) return;

  var timer = null;
  var lastQuery = '';

  input.addEventListener('input', function () {
    var value = input.value.trim();

    if (value.length < 2 || value === lastQuery) return;

    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      lastQuery = value;

      fetch(input.getAttribute('data-suggest-url') + '?q=' + encodeURIComponent(value), {
        headers: { Accept: 'application/json' }
      })
        .then(function (response) { return response.ok ? response.json() : { suggestions: [] }; })
        .then(function (data) {
          list.innerHTML = '';
          (data.suggestions || []).forEach(function (suggestion) {
            var option = document.createElement('option');
            option.value = suggestion;
            list.appendChild(option);
          });
        })
        .catch(function () { /* Suggestions are a convenience, not the feature. */ });
    }, 180);
  });
})();
