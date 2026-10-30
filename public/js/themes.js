/*
 * The thematic areas band on the home page: shows the pause button and
 * keeps it in step with the band. CSS does the scrolling and stops it on
 * hover, on focus, under prefers-reduced-motion and with the "Pause
 * animations" display option (html.a11y-pause), where the button is not
 * drawn.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-th-toggle]').forEach(function (button) {
    var band = button.closest('.th-band');
    if (!band) return;
    button.hidden = false;
    button.addEventListener('click', function () {
      var paused = !band.classList.contains('is-paused');
      band.classList.toggle('is-paused', paused);
      button.textContent = paused ? button.dataset.labelPlay : button.dataset.labelPause;
    });
  });
})();
