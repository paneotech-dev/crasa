/* CRASA regulatory comparator — the React layer.
 *
 * WHAT THIS DOES, AND WHAT IT DELIBERATELY DOES NOT.
 *
 * The comparator is already complete before this file runs. Every control
 * is a link, every state has a URL, and the server renders the whole
 * comparison. That is not a fallback: it is the version that prints, the
 * version a screen reader gets, and the version that makes a pasted
 * permalink resolve to figures rather than to a spinner.
 *
 * This file makes selection feel immediate. It intercepts the links,
 * fetches the page the link names, swaps the toolbar, the member state list
 * and the result in place, and pushes the new URL, so the address bar still
 * describes what is on screen. If it never loads, nothing is lost but the
 * speed.
 *
 * ONE RENDERING PATH. Until 17 September 2026 this drew its own table from
 * the JSON endpoint and left the rest of the page as it was. The member
 * state list, the "n of 4" count and the export links then described the
 * previous selection: adding a third state replaced the first two, and
 * Export PDF exported what had been on screen before. The server's markup
 * is now the only markup, so every region always describes the same
 * selection. A change of mode, or a move to or from the empty state, is a
 * full page load, because the page around the result changes too.
 *
 * React 18, per the stack. No JSX and no build step: the platform serves
 * static CSS and Blade, and a bundler for one component would be a second
 * toolchain to keep in step with the design.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-comparator]');
  if (!root || !window.React || !window.ReactDOM || !window.DOMParser) return;

  var h = window.React.createElement;
  var useEffect = window.React.useEffect;
  var container = root.closest('.c-cmp');
  if (!container) return;

  var modeOf = function (url) {
    return new URL(url, window.location.origin).searchParams.get('mode') || 'browse';
  };
  var isEmptyCompare = function (url) {
    var params = new URL(url, window.location.origin).searchParams;
    return (params.get('mode') || 'browse') === 'compare' && !(params.get('countries') || '').replace(/,/g, '');
  };

  /* Replace one region with the same region from the fetched page. */
  var swap = function (fresh, selector) {
    var here = container.querySelector(selector);
    var there = fresh.querySelector(selector);
    if (!here || !there) throw new Error('shape: ' + selector);
    here.replaceWith(document.importNode(there, true));
  };

  var navigate = function (href, push) {
    var current = window.location.href;

    // The page around the result changes too; the full load is the
    // version that is always right.
    if (modeOf(href) !== modeOf(current) || modeOf(href) !== 'compare'
        || isEmptyCompare(href) || isEmptyCompare(current)) {
      window.location.href = href;
      return;
    }

    root.setAttribute('aria-busy', 'true');

    window.fetch(href, { headers: { Accept: 'text/html' }, credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) throw new Error(String(response.status));
        return response.text();
      })
      .then(function (html) {
        var doc = new window.DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.querySelector('.c-cmp');
        var freshRoot = fresh && fresh.querySelector('[data-comparator]');
        if (!fresh || !freshRoot) throw new Error('shape');

        swap(fresh, '.c-cmp__bar');
        swap(fresh, '.c-cmp__aside');

        // The result: everything the server rendered, but not the React
        // mount, which stays where it is.
        Array.prototype.slice.call(root.childNodes).forEach(function (node) {
          if (node !== mount) root.removeChild(node);
        });
        Array.prototype.slice.call(freshRoot.childNodes).forEach(function (node) {
          root.insertBefore(document.importNode(node, true), mount);
        });
        root.setAttribute('data-endpoint', freshRoot.getAttribute('data-endpoint') || '');
        root.setAttribute('aria-busy', 'false');

        if (doc.title) document.title = doc.title;
        if (push) window.history.pushState({ comparator: true }, '', href);
      })
      .catch(function () {
        // Anything unexpected: the full page load, which always works.
        window.location.href = href;
      });
  };

  function Comparator() {
    useEffect(function () {
      var onClick = function (event) {
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || !container.contains(link)) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
        if (link.getAttribute('aria-disabled') === 'true') { event.preventDefault(); return; }
        if (link.hasAttribute('download')) return;

        var url = new URL(link.href, window.location.origin);
        // Exports and anything else that is not this page load normally.
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) return;

        event.preventDefault();
        navigate(url.href, true);
      };

      container.addEventListener('click', onClick);

      var onPop = function () { window.location.reload(); };
      window.addEventListener('popstate', onPop);

      return function () {
        container.removeEventListener('click', onClick);
        window.removeEventListener('popstate', onPop);
      };
    }, []);

    return null;
  }

  var mount = document.createElement('div');
  mount.setAttribute('data-comparator-live', '');
  mount.hidden = true;
  root.appendChild(mount);

  window.ReactDOM.createRoot(mount).render(h(Comparator));
})();
