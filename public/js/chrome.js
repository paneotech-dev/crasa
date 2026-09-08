/* CRASA Digital Anchor Ecosystem — public header behaviour.
 *
 * One job: run the drawer. Escape closes it, focus is trapped inside it
 * while it is open, and focus returns to the trigger on close.
 *
 * The collapse itself is CSS, at the breakpoints screen 01 names. It used to
 * be measured at runtime and that failed three times, each time hiding a
 * navigation that fitted. A media query cannot be wrong about the viewport
 * width, so there is nothing here to get wrong.
 *
 * Without this file the header keeps every item and wraps to a second row.
 */
(function () {
  'use strict';

  var header = document.querySelector('[data-header]');
  if (!header) return;

  var toggle = header.querySelector('[data-drawer-toggle]');
  var drawer = document.querySelector('[data-drawer]');
  if (!toggle || !drawer) return;

  var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

  /* Drawer ------------------------------------------------------------- */

  function openDrawer() {
    drawer.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', toggle.getAttribute('data-label-close'));
    var first = drawer.querySelector(FOCUSABLE);
    if (first) first.focus();
    document.addEventListener('keydown', onKeydown, true);
  }

  function closeDrawer(returnFocus) {
    if (drawer.hidden) return;
    drawer.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', toggle.getAttribute('data-label-open'));
    document.removeEventListener('keydown', onKeydown, true);
    if (returnFocus !== false) toggle.focus();
  }

  function onKeydown(event) {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeDrawer(true);
      return;
    }
    if (event.key !== 'Tab') return;

    var items = Array.prototype.filter.call(
      drawer.querySelectorAll(FOCUSABLE),
      function (el) { return el.offsetParent !== null; }
    );
    items.unshift(toggle);
    if (!items.length) return;

    var first = items[0];
    var last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  /* Wiring -------------------------------------------------------------- */

  toggle.addEventListener('click', function () {
    if (drawer.hidden) openDrawer(); else closeDrawer(true);
  });

  /* Close the drawer when the viewport grows past the breakpoint, so a
     reader who rotates a tablet is not left with an open drawer over a
     header that is no longer collapsed. */
  if (window.matchMedia) {
    var mobile = window.matchMedia('(max-width: 767.98px)');
    if (mobile.addEventListener) {
      mobile.addEventListener('change', function () {
        if (!mobile.matches) closeDrawer(false);
      });
    }
  }
})();
