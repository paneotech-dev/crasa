/* CRASA Digital Anchor Ecosystem — the administered shell.
 *
 * ONE JOB: the sidebar becomes a drawer below 1024px.
 *
 * With this file absent, blocked or broken, the sidebar is a sidebar. It is
 * in the document, it is navigable, and every link works. The drawer is an
 * improvement on a narrow viewport, not the way the navigation exists.
 *
 * The drawer is focus-trapped while open, because a drawer over the page
 * that lets Tab walk behind it is a drawer a keyboard reader cannot leave.
 */
(function () {
  'use strict';

  var sidebar = document.querySelector('[data-shell-sidebar]');
  var toggle = document.querySelector('[data-shell-toggle]');
  var veil = document.querySelector('[data-shell-veil]');

  if (!sidebar || !toggle) return;

  var open = false;
  var lastFocus = null;

  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

  function setOpen(next) {
    open = next;

    document.documentElement.classList.toggle('has-drawer-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    sidebar.setAttribute('data-open', open ? 'true' : 'false');

    if (veil) veil.hidden = !open;

    if (open) {
      lastFocus = document.activeElement;
      var first = sidebar.querySelector(FOCUSABLE);
      if (first) first.focus();
    } else if (lastFocus && lastFocus.focus) {
      lastFocus.focus();
    }
  }

  toggle.addEventListener('click', function () {
    setOpen(!open);
  });

  if (veil) {
    veil.addEventListener('click', function () {
      setOpen(false);
    });
  }

  document.addEventListener('keydown', function (event) {
    if (!open) return;

    if (event.key === 'Escape') {
      event.preventDefault();
      setOpen(false);

      return;
    }

    if (event.key !== 'Tab') return;

    /* The trap. Only while the drawer is open; at desktop widths the
       sidebar is part of the page and Tab should walk straight through it. */
    var items = Array.prototype.filter.call(
      sidebar.querySelectorAll(FOCUSABLE),
      function (node) { return node.offsetParent !== null; }
    );

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
  });

  /* A reader who widens the window past the breakpoint is looking at a
     sidebar again, and a trap around a sidebar is a trap around the page. */
  if (window.matchMedia) {
    var wide = window.matchMedia('(min-width: 1024px)');

    if (wide.addEventListener) {
      wide.addEventListener('change', function (event) {
        if (event.matches && open) setOpen(false);
      });
    }
  }
})();
