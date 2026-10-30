/* CRASA: cookie choice (item 1h, 30 October 2026).
 *
 * Third-party content (social posts, map tiles) is always sent as a
 * placeholder, [data-consent-embed="social|maps"]. This script swaps a
 * placeholder for the real thing when the reader has allowed its category,
 * or when they press "Show" on that one item. Nothing from another origin
 * is requested before either.
 *
 * The choice is kept in localStorage under a version and a date. A choice
 * older than twelve months, or made under another version, counts as no
 * choice and the banner is shown again.
 */
(function () {
  'use strict';

  var KEY = 'crasa-consent';
  var VERSION = 1;
  var MAX_AGE = 365 * 24 * 60 * 60 * 1000;
  var CATEGORIES = ['social', 'maps'];

  var banner = document.querySelector('[data-consent-banner]');
  var dialog = document.querySelector('[data-consent-dialog]');
  var status = document.querySelector('[data-consent-status]');
  var opener = null;

  function read() {
    try {
      var c = JSON.parse(window.localStorage.getItem(KEY) || 'null');
      if (c && c.v === VERSION && typeof c.at === 'number' && Date.now() - c.at < MAX_AGE) return c;
    } catch (e) { /* No storage: no choice. */ }
    return null;
  }

  function write(choice) {
    var stored = { v: VERSION, at: Date.now() };
    CATEGORIES.forEach(function (k) { stored[k] = choice[k] === true; });
    try { window.localStorage.setItem(KEY, JSON.stringify(stored)); } catch (e) { /* Asked again next page. */ }
    return stored;
  }

  var xScript = null;
  function loadX(done) {
    if (window.twttr && window.twttr.widgets) { done(); return; }
    if (!xScript) {
      xScript = document.createElement('script');
      xScript.src = 'https://platform.twitter.com/widgets.js';
      xScript.async = true;
      xScript.charset = 'utf-8';
      document.body.appendChild(xScript);
    }
    xScript.addEventListener('load', done);
  }

  /* Replaces one placeholder with its content. */
  function activate(el, focus) {
    if (el.hasAttribute('data-consent-done')) return;
    el.setAttribute('data-consent-done', '');
    var kind = el.getAttribute('data-embed');
    var target = el;

    Array.prototype.forEach.call(el.querySelectorAll('[data-consent-notice]'), function (n) { n.remove(); });

    if (kind === 'frame') {
      var frame = document.createElement('iframe');
      frame.src = el.getAttribute('data-embed-src');
      frame.title = el.getAttribute('data-embed-title') || '';
      frame.height = el.getAttribute('data-embed-height') || '520';
      frame.width = '500';
      frame.setAttribute('frameborder', '0');
      frame.setAttribute('allowfullscreen', '');
      frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      el.className = 's-post__frame';
      el.style.setProperty('--s-frame-h', frame.height + 'px');
      el.appendChild(frame);
      target = frame;
    } else if (kind === 'x') {
      var quote = el.querySelector('[data-x-quote]');
      el.className = 's-post__x';
      if (quote) {
        quote.className = 'twitter-tweet';
        loadX(function () {
          if (window.twttr && window.twttr.widgets) window.twttr.widgets.load(el);
        });
      }
    } else if (kind === 'tiles') {
      Array.prototype.forEach.call(el.querySelectorAll('img[data-src]'), function (img) {
        img.src = img.getAttribute('data-src');
        img.removeAttribute('data-src');
      });
    } else {
      // The contact map draws itself (contact-map.js) on this event.
      el.dispatchEvent(new CustomEvent('consent:show'));
    }

    if (focus) {
      if (!target.hasAttribute('tabindex') && target === el) el.setAttribute('tabindex', '-1');
      target.focus();
    }
  }

  function apply(choice) {
    Array.prototype.forEach.call(document.querySelectorAll('[data-consent-embed]'), function (el) {
      if (choice && choice[el.getAttribute('data-consent-embed')] === true) activate(el, false);
    });
  }

  // The banner's height as --cc-h on <html>: the floating controls sit
  // above it and focused content scrolls clear of it, so nothing a keyboard
  // reader reaches is hidden under the banner.
  function setBannerHeight() {
    var h = banner && !banner.hidden ? banner.offsetHeight : 0;
    document.documentElement.style.setProperty('--cc-h', h + 'px');
  }

  function hideBanner() {
    if (!banner || banner.hidden) return;
    var hadFocus = banner.contains(document.activeElement);
    banner.hidden = true;
    setBannerHeight();
    // Focus would otherwise be lost with the banner.
    if (hadFocus) {
      var main = document.getElementById('content');
      if (main) main.focus();
    }
  }

  function decide(choice) {
    var before = read();
    var stored = write(choice);
    // Something already shown that is now refused goes on a fresh page.
    var withdrawn = before && CATEGORIES.some(function (k) {
      return before[k] === true && stored[k] !== true && document.querySelector('[data-consent-embed="' + k + '"][data-consent-done]');
    });
    if (dialog && dialog.open) dialog.close();
    hideBanner();
    if (withdrawn) { window.location.reload(); return; }
    apply(stored);
    if (status) {
      status.textContent = '';
      window.setTimeout(function () { status.textContent = status.getAttribute('data-saved'); }, 50);
    }
  }

  function all(value) {
    var c = {};
    CATEGORIES.forEach(function (k) { c[k] = value; });
    return c;
  }

  function openSettings(trigger) {
    if (!dialog || typeof dialog.showModal !== 'function') return false;
    opener = trigger || document.activeElement;
    var current = read() || {};
    CATEGORIES.forEach(function (k) {
      var box = dialog.querySelector('[name="' + k + '"]');
      if (box) box.checked = current[k] === true;
    });
    dialog.showModal();
    var first = dialog.querySelector('input, button');
    if (first) first.focus();
    return true;
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-consent-all], [data-consent-none], [data-consent-settings], [data-consent-open], [data-consent-close], [data-consent-show]') : null;
    if (!t) return;

    if (t.hasAttribute('data-consent-all')) { decide(all(true)); }
    else if (t.hasAttribute('data-consent-none')) { decide(all(false)); }
    else if (t.hasAttribute('data-consent-settings') || t.hasAttribute('data-consent-open')) {
      if (openSettings(t)) e.preventDefault();
    }
    else if (t.hasAttribute('data-consent-close')) { dialog.close(); }
    else if (t.hasAttribute('data-consent-show')) {
      var el = t.closest('[data-consent-embed]');
      if (el) { e.preventDefault(); activate(el, true); }
    }
  });

  if (dialog) {
    dialog.querySelector('[data-consent-form]').addEventListener('submit', function (e) {
      e.preventDefault();
      var c = {};
      CATEGORIES.forEach(function (k) {
        var box = dialog.querySelector('[name="' + k + '"]');
        c[k] = !!(box && box.checked);
      });
      decide(c);
    });
    // Focus goes back to what opened the dialog (the footer link, the
    // banner's Settings button) or, if that has gone, to the main content.
    dialog.addEventListener('close', function () {
      var back = opener && document.contains(opener) && !(banner && banner.hidden && banner.contains(opener)) ? opener : document.getElementById('content');
      opener = null;
      if (back) back.focus();
    });
  }

  var choice = read();
  if (choice) apply(choice);
  else if (banner) {
    banner.hidden = false;
    setBannerHeight();
    window.addEventListener('resize', setBannerHeight);
  }

  window.CRASAConsent = { read: read };
})();
