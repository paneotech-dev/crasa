/*
 * Display options. Text size, high contrast and underlined links, kept in
 * localStorage and applied as classes on <html>. The layout head applies
 * the stored choice before first paint; this file runs the panel.
 *
 * Storage can be missing or refuse (private windows, blocked site data),
 * so every read and write is guarded and the panel still works for the
 * current page without it.
 */
(function () {
  'use strict';

  var KEY = 'crasa-a11y';
  var NOTICE_KEY = 'crasa-translation-notice';

  // The translation notice closes by itself (it is a <details>); this only
  // remembers that it was closed, and hands focus to the content after it.
  var notice = document.querySelector('[data-translation-notice] details');
  if (notice) {
    notice.addEventListener('toggle', function () {
      if (notice.open) return;
      try { window.localStorage.setItem(NOTICE_KEY, 'closed'); } catch (e) { /* Shown again next page. */ }
      var main = document.getElementById('content');
      if (main && (document.activeElement === document.body || notice.contains(document.activeElement))) main.focus();
    });
  }

  var SIZES = ['smaller', 'normal', 'larger', 'largest'];
  var root = document.documentElement;
  var widget = document.querySelector('[data-a11y]');
  if (!widget) return;

  var toggle = widget.querySelector('[data-a11y-toggle]');
  var panel = widget.querySelector('[data-a11y-panel]');
  var status = widget.querySelector('[data-a11y-status]');

  function read() {
    try {
      var stored = JSON.parse(window.localStorage.getItem(KEY) || '{}');
      return stored && typeof stored === 'object' ? stored : {};
    } catch (e) {
      return {};
    }
  }

  function write(state) {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(state));
    } catch (e) { /* The choice holds for this page only. */ }
  }

  var state = read();
  if (SIZES.indexOf(state.size) === -1) state.size = 'normal';
  state.contrast = state.contrast === true;
  state.underline = state.underline === true;

  function apply() {
    SIZES.forEach(function (size) {
      root.classList.toggle('a11y-text-' + size, size !== 'normal' && state.size === size);
    });
    root.classList.toggle('a11y-contrast', state.contrast);
    root.classList.toggle('a11y-underline', state.underline);

    widget.querySelectorAll('[data-a11y-size]').forEach(function (button) {
      button.setAttribute('aria-pressed', String(button.getAttribute('data-a11y-size') === state.size));
    });
    widget.querySelectorAll('[data-a11y-flag]').forEach(function (button) {
      button.setAttribute('aria-pressed', String(state[button.getAttribute('data-a11y-flag')] === true));
    });
  }

  function announce(message) {
    // Cleared first, so the same message twice is still read out.
    status.textContent = '';
    window.setTimeout(function () { status.textContent = message; }, 50);
  }

  function open() {
    panel.hidden = false;
    panel.classList.remove('c-a11y__panel--start');
    if (panel.getBoundingClientRect().left < 0) panel.classList.add('c-a11y__panel--start');
    toggle.setAttribute('aria-expanded', 'true');
    var first = panel.querySelector('[aria-pressed="true"]') || panel.querySelector('button');
    if (first) first.focus();
  }

  function close(returnFocus) {
    if (panel.hidden) return;
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    if (returnFocus) toggle.focus();
  }

  toggle.addEventListener('click', function () {
    if (panel.hidden) open(); else close(false);
  });

  widget.querySelector('[data-a11y-close]').addEventListener('click', function () { close(true); });

  widget.querySelectorAll('[data-a11y-size]').forEach(function (button) {
    button.addEventListener('click', function () {
      state.size = button.getAttribute('data-a11y-size');
      apply();
      write(state);
      announce(widget.getAttribute('data-announce-size').replace(':size', button.textContent.trim()));
    });
  });

  widget.querySelectorAll('[data-a11y-flag]').forEach(function (button) {
    button.addEventListener('click', function () {
      var flag = button.getAttribute('data-a11y-flag');
      state[flag] = !state[flag];
      apply();
      write(state);
      var template = widget.getAttribute(state[flag] ? 'data-announce-on' : 'data-announce-off');
      announce(template.replace(':option', button.textContent.trim()));
    });
  });

  widget.querySelector('[data-a11y-reset]').addEventListener('click', function () {
    state = { size: 'normal', contrast: false, underline: false };
    apply();
    write(state);
    announce(widget.getAttribute('data-announce-reset'));
  });

  // Escape closes and hands focus back to the button that opened it.
  widget.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !panel.hidden) {
      event.preventDefault();
      close(true);
    }
  });

  // A click or a tab outside the widget closes it without moving focus.
  document.addEventListener('click', function (event) {
    if (!widget.contains(event.target)) close(false);
  });
  widget.addEventListener('focusout', function (event) {
    if (event.relatedTarget && !widget.contains(event.relatedTarget)) close(false);
  });

  apply();
})();
