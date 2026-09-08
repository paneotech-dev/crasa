/* CRASA Digital Anchor Ecosystem — public header behaviour.
 *
 * Two jobs. Collapse the primary navigation into the drawer at the width
 * where its items stop fitting the rendered language, which is measured
 * rather than assumed, because Portuguese and French run longer than
 * English. And run the drawer: escape closes it, focus is trapped inside
 * it while it is open, and focus returns to the trigger on close.
 *
 * Without this file the header keeps every item and wraps to a second row.
 */
(function () {
  'use strict';

  var header = document.querySelector('[data-header]');
  if (!header) return;

  var nav = header.querySelector('[data-header-nav]');
  var utilities = header.querySelector('[data-header-utilities]');
  var toggle = header.querySelector('[data-drawer-toggle]');
  var drawer = document.querySelector('[data-drawer]');
  if (!nav || !utilities || !toggle || !drawer) return;

  // The labelled trigger, measured while it is hidden.
  var TOGGLE_WIDTH = 120;

  var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

  /* Fit measurement ---------------------------------------------------- */

  function contentWidth(element) {
    var total = 0;
    var children = element.children;
    for (var i = 0; i < children.length; i++) {
      total += children[i].getBoundingClientRect().width;
    }
    var gap = parseFloat(getComputedStyle(element).columnGap) || 0;
    return total + gap * Math.max(0, children.length - 1);
  }

  /* Three states, and wrapping is the floor between them.
   *
   * inline   identity, nav and utilities all on one row
   * wrapped  the nav drops to a second row, still entirely visible
   * drawer   the nav goes into the drawer; search, switcher and extranet
   *          stay on the first row
   * compact  everything goes into the drawer
   *
   * The bug this replaces: the previous version collapsed to the drawer the
   * moment the nav did not fit on ONE row. In French the nine items stop
   * fitting a single row well above 1440, so a desktop reader lost the whole
   * navigation to a hamburger.
   *
   * Layout invariant 6 says wrapping to a second row is always better than
   * the alternative, so wrapping is tried before anything is hidden. The
   * drawer is only reached when identity and utilities alone fill the row,
   * which is genuinely narrow.
   */
  function measure() {
    header.setAttribute('data-nav', 'inline');

    var inner = header.querySelector('[data-header-inner]');
    var styles = getComputedStyle(inner);
    var available = inner.clientWidth
      - parseFloat(styles.paddingLeft)
      - parseFloat(styles.paddingRight);
    var gap = parseFloat(styles.columnGap) || 0;

    var identityWidth = header.querySelector('[data-header-identity]').getBoundingClientRect().width;
    var utilitiesWidth = utilities.getBoundingClientRect().width;
    var navWidth = contentWidth(nav);
    var widestNavItem = widestChild(nav);

    var stage;

    if (identityWidth + navWidth + utilitiesWidth + gap * 2 <= available) {
      // Everything on one row.
      stage = 'inline';
    } else if (identityWidth + utilitiesWidth + gap <= available && widestNavItem <= available) {
      // The nav wraps to its own row and stays entirely visible. This is the
      // floor, and it is where a 1440 French header now lands.
      stage = 'wrapped';
    } else if (identityWidth + TOGGLE_WIDTH + utilitiesWidth + gap * 2 <= available) {
      stage = 'drawer';
    } else {
      stage = 'compact';
    }

    header.setAttribute('data-nav', stage);

    if (stage === 'inline' || stage === 'wrapped') {
      closeDrawer(false);
      return true;
    }

    return false;
  }

  /* The widest single nav item. If even one item cannot fit the row, no
     amount of wrapping helps and the drawer is the only option. */
  function widestChild(element) {
    var widest = 0;
    var children = element.children;
    for (var i = 0; i < children.length; i++) {
      widest = Math.max(widest, children[i].getBoundingClientRect().width);
    }
    return widest;
  }

  /* Drawer ------------------------------------------------------------- */

  function openDrawer() {
    // In the drawer stage the switcher and the extranet action are still on
    // the first row, so the drawer must not repeat them.
    var duplicated = header.getAttribute('data-nav') === 'drawer';
    var langGroup = drawer.querySelector('[data-drawer-lang]');
    var extranet = drawer.querySelector('[data-drawer-extranet]');
    if (langGroup) langGroup.hidden = duplicated;
    if (extranet) extranet.hidden = duplicated;

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

  toggle.addEventListener('click', function () {
    if (drawer.hidden) openDrawer(); else closeDrawer(true);
  });

  /* Wiring ------------------------------------------------------------- */

  measure();

  var pending;
  function scheduleMeasure() {
    cancelAnimationFrame(pending);
    pending = requestAnimationFrame(measure);
  }

  window.addEventListener('resize', scheduleMeasure);

  // Web fonts land after first paint and change the measured widths.
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(scheduleMeasure);
  }
})();
