/* CRASA motion (C1, 29 October 2026). No library.
 *
 * Reveal: elements with .u-reveal get .is-in when they enter the viewport
 * (ui.css draws the 12px rise and fade, 400ms). Siblings inside a
 * [data-stagger] parent are delayed 60ms each, at most 300ms.
 * Count-up: [data-count] numbers count from zero to their value once,
 * in 400ms, when first seen. The final value is in the markup, so without
 * script, or under prefers-reduced-motion, the number is simply there.
 */
(function () {
  'use strict';

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement;
  var paused = root.classList.contains('a11y-no-motion') || root.classList.contains('a11y-pause');
  var reveals = Array.prototype.slice.call(document.querySelectorAll('.u-reveal'));
  var counters = Array.prototype.slice.call(document.querySelectorAll('[data-count]'));

  if (reduce || paused || !('IntersectionObserver' in window)) {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
    return;
  }

  document.documentElement.classList.add('k-motion');

  // The display options toolbar's "pause animations": everything shows
  // at once and no number counts.
  var stopped = false;
  document.addEventListener('crasa:a11y-pause', function (event) {
    if (event.detail && event.detail.paused === false) return;
    stopped = true;
    reveals.forEach(function (el) { el.classList.add('is-in'); el.style.transitionDelay = '0ms'; });
    counters.forEach(function (el) { el.textContent = el.getAttribute('data-final') || el.textContent; });
  });

  Array.prototype.forEach.call(document.querySelectorAll('[data-stagger]'), function (parent) {
    var kids = Array.prototype.filter.call(parent.children, function (c) { return c.classList.contains('u-reveal'); });
    kids.forEach(function (kid, i) { kid.style.transitionDelay = Math.min(i * 60, 300) + 'ms'; });
  });

  var revealer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-in');
      revealer.unobserve(entry.target);
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

  reveals.forEach(function (el) {
    // Already on screen at load: shown at once, no flash.
    var r = el.getBoundingClientRect();
    if (r.top < window.innerHeight && r.bottom > 0) {
      el.classList.add('is-in');
    } else {
      revealer.observe(el);
    }
  });

  function format(value, template) {
    return template.replace(/[\d.,\s]+/, String(value));
  }

  function run(el) {
    var target = parseInt(el.getAttribute('data-count'), 10);
    if (isNaN(target)) return;
    var from = parseInt(el.getAttribute('data-from') || '0', 10);
    var text = el.textContent;
    el.setAttribute('data-final', text);
    if (stopped || root.classList.contains('a11y-pause')) return;
    var start = null;
    var duration = 400;
    function step(t) {
      if (start === null) start = t;
      var p = Math.min((t - start) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      if (stopped) { el.textContent = text; return; }
      el.textContent = format(Math.round(from + (target - from) * eased), text);
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  var counter = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      counter.unobserve(entry.target);
      run(entry.target);
    });
  }, { threshold: 0.4 });

  counters.forEach(function (el) { counter.observe(el); });
})();
