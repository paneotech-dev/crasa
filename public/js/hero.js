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
 *   - does not rotate at all under prefers-reduced-motion, but keeps its
 *     controls: the slides carry different content, so removing the way to
 *     reach them withholds two of the three
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
  var counter = hero.querySelector('[data-hero-counter]');

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

  /* A crossfade, not a slide-in.
   *
   * Screen 02 stacks its slides on one grid cell and changes which one is
   * shown; nothing travels. A 400ms fade on --ease-in is the change the
   * motion specification allows, because it does not imply a direction and
   * it does not move text a reader may be part-way through.
   *
   * The outgoing slide stays in the flow for the length of the fade and is
   * then hidden, so the band never collapses to nothing between slides. It
   * is aria-hidden throughout, so the accessibility tree changes at once
   * even though the pixels take 400ms. */
  var FADE = 400;
  var fading = null;

  function show(index, announce) {
    var previous = current;
    current = (index + slides.length) % slides.length;

    if (previous === current && slides[current].hidden === false) {
      return;
    }

    if (fading !== null) {
      window.clearTimeout(fading);
      fading = null;
    }

    slides.forEach(function (slide, i) {
      if (i === current) {
        slide.hidden = false;
        slide.setAttribute('aria-hidden', 'false');
        // Forced reflow, so the browser has a frame at opacity 0 to
        // transition from rather than jumping straight to 1.
        void slide.offsetWidth;
        slide.setAttribute('data-state', 'current');
      } else if (i === previous && !prefersReduced()) {
        slide.setAttribute('aria-hidden', 'true');
        slide.setAttribute('data-state', 'leaving');
      } else {
        slide.hidden = true;
        slide.setAttribute('aria-hidden', 'true');
        slide.removeAttribute('data-state');
      }
    });

    if (!prefersReduced()) {
      fading = window.setTimeout(function () {
        slides.forEach(function (slide, i) {
          if (i !== current) {
            slide.hidden = true;
            slide.removeAttribute('data-state');
          }
        });
        fading = null;
      }, FADE);
    }

    dots.forEach(function (dot, i) {
      dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
    });

    if (counter) counter.textContent = (current + 1) + ' / ' + slides.length;

    if (announce && live) {
      // The label already reads "Slide 2 of 3: featured comparison", which
      // is what a reader needs to know changed. Announced politely so it
      // waits its turn.
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

      /* Both names move together. The visible one is a single word; the
         accessible one says what is being paused, which is what the state
         note used to carry for a reader who cannot see the control. Each
         accessible name contains its visible label, per SC 2.5.3. */
      var visible = pauseButton.getAttribute(pausedByUser ? 'data-label-play' : 'data-label-pause');
      var spoken = pauseButton.getAttribute(pausedByUser ? 'data-aria-play' : 'data-aria-pause');

      if (pauseLabel && visible) pauseLabel.textContent = visible;
      if (spoken) pauseButton.setAttribute('aria-label', spoken);
      if (pausedByUser) stop(); else start();
    });
  }

  // A change of preference mid-session is honoured without a reload.
  if (reduced && reduced.addEventListener) {
    reduced.addEventListener('change', function () {
      if (prefersReduced()) { stop(); show(0, false); } else { start(); }
    });
  }

  /* Swipe.
   *
   * Horizontal only, and only past a threshold that a vertical scroll will
   * not cross: a reader flicking down the page must not change slide by
   * accident. Passive listeners throughout, so the scroll is never blocked
   * while we decide.
   */
  var SWIPE = 45;
  var touchX = null;
  var touchY = null;

  hero.addEventListener('touchstart', function (event) {
    if (event.touches.length !== 1) return;
    touchX = event.touches[0].clientX;
    touchY = event.touches[0].clientY;
  }, { passive: true });

  hero.addEventListener('touchend', function (event) {
    if (touchX === null) return;

    var touch = event.changedTouches[0];
    var dx = touch.clientX - touchX;
    var dy = touch.clientY - touchY;

    touchX = null;
    touchY = null;

    // A gesture that travelled further down than across was a scroll.
    if (Math.abs(dx) < SWIPE || Math.abs(dx) <= Math.abs(dy)) return;

    show(dx < 0 ? current + 1 : current - 1, true);
    start();
  }, { passive: true });

  show(0, false);
  start();
})();
