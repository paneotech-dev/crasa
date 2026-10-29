/* CRASA public header: the phone and tablet drawer, and the share button
 * in the breadcrumb row (Concept 3 port, 29 October 2026).
 *
 * Drawer: the Menu button opens it; focus moves to its first control and
 * stays inside while it is open; Escape, the close button and the scrim
 * close it and focus returns to the Menu button. Each section is an
 * accordion button with aria-expanded.
 *
 * Share: the system share sheet where there is one, otherwise the address
 * is copied and "Link copied" is announced.
 */
(function () {
  'use strict';

  var drawer = document.querySelector('[data-k-drawer]');
  var opener = document.querySelector('[data-k-drawer-open]');
  var scrim = document.querySelector('.k-scrim');

  if (drawer && opener) {
    var lastFocus = null;

    var focusables = function () {
      return Array.prototype.slice.call(drawer.querySelectorAll('a[href], button, input, [tabindex]:not([tabindex="-1"])'))
        .filter(function (el) { return el.offsetParent !== null; });
    };

    var openDrawer = function () {
      lastFocus = document.activeElement;
      drawer.hidden = false;
      if (scrim) scrim.hidden = false;
      // A frame so the transition runs from the hidden state.
      requestAnimationFrame(function () {
        drawer.classList.add('is-on');
        if (scrim) scrim.classList.add('is-on');
      });
      opener.setAttribute('aria-expanded', 'true');
      document.documentElement.classList.add('k-drawer-open');
      var first = drawer.querySelector('[data-k-drawer-close]');
      if (first) first.focus();
    };

    var closeDrawer = function () {
      drawer.classList.remove('is-on');
      if (scrim) scrim.classList.remove('is-on');
      opener.setAttribute('aria-expanded', 'false');
      document.documentElement.classList.remove('k-drawer-open');
      setTimeout(function () {
        drawer.hidden = true;
        if (scrim) scrim.hidden = true;
      }, 260);
      (lastFocus || opener).focus();
    };

    opener.addEventListener('click', openDrawer);
    Array.prototype.forEach.call(document.querySelectorAll('[data-k-drawer-close]'), function (el) {
      el.addEventListener('click', closeDrawer);
    });

    drawer.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeDrawer();
        return;
      }
      if (event.key !== 'Tab') return;
      var list = focusables();
      if (!list.length) return;
      var first = list[0];
      var last = list[list.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });

    Array.prototype.forEach.call(drawer.querySelectorAll('[data-k-acc]'), function (acc) {
      var button = acc.querySelector('[data-k-acc-toggle]');
      button.addEventListener('click', function () {
        var open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        acc.classList.toggle('is-open', open);
      });
    });

    // The section the reader is in starts open.
    Array.prototype.forEach.call(drawer.querySelectorAll('[data-k-acc]'), function (acc) {
      if (acc.querySelector('[aria-current="page"]')) {
        acc.classList.add('is-open');
        acc.querySelector('[data-k-acc-toggle]').setAttribute('aria-expanded', 'true');
      }
    });

    // Back at desktop width the drawer has nothing to do.
    var wide = window.matchMedia('(min-width: 1100px)');
    var onWide = function () { if (wide.matches && !drawer.hidden) closeDrawer(); };
    if (wide.addEventListener) wide.addEventListener('change', onWide);
  }

  var share = document.querySelector('[data-k-share]');
  if (share) {
    var live = document.querySelector('[data-k-share-live]');
    share.addEventListener('click', function () {
      var data = { title: document.title, url: location.href };
      if (navigator.share) {
        navigator.share(data).catch(function () {});
        return;
      }
      var done = function () {
        if (live) {
          live.textContent = '';
          setTimeout(function () { live.textContent = share.getAttribute('data-copied'); }, 50);
        }
        share.classList.add('is-done');
        setTimeout(function () { share.classList.remove('is-done'); }, 1600);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(location.href).then(done, done);
      } else {
        done();
      }
    });
  }
})();
