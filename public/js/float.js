/*
 * Floating page controls: "Back to top" shows once the reader has
 * scrolled one screen and hides again near the top. Following the link
 * moves focus to the skip link, so the next Tab starts at the top of the
 * page as the view does.
 */
(function () {
  'use strict';

  var top = document.querySelector('[data-float-top]');
  if (!top) return;

  var ticking = false;

  function update() {
    ticking = false;
    top.hidden = window.scrollY < window.innerHeight;
  }

  window.addEventListener('scroll', function () {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(update);
    }
  }, { passive: true });
  window.addEventListener('resize', update);

  top.addEventListener('click', function (event) {
    event.preventDefault();
    var reduce = document.documentElement.classList.contains('a11y-pause')
      || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    var skip = document.querySelector('.c-skip');
    if (skip) skip.focus({ preventScroll: true });
  });

  update();
})();
