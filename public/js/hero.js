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
  // The counter went with the control strip on 9 September 2026. Kept as
  // a lookup rather than deleted: a hero that still carries one is still
  // updated, and the guard costs nothing.
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

      /* The button carries an icon and no visible text, so SC 2.5.3 has
         nothing to match and the accessible name is free to say what is
         being paused rather than repeating one word. The icon follows
         aria-pressed in CSS, so the state and the glyph cannot drift. */
      var spoken = pauseButton.getAttribute(pausedByUser ? 'data-aria-play' : 'data-aria-pause');

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

/* The Concept 3 home hero (C6, 29 October 2026).
 *
 * Crossfade 600ms with the caption sliding in (k.css), advance every 7s,
 * paused on hover, while focus is inside, while the tab is hidden and by
 * the pause button (aria-pressed). Previous and next, the dots and the
 * Featured list choose a slide; arrow keys work while focus is in the
 * hero; a horizontal swipe on the photograph moves one slide. Each change
 * is announced politely. Under prefers-reduced-motion nothing advances on
 * its own and the change is a plain swap.
 */
(function () {
  'use strict';

  var hero = document.querySelector('[data-k-hero]');
  if (!hero) return;

  var caps = Array.prototype.slice.call(hero.querySelectorAll('[data-k-slide]'));
  var figs = Array.prototype.slice.call(hero.querySelectorAll('[data-k-fig]'));
  var dots = Array.prototype.slice.call(hero.querySelectorAll('[data-k-dot]'));
  var live = hero.querySelector('[data-k-live]');
  var pause = hero.querySelector('[data-k-pause]');
  var photo = hero.querySelector('.k-hero__photo');
  var total = caps.length;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var DELAY = 7000;
  var index = 0;
  var timer = null;
  var userPaused = reduce || document.documentElement.classList.contains('a11y-no-motion');
  var hovering = false;
  var focusInside = false;

  if (total < 2) return;

  hero.style.setProperty('--k-dur', DELAY + 'ms');

  function show(next, announce) {
    index = (next + total) % total;
    caps.forEach(function (cap, i) {
      var on = i === index;
      cap.classList.toggle('is-on', on);
      if (on) { cap.removeAttribute('aria-hidden'); cap.removeAttribute('inert'); }
      else { cap.setAttribute('aria-hidden', 'true'); cap.setAttribute('inert', ''); }
    });
    figs.forEach(function (fig, i) {
      fig.classList.toggle('is-on', i === index);
      var img = fig.querySelector('img[loading="lazy"]');
      if (i === index && img) img.loading = 'eager';
    });
    dots.forEach(function (dot) {
      var on = parseInt(dot.getAttribute('data-k-dot'), 10) === index;
      dot.setAttribute('aria-current', on ? 'true' : 'false');
      // Restart the progress bar of the Featured item.
      if (on && dot.classList.contains('k-fitem')) {
        dot.classList.remove('is-run');
        void dot.offsetWidth;
        dot.classList.add('is-run');
      }
    });
    if (announce && live) {
      live.textContent = live.getAttribute('data-template')
        .replace(':index', index + 1).replace(':total', total)
        .replace(':name', caps[index].getAttribute('data-name') || '');
    }
    schedule();
  }

  function running() {
    return !userPaused && !hovering && !focusInside && !document.hidden;
  }

  function schedule() {
    clearTimeout(timer);
    hero.classList.toggle('is-paused', !running());
    if (running()) timer = setTimeout(function () { show(index + 1, false); }, DELAY);
  }

  hero.querySelector('[data-k-prev]').addEventListener('click', function () { show(index - 1, true); });
  hero.querySelector('[data-k-next]').addEventListener('click', function () { show(index + 1, true); });
  dots.forEach(function (dot) {
    dot.addEventListener('click', function () { show(parseInt(dot.getAttribute('data-k-dot'), 10), true); });
  });

  function setPaused(value) {
    userPaused = value;
    pause.setAttribute('aria-pressed', value ? 'true' : 'false');
    pause.setAttribute('aria-label', pause.getAttribute(value ? 'data-label-play' : 'data-label-pause'));
    schedule();
  }
  pause.addEventListener('click', function () { setPaused(!userPaused); });
  if (userPaused) setPaused(true);

  hero.addEventListener('mouseenter', function () { hovering = true; schedule(); });
  hero.addEventListener('mouseleave', function () { hovering = false; schedule(); });
  hero.addEventListener('focusin', function () { focusInside = true; schedule(); });
  hero.addEventListener('focusout', function (event) {
    if (!event.relatedTarget || !hero.contains(event.relatedTarget)) { focusInside = false; schedule(); }
  });
  document.addEventListener('visibilitychange', schedule);

  hero.addEventListener('keydown', function (event) {
    if (event.target.matches('input, textarea, select')) return;
    if (event.key === 'ArrowLeft') { event.preventDefault(); show(index - 1, true); }
    if (event.key === 'ArrowRight') { event.preventDefault(); show(index + 1, true); }
  });

  var startX = null;
  var startY = null;
  photo.addEventListener('touchstart', function (event) {
    startX = event.touches[0].clientX;
    startY = event.touches[0].clientY;
  }, { passive: true });
  photo.addEventListener('touchend', function (event) {
    if (startX === null) return;
    var dx = event.changedTouches[0].clientX - startX;
    var dy = event.changedTouches[0].clientY - startY;
    if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) show(index + (dx < 0 ? 1 : -1), true);
    startX = null;
  });

  hero.classList.add('is-ready');
  show(0, false);
})();
