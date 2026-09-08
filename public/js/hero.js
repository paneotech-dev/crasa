/* CRASA Digital Anchor Ecosystem — hero rotation.
 *
 * A carousel with the accessibility that implies, and the restraint the
 * motion specification requires:
 *
 *   - stops on hover and on focus anywhere inside it
 *   - never advances while a control inside it holds focus, so a keyboard
 *     reader is not moved off the thing they are on
 *   - fully operable from the keyboard, arrows and dots both
 *   - announces each change through a polite live region
 *   - does not rotate at all under prefers-reduced-motion, and the controls
 *     are removed rather than left inert
 *   - offers an explicit pause, which is what makes an auto-advancing region
 *     acceptable at all under WCAG 2.2.2
 *
 * Without this file the first slide is served and nothing moves, which is a
 * banner and is a perfectly good outcome.
 */
(function () {
  'use strict';

  var hero = document.querySelector('[data-hero]');
  if (!hero) return;

  var slides = Array.prototype.slice.call(hero.querySelectorAll('[data-hero-slide]'));
  if (slides.length < 2) return;

  var dots = Array.prototype.slice.call(hero.querySelectorAll('[data-hero-dot]'));
  var live = hero.querySelector('[data-hero-live]');
  var pauseButton = hero.querySelector('[data-hero-pause]');
  var pauseLabel = hero.querySelector('[data-hero-pause-label]');

  var INTERVAL = 8000;
  var current = 0;
  var timer = null;
  var pausedByUser = false;
  var hovered = false;
  var focused = false;

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

  function prefersReduced() {
    return reduced && reduced.matches;
  }

  function show(index, announce) {
    current = (index + slides.length) % slides.length;

    slides.forEach(function (slide, i) {
      slide.hidden = i !== current;
    });

    dots.forEach(function (dot, i) {
      dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
    });

    if (announce && live) {
      // The label already reads "Slide 2 of 3", which is what a reader needs
      // to know changed. Announced politely so it waits its turn.
      live.textContent = slides[current].getAttribute('aria-label') || '';
    }
  }

  function start() {
    stop();
    if (prefersReduced() || pausedByUser || hovered || focused) return;
    timer = window.setInterval(function () { show(current + 1, true); }, INTERVAL);
  }

  function stop() {
    if (timer !== null) {
      window.clearInterval(timer);
      timer = null;
    }
  }

  /* Hover and focus both hold it still. Focus matters more than hover: a
     keyboard reader tabbing through the controls must not have the content
     move underneath them. */
  hero.addEventListener('mouseenter', function () { hovered = true; stop(); });
  hero.addEventListener('mouseleave', function () { hovered = false; start(); });
  hero.addEventListener('focusin', function () { focused = true; stop(); });
  hero.addEventListener('focusout', function (event) {
    if (!hero.contains(event.relatedTarget)) {
      focused = false;
      start();
    }
  });

  var prev = hero.querySelector('[data-hero-prev]');
  var next = hero.querySelector('[data-hero-next]');

  if (prev) prev.addEventListener('click', function () { show(current - 1, true); start(); });
  if (next) next.addEventListener('click', function () { show(current + 1, true); start(); });

  dots.forEach(function (dot, i) {
    dot.addEventListener('click', function () { show(i, true); start(); });
  });

  /* Left and right move between slides when focus is on the controls, which
     is what a reader who has landed on a tablist expects. */
  hero.addEventListener('keydown', function (event) {
    if (event.key === 'ArrowLeft') { event.preventDefault(); show(current - 1, true); }
    if (event.key === 'ArrowRight') { event.preventDefault(); show(current + 1, true); }
  });

  if (pauseButton) {
    pauseButton.addEventListener('click', function () {
      pausedByUser = !pausedByUser;
      pauseButton.setAttribute('aria-pressed', pausedByUser ? 'true' : 'false');
      if (pauseLabel) {
        pauseLabel.textContent = pausedByUser
          ? pauseButton.getAttribute('data-label-play') || pauseLabel.textContent
          : pauseButton.getAttribute('data-label-pause') || pauseLabel.textContent;
      }
      if (pausedByUser) stop(); else start();
    });
  }

  // A change of preference mid-session is honoured without a reload.
  if (reduced && reduced.addEventListener) {
    reduced.addEventListener('change', function () {
      if (prefersReduced()) { stop(); show(0, false); } else { start(); }
    });
  }

  show(0, false);
  start();
})();
