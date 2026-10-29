/* CRASA: the SADC region map, enhanced.
 *
 * The map works with no script: every member state is a link, the side
 * panel answers hover and focus in CSS, the legend and the list say what
 * the outlines are. This file adds three things:
 *
 *   a tooltip beside the shape on hover and on keyboard focus (country,
 *   regulator, the value when the map is shaded, and Open), which replaces
 *   the side panel so the map can take the full width;
 *   the same tooltip for Madagascar and Seychelles, which says that they
 *   are SADC member states but not CRASA members;
 *   one pulse of the island dots when the map first scrolls into view,
 *   skipped under prefers-reduced-motion.
 */
(function () {
  'use strict';

  var maps = document.querySelectorAll('[data-region-map]');
  Array.prototype.forEach.call(maps, setUp);

  function setUp(figure) {
    var stage = figure.querySelector('.c-map-region__stage');
    var tip = figure.querySelector('.c-map-region__tip');
    if (!stage || !tip) return;

    figure.classList.add('is-enhanced');
    var openLabel = figure.getAttribute('data-tip-open') || 'Open';
    var current = null;
    var hideTimer = null;

    var targets = figure.querySelectorAll('.c-map-region__member, .c-map-region__nonmember, .c-map-region__inset');
    Array.prototype.forEach.call(targets, function (el) {
      el.addEventListener('mouseenter', function () { show(el); });
      el.addEventListener('mouseleave', scheduleHide);
      el.addEventListener('focus', function () { show(el); });
      el.addEventListener('blur', scheduleHide);
    });
    tip.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
    tip.addEventListener('mouseleave', scheduleHide);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') hide();
    });

    function partner(el) {
      // The inset card and the shape on the map are the same member state:
      // pointing at one highlights the other.
      var iso = el.getAttribute('data-iso');
      return iso ? figure.querySelectorAll('[data-iso="' + iso + '"]') : [el];
    }

    function show(el) {
      clearTimeout(hideTimer);
      if (current) Array.prototype.forEach.call(partner(current), function (p) { p.classList.remove('is-active'); });
      current = el;
      Array.prototype.forEach.call(partner(el), function (p) { p.classList.add('is-active'); });

      var name = el.getAttribute('data-tip-name');
      if (!name) return;
      tip.innerHTML = '';
      add('span', 'c-map-region__tip-name', name);
      var authority = el.getAttribute('data-tip-authority');
      if (authority) add('span', 'c-map-region__tip-line', authority);
      var note = el.getAttribute('data-tip-note');
      if (note) add('span', 'c-map-region__tip-line', note);
      var value = el.getAttribute('data-tip-value');
      if (value) add('span', 'c-map-region__tip-value', value);
      var href = el.getAttribute('href');
      if (href) {
        var link = add('a', 'c-map-region__tip-open', openLabel);
        link.href = href;
        // The focused shape is itself the link, so the copy here is for
        // pointers only and stays out of the tab order.
        link.tabIndex = -1;
      }
      tip.hidden = false;
      place(el);
    }

    function add(tag, cls, text) {
      var node = document.createElement(tag);
      node.className = cls;
      node.textContent = text;
      tip.appendChild(node);
      return node;
    }

    function place(el) {
      var box = el.getBoundingClientRect();
      var frame = stage.getBoundingClientRect();
      var w = tip.offsetWidth;
      var h = tip.offsetHeight;
      var x = box.left - frame.left + box.width / 2 - w / 2;
      var y = box.top - frame.top - h - 8;
      if (y < 0) y = box.bottom - frame.top + 8;
      x = Math.max(0, Math.min(x, frame.width - w));
      tip.style.left = Math.round(x) + 'px';
      tip.style.top = Math.round(y) + 'px';
    }

    function scheduleHide() {
      clearTimeout(hideTimer);
      hideTimer = setTimeout(hide, 180);
    }

    function hide() {
      tip.hidden = true;
      if (current) Array.prototype.forEach.call(partner(current), function (p) { p.classList.remove('is-active'); });
      current = null;
    }

    // One pulse of the island dots on reveal.
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (!('IntersectionObserver' in window)) return;
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        figure.classList.add('is-revealed');
        observer.disconnect();
      });
    }, { threshold: 0.35 });
    observer.observe(stage);
  }
})();
