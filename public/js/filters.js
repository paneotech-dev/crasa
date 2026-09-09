/* CRASA Digital Anchor Ecosystem — filters that answer without reloading.
 *
 * THE FLOOR IS THE PAGE. Every filter on this platform is a link or a form
 * that works with this file absent, blocked or broken. Nothing below draws
 * a control, and nothing below is the only way to reach a result. Turn
 * JavaScript off and the publications library, the newsroom, the search
 * results and the member states map all still filter, still paginate and
 * still deep-link. This file removes the page flash between one answer and
 * the next.
 *
 * The contract is in the markup:
 *
 *   [data-live-filters]              the region that owns the exchange
 *     data-live-swap="#a,#b"         elements replaced from the response
 *     data-live-status="#count"      element whose text is re-announced
 *
 * Anything inside that is a link or a form control drives it. A response
 * that does not arrive, or arrives without the regions, falls through to an
 * ordinary navigation rather than leaving the reader on a stale page.
 */
(function () {
  'use strict';

  var regions = document.querySelectorAll('[data-live-filters]');
  if (!regions.length || !window.fetch || !window.history || !window.history.pushState) return;

  Array.prototype.forEach.call(regions, function (region) {
    setUp(region);
  });

  function setUp(region) {
    var swap = (region.getAttribute('data-live-swap') || '').split(',').filter(Boolean);
    var statusSelector = region.getAttribute('data-live-status');
    if (!swap.length) return;

    var busy = false;

    region.addEventListener('click', function (event) {
      var link = event.target.closest ? event.target.closest('a[href]') : null;
      if (!link || !region.contains(link)) return;
      if (link.hasAttribute('data-live-skip')) return;

      // A modified click is the reader asking for a new tab or a download,
      // and it is not ours to intercept.
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
      if (link.target && link.target !== '_self') return;
      if (!sameOrigin(link.href)) return;

      event.preventDefault();
      go(link.href);
    });

    // Selects and checkboxes inside the region apply as they change. The
    // submit button they sit beside is removed only once this listener is
    // attached, so a script that fails leaves a working form behind.
    region.addEventListener('change', function (event) {
      var form = event.target.form;
      if (!form || !region.contains(form)) return;

      event.preventDefault();
      go(urlFor(form));
    });

    region.addEventListener('submit', function (event) {
      var form = event.target;
      if (!region.contains(form)) return;

      event.preventDefault();
      go(urlFor(form));
    });

    function urlFor(form) {
      var action = form.getAttribute('action') || window.location.href;
      var query = new URLSearchParams(new FormData(form));

      // An empty value means "no filter" and should leave the URL rather
      // than sit in it as ?indicator= — a reader pastes these into email.
      Array.prototype.slice.call(query.keys()).forEach(function (key) {
        if (query.get(key) === '') query.delete(key);
      });

      var string = query.toString();

      return action.split('?')[0] + (string ? '?' + string : '');
    }

    function go(url, replace) {
      if (busy) return;
      busy = true;

      swap.forEach(function (selector) {
        var node = document.querySelector(selector);
        if (node) node.setAttribute('aria-busy', 'true');
      });

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (response) {
          if (!response.ok) throw new Error('status ' + response.status);
          return response.text();
        })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var replaced = 0;

          swap.forEach(function (selector) {
            var next = doc.querySelector(selector);
            var current = document.querySelector(selector);
            if (!next || !current) return;

            current.innerHTML = next.innerHTML;
            current.removeAttribute('aria-busy');
            replaced++;
          });

          if (!replaced) throw new Error('nothing to swap');

          // A swapped region brings back its own apply button, and the
          // sidebar is inside a swapped region. Removing them once at
          // start-up leaves the second filter click with a button the
          // script has already made redundant.
          dropSubmits();

          if (!replace) window.history.pushState({ live: true }, '', url);
          else window.history.replaceState({ live: true }, '', url);

          if (doc.title) document.title = doc.title;

          announce(doc);
          busy = false;
        })
        .catch(function () {
          // Whatever went wrong, the reader still gets the page they asked
          // for. A stale list with a spinner on it is the worse outcome.
          window.location.href = url;
        });
    }

    /*
     * The count is a role="status" that was in the document before the
     * exchange started, so replacing its text announces it. Rebuilding the
     * element instead would give the reader silence, which is the usual way
     * an "instant" filter locks a screen reader out of its own results.
     */
    function announce(doc) {
      if (!statusSelector) return;

      var current = document.querySelector(statusSelector);
      var next = doc.querySelector(statusSelector);
      if (!current || !next) return;

      current.textContent = next.textContent.trim();
    }

    /*
     * The apply buttons beside the selects are now redundant, and they go
     * only here — after every listener above is attached. A script that
     * threw on the way down leaves the form and its button intact.
     */
    dropSubmits();

    function dropSubmits() {
      Array.prototype.forEach.call(region.querySelectorAll('[data-live-submit]'), function (button) {
        button.remove();
      });
    }

    window.addEventListener('popstate', function () {
      if (busy) return;
      go(window.location.href, true);
    });
  }

  function sameOrigin(href) {
    var link = document.createElement('a');
    link.href = href;

    return link.origin === window.location.origin;
  }
})();
