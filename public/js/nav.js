/* CRASA public header: the mega menu (Concept 3 port, 29 October 2026).
 *
 * Without this file each panel opens on hover and while focus is inside
 * its item (CSS), so every link is reachable. With it:
 * - the chevron (or a label without a page) opens and closes its panel on
 *   click, Enter and Space; one panel open at a time;
 * - Escape closes and returns focus to the chevron;
 * - ArrowDown on the chevron opens the panel and moves to its first link;
 *   ArrowUp and ArrowDown move between the panel's links, Home and End to
 *   the first and last;
 * - ArrowLeft and ArrowRight move between the top-level items;
 * - hovering an item with a fine pointer opens it and keeps aria-expanded
 *   true while it is open; a click outside closes it.
 */
(function () {
  'use strict';

  var nav = document.querySelector('[data-k-nav]');
  if (!nav) return;

  var items = Array.prototype.slice.call(nav.querySelectorAll('.k-ni'));
  var groups = Array.prototype.slice.call(nav.querySelectorAll('[data-k-dd]'));
  var fine = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var timer = null;

  document.documentElement.classList.add('k-nav-js');

  function toggleOf(group) { return group.querySelector('[data-k-dd-toggle]'); }
  function linksOf(group) { return Array.prototype.slice.call(group.querySelectorAll('.k-mega a')); }

  function close(group) {
    group.classList.remove('is-open');
    toggleOf(group).setAttribute('aria-expanded', 'false');
  }

  function closeAll(except) {
    groups.forEach(function (g) { if (g !== except) close(g); });
  }

  function open(group) {
    closeAll(group);
    group.classList.add('is-open');
    toggleOf(group).setAttribute('aria-expanded', 'true');
  }

  function isOpen(group) { return group.classList.contains('is-open'); }

  function topFocusables() {
    return items.map(function (item) { return item.querySelector('.k-nt, [data-k-dd-toggle]'); });
  }

  groups.forEach(function (group) {
    var toggle = toggleOf(group);

    toggle.addEventListener('click', function () {
      if (isOpen(group)) { close(group); } else { open(group); }
    });

    toggle.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        event.stopPropagation();
        open(group);
        var links = linksOf(group);
        if (links.length) links[0].focus();
      }
    });

    group.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && isOpen(group)) {
        event.preventDefault();
        close(group);
        toggle.focus();
        return;
      }

      var links = linksOf(group);
      var index = links.indexOf(document.activeElement);
      if (index === -1) return;

      var next = null;
      if (event.key === 'ArrowDown') next = links[Math.min(index + 1, links.length - 1)];
      if (event.key === 'ArrowUp') next = index === 0 ? toggle : links[index - 1];
      if (event.key === 'Home') next = links[0];
      if (event.key === 'End') next = links[links.length - 1];
      if (next) {
        event.preventDefault();
        next.focus();
      }
    });

    // Focus leaving the item closes its panel.
    group.addEventListener('focusout', function (event) {
      if (event.relatedTarget && !group.contains(event.relatedTarget)) close(group);
    });

    if (fine) {
      group.addEventListener('mouseenter', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { open(group); }, 80);
      });
      group.addEventListener('mouseleave', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { close(group); }, 160);
      });
    }
  });

  // Left and right move along the top row.
  nav.addEventListener('keydown', function (event) {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
    var tops = topFocusables();
    var current = items.findIndex(function (item) { return item.contains(document.activeElement); });
    if (current === -1) return;
    var inPanel = document.activeElement.closest('.k-mega');
    if (inPanel) return;
    event.preventDefault();
    var target = event.key === 'ArrowRight'
      ? tops[(current + 1) % tops.length]
      : tops[(current - 1 + tops.length) % tops.length];
    closeAll(null);
    target.focus();
  });

  document.addEventListener('click', function (event) {
    if (!nav.contains(event.target)) closeAll(null);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeAll(null);
  });

  // The row lifts with a soft shadow once the page has scrolled.
  var row = document.querySelector('[data-k-navrow]');
  if (row && 'IntersectionObserver' in window) {
    var sentinel = document.createElement('div');
    sentinel.setAttribute('aria-hidden', 'true');
    sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:160px;pointer-events:none';
    document.body.prepend(sentinel);
    new IntersectionObserver(function (entries) {
      row.classList.toggle('is-stuck', !entries[0].isIntersecting);
    }).observe(sentinel);
  }
})();
