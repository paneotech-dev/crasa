/* CRASA Digital Anchor Ecosystem — header dropdowns.
 *
 * THE FLOOR IS AN OPEN MENU. Without this file every child link is in the
 * document and reachable: the CSS that closes a menu is under html.has-js,
 * and the toggle button is not drawn. This turns an open list into a
 * dropdown; it is not how the navigation exists.
 *
 * One group open at a time. Escape closes and returns focus to the button
 * that opened it, which is what a reader who opened it by keyboard expects.
 */
(function () {
  'use strict';

  var groups = Array.prototype.slice.call(document.querySelectorAll('[data-nav-group]'));

  if (!groups.length) return;

  groups.forEach(function (group) {
    var toggle = group.querySelector('[data-nav-toggle]');
    var menu = group.querySelector('[data-nav-menu]');

    if (!toggle || !menu) return;

    menu.hidden = true;

    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') === 'true';

      closeAll();

      if (!open) {
        toggle.setAttribute('aria-expanded', 'true');
        menu.hidden = false;
      }
    });

    /* Leaving the group with the keyboard closes it. Without this a reader
       tabs out of an open menu and it stays open behind them. */
    group.addEventListener('focusout', function (event) {
      if (!group.contains(event.relatedTarget)) {
        toggle.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
      }
    });
  });

  function closeAll(except) {
    groups.forEach(function (group) {
      if (group === except) return;

      var toggle = group.querySelector('[data-nav-toggle]');
      var menu = group.querySelector('[data-nav-menu]');

      if (toggle) toggle.setAttribute('aria-expanded', 'false');
      if (menu) menu.hidden = true;
    });
  }

  document.addEventListener('click', function (event) {
    if (!event.target.closest || !event.target.closest('[data-nav-group]')) {
      closeAll();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;

    var open = document.querySelector('[data-nav-toggle][aria-expanded="true"]');

    if (open) {
      closeAll();
      open.focus();
    }
  });
})();
