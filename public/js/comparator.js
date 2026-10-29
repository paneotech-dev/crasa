/* CRASA regulatory comparator: the three-step console.
 *
 * The page is complete without this file: every control is a link, every
 * state has a URL, the server draws the chart and the table. This file
 * makes a choice feel immediate. It intercepts the console's links, fetches
 * the page the link names, swaps the console in place, pushes the URL (so
 * "Copy link" and the address bar always reproduce what is on screen),
 * announces the new result in words, and keeps focus on the control the
 * reader used. Any failure falls back to following the link.
 *
 * No library: the console is server-rendered markup, so React 18 is no
 * longer loaded here.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-comparator]');
  if (!root || !window.fetch || !window.DOMParser) return;

  var region = root.querySelector('[data-cmp-swap]');
  var status = root.querySelector('[data-cmp-status]');
  var pending = null;

  function keyOf(link) {
    if (!link) return null;
    if (link.getAttribute('data-key')) return '[data-key="' + link.getAttribute('data-key') + '"]';
    var href = link.getAttribute('href') || '';
    var params = new URL(href, window.location.origin).searchParams;
    if (link.classList.contains('c-cmp__pick')) return '.c-cmp__pick[href*="indicator=' + params.get('indicator') + '"]';
    if (link.classList.contains('c-cmp__chip--all')) return '.c-cmp__chip--all';
    return null;
  }

  function enhance() {
    var copy = region.querySelector('[data-cmp-copy]');
    var input = region.querySelector('[data-cmp-permalink]');
    if (copy && input) {
      copy.hidden = false;
      copy.addEventListener('click', function () {
        var text = window.location.href.split('#')[0];
        input.value = text;
        var done = function () { say(copy.getAttribute('data-done')); };
        var fail = function () { input.focus(); input.select(); say(copy.getAttribute('data-failed')); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done, function () {
            input.select();
            try { document.execCommand('copy') ? done() : fail(); } catch (e) { fail(); }
          });
        } else {
          input.select();
          try { document.execCommand('copy') ? done() : fail(); } catch (e) { fail(); }
        }
      });
    }
  }

  function say(text) {
    if (!status || !text) return;
    status.textContent = '';
    window.setTimeout(function () { status.textContent = text; }, 30);
  }

  function load(href, push, focusKey) {
    if (pending && pending.abort) pending.abort();
    pending = window.AbortController ? new AbortController() : null;
    region.setAttribute('aria-busy', 'true');

    return fetch(href, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
      credentials: 'same-origin',
      signal: pending ? pending.signal : undefined
    }).then(function (response) {
      if (!response.ok) throw new Error('status ' + response.status);
      return response.text();
    }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var fresh = doc.querySelector('[data-cmp-swap]');
      if (!fresh) throw new Error('shape');
      region.innerHTML = fresh.innerHTML;
      region.setAttribute('data-summary', fresh.getAttribute('data-summary') || '');
      region.removeAttribute('aria-busy');
      if (push) window.history.pushState({ cmp: true }, '', href);
      enhance();
      say(fresh.getAttribute('data-summary'));
      if (focusKey) {
        var target = region.querySelector(focusKey);
        if (target) target.focus({ preventScroll: true });
      }
    }).catch(function (error) {
      if (error && error.name === 'AbortError') return;
      window.location.href = href;
    });
  }

  root.addEventListener('click', function (event) {
    var link = event.target.closest && event.target.closest('a[data-cmp-nav]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    load(link.href, true, keyOf(link));
  });

  window.addEventListener('popstate', function () {
    load(window.location.href, false, null);
  });

  enhance();
})();
