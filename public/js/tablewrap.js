/* Wide tables in the back office and the member area.
 *
 * A table wider than its column scrolls inside .a-tablewrap. A keyboard
 * reader can only scroll it if the wrapper can take focus, so a wrapper
 * that overflows and holds nothing focusable becomes a named region with
 * tabindex 0 (WCAG 2.1.1; axe scrollable-region-focusable). One that fits
 * gets no extra Tab stop. Checked again on resize.
 */
(function () {
  'use strict';

  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

  function check() {
    Array.prototype.forEach.call(document.querySelectorAll('.a-tablewrap'), function (wrap) {
      if (wrap.hasAttribute('data-tablewrap-fixed')) return;
      var scrolls = wrap.scrollWidth > wrap.clientWidth + 1;
      var added = wrap.hasAttribute('data-tablewrap-focus');
      if (scrolls && !added && !wrap.querySelector(FOCUSABLE) && !wrap.hasAttribute('tabindex')) {
        var caption = wrap.querySelector('caption');
        wrap.setAttribute('tabindex', '0');
        wrap.setAttribute('role', 'region');
        if (caption && caption.textContent.trim()) wrap.setAttribute('aria-label', caption.textContent.trim());
        else if (!wrap.hasAttribute('aria-label')) wrap.setAttribute('aria-label', wrap.getAttribute('data-tablewrap-label') || document.title);
        wrap.setAttribute('data-tablewrap-focus', '');
      } else if (!scrolls && added) {
        wrap.removeAttribute('tabindex');
        wrap.removeAttribute('role');
        wrap.removeAttribute('aria-label');
        wrap.removeAttribute('data-tablewrap-focus');
      }
    });
  }

  check();
  var t = null;
  window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(check, 150); });
})();
