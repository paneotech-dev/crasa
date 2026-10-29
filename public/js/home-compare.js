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
    // Two shapes: the page block swaps one target; the home console
    // (partials/home/comparator) swaps a table slot and a chart slot so the
    // form between them keeps its focus.
    var target = block.querySelector('[data-cmp-target]');
    var slots = block.querySelectorAll('[data-cmp-slot]');
    var status = block.querySelector('[role="status"]');
    var endpoint = block.getAttribute('data-fragment');
    if (!form || (!target && !slots.length) || !endpoint) return;

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

      busy(true);

      var url = new URL(endpoint, window.location.href);
      params.forEach(function (value, key) { url.searchParams.set(key, value); });

      fetch(url.toString(), {
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

          if (target) {
            target.innerHTML = '';
            target.appendChild(document.importNode(result, true));
          }
          Array.prototype.forEach.call(slots, function (slot) {
            var part = result.querySelector('[data-cmp-part="' + slot.getAttribute('data-cmp-slot') + '"]');
            if (!part) throw new Error('no part');
            slot.innerHTML = '';
            slot.appendChild(document.importNode(part, true));
          });
          busy(false);

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
          busy(false);
          form.submit();
        });
    }

    function busy(on) {
      var regions = target ? [target] : Array.prototype.slice.call(slots);
      regions.forEach(function (r) { on ? r.setAttribute('aria-busy', 'true') : r.removeAttribute('aria-busy'); });
    }

    function setValue(name, value) {
      var select = form.querySelector('select[name="' + name + '"]');
      if (select && value && select.value !== value) select.value = value;
    }
  }
})();
