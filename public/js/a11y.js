/*
 * Display options. Text size, line spacing, reading (dyslexia-friendly)
 * spacing, high contrast, underlined links and paused animations, kept in
 * localStorage and applied as classes on <html>. The layout head and the
 * toolbar partial apply the stored choice before the page is drawn; this
 * file runs the panel.
 *
 * Paused animations: html.a11y-pause stops CSS animation and transition
 * (a11y.css), and a 'crasa:a11y-pause' event on document, with
 * detail.paused, tells a slider or counter script to stop and stay
 * stopped. Scripts that start later read the class.
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
  var FLAGS = ['spacing', 'dyslexia', 'contrast', 'underline', 'pause'];
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
  FLAGS.forEach(function (flag) { state[flag] = state[flag] === true; });

  function apply() {
    SIZES.forEach(function (size) {
      root.classList.toggle('a11y-text-' + size, size !== 'normal' && state.size === size);
    });
    FLAGS.forEach(function (flag) { root.classList.toggle('a11y-' + flag, state[flag]); });

    widget.querySelectorAll('[data-a11y-size]').forEach(function (button) {
      button.setAttribute('aria-pressed', String(button.getAttribute('data-a11y-size') === state.size));
    });
    widget.querySelectorAll('[data-a11y-flag]').forEach(function (button) {
      button.setAttribute('aria-pressed', String(state[button.getAttribute('data-a11y-flag')] === true));
    });
  }

  function pauseEvent() {
    document.dispatchEvent(new CustomEvent('crasa:a11y-pause', { detail: { paused: state.pause } }));
    // Media the page plays by itself stops too; the reader can still
    // start a video with its own controls.
    if (state.pause) {
      document.querySelectorAll('video[autoplay]').forEach(function (video) { video.pause(); });
    }
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
      var name = button.querySelector('.c-a11y__switch-name') || button;
      announce(template.replace(':option', name.textContent.trim()));
      if (flag === 'pause') pauseEvent();
    });
  });

  widget.querySelector('[data-a11y-reset]').addEventListener('click', function () {
    var wasPaused = state.pause;
    state = { size: 'normal' };
    FLAGS.forEach(function (flag) { state[flag] = false; });
    apply();
    write(state);
    if (wasPaused) pauseEvent();
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
  if (state.pause) pauseEvent();
})();
