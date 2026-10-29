/* CRASA: the homepage comparison, answered in place.
 *
 * The block is a GET form that works with this file absent: pressing the
 * button reloads the homepage at the block. This file asks the fragment
 * endpoint for the table alone whenever a selector changes, swaps it in,
 * sets the selectors to what the server resolved, keeps the address in step
 * (so the URL still opens on the same comparison) and puts the new summary
 * in the status line, which screen readers announce.
 *
 * Anything that goes wrong falls back to submitting the form.
 */
(function () {
  'use strict';

  if (!window.fetch || !window.URLSearchParams) return;

  var blocks = document.querySelectorAll('[data-home-compare]');
  Array.prototype.forEach.call(blocks, setUp);

  function setUp(block) {
    var form = block.querySelector('form');
    var target = block.querySelector('[data-cmp-target]');
    var status = block.querySelector('[role="status"]');
    var endpoint = block.getAttribute('data-fragment');
    if (!form || !target || !endpoint) return;

    var pending = null;

    form.addEventListener('change', function () { update(); });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      update();
    });

    // The button is redundant once every change applies itself. It goes
    // only now, after the listeners above are attached.
    Array.prototype.forEach.call(form.querySelectorAll('[data-cmp-submit]'), function (button) {
      button.hidden = true;
    });

    function query() {
      var params = new URLSearchParams();
      Array.prototype.forEach.call(form.querySelectorAll('select[name]'), function (select) {
        if (select.value) params.set(select.name, select.value);
      });
      return params;
    }

    function update() {
      var params = query();
      if (pending) pending.abort && pending.abort();
      pending = window.AbortController ? new AbortController() : null;

      target.setAttribute('aria-busy', 'true');

      fetch(endpoint + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
        signal: pending ? pending.signal : undefined
      })
        .then(function (response) {
          if (!response.ok) throw new Error('status ' + response.status);
          return response.text();
        })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var result = doc.querySelector('[data-cmp-result]');
          if (!result) throw new Error('no result');

          target.innerHTML = '';
          target.appendChild(document.importNode(result, true));
          target.removeAttribute('aria-busy');

          // The selectors follow what the table shows.
          setValue('cmp_indicator', result.getAttribute('data-indicator'));
          setValue('cmp_a', result.getAttribute('data-a'));
          setValue('cmp_b', result.getAttribute('data-b'));

          if (status) status.textContent = result.getAttribute('data-status') || '';

          if (window.history && window.history.replaceState) {
            var resolved = new URLSearchParams(window.location.search);
            resolved.set('cmp_indicator', result.getAttribute('data-indicator') || '');
            resolved.set('cmp_a', result.getAttribute('data-a') || '');
            resolved.set('cmp_b', result.getAttribute('data-b') || '');
            window.history.replaceState(window.history.state, '', window.location.pathname + '?' + resolved.toString() + '#' + block.closest('section').id);
          }
        })
        .catch(function (error) {
          if (error && error.name === 'AbortError') return;
          target.removeAttribute('aria-busy');
          form.submit();
        });
    }

    function setValue(name, value) {
      var select = form.querySelector('select[name="' + name + '"]');
      if (select && value && select.value !== value) select.value = value;
    }
  }
})();
