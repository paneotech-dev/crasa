/* Events: month/list switch, committee and year filters in place, arrow
 * keys across the month grid, copy link on the event page. Every control is
 * a link or a button that works without this file. */
(function () {
  'use strict';

  function setParam(name, value) {
    try {
      var url = new URL(window.location.href);
      if (value) { url.searchParams.set(name, value); } else { url.searchParams.delete(name); }
      window.history.replaceState(null, '', url.toString());
    } catch (e) { /* old browser: the link still works */ }
  }

  function rewriteLinks(root, name, value) {
    root.querySelectorAll('.e-monthnav a, [data-view-switch], [data-year-filter]').forEach(function (a) {
      try {
        var url = new URL(a.href);
        if (value) { url.searchParams.set(name, value); } else { url.searchParams.delete(name); }
        a.href = url.toString();
      } catch (e) {}
    });
  }

  var section = document.querySelector('[data-events]');
  if (section) {
    var wide = window.matchMedia('(min-width: 768px)');
    var switches = section.querySelectorAll('[data-view-switch]');

    function markView() {
      var view = section.getAttribute('data-view');
      var shown = view === 'auto' ? (wide.matches ? 'month' : 'list') : view;
      switches.forEach(function (s) {
        if (s.getAttribute('data-view-switch') === shown) { s.setAttribute('aria-current', 'true'); } else { s.removeAttribute('aria-current'); }
      });
    }
    markView();
    // The legend stays open on wide screens and folds on phones.
    var legend = section.querySelector('[data-legend]');
    if (legend && !wide.matches) { legend.removeAttribute('open'); }
    if (wide.addEventListener) { wide.addEventListener('change', markView); }

    switches.forEach(function (s) {
      s.addEventListener('click', function (ev) {
        ev.preventDefault();
        var view = s.getAttribute('data-view-switch');
        section.setAttribute('data-view', view);
        setParam('view', view);
        markView();
        rewriteLinks(document, 'view', view);
      });
    });

    // Committee filter, in place.
    var status = document.querySelector('[data-past-status]');
    document.querySelectorAll('[data-filter]').forEach(function (chip) {
      chip.addEventListener('click', function (ev) {
        ev.preventDefault();
        var code = chip.getAttribute('data-filter');
        document.querySelectorAll('[data-filter]').forEach(function (c) {
          if (c === chip) { c.setAttribute('aria-current', 'true'); } else { c.removeAttribute('aria-current'); }
        });
        var shown = 0;
        document.querySelectorAll('.e-chip[data-group], .e-list__item[data-group], .e-cards > li[data-group]').forEach(function (el) {
          var match = !code || el.getAttribute('data-group') === code;
          el.hidden = !match;
          if (match && el.matches('.e-cards > li')) { shown++; }
        });
        document.querySelectorAll('.e-year').forEach(function (y) {
          var any = y.querySelector('.e-cards > li:not([hidden])');
          y.classList.toggle('e-year--empty', !any);
        });
        setParam('committee', code);
        rewriteLinks(document, 'committee', code);
        if (status) { status.textContent = chip.textContent.trim() + ': ' + shown; }
      });
    });

    // Arrow keys across the month grid.
    var grid = section.querySelector('[data-grid]');
    if (grid) {
      grid.addEventListener('keydown', function (ev) {
        var link = ev.target.closest('.e-chip');
        if (!link) { return; }
        var chips = Array.prototype.filter.call(grid.querySelectorAll('.e-chip'), function (c) { return !c.hidden; });
        var i = chips.indexOf(link);
        var target = null;
        if (ev.key === 'ArrowRight') { target = chips[i + 1]; }
        else if (ev.key === 'ArrowLeft') { target = chips[i - 1]; }
        else if (ev.key === 'Home') { target = chips[0]; }
        else if (ev.key === 'End') { target = chips[chips.length - 1]; }
        else if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
          var cell = link.closest('td');
          var day = new Date(cell.getAttribute('data-date') + 'T00:00:00');
          var step = ev.key === 'ArrowDown' ? 1 : -1;
          for (var n = 7; n <= 42 && !target; n++) {
            var d = new Date(day.getTime() + step * n * 86400000);
            var iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            var c = grid.querySelector('td[data-date="' + iso + '"]');
            if (!c) { continue; }
            target = Array.prototype.find.call(c.querySelectorAll('.e-chip'), function (x) { return !x.hidden; }) || null;
          }
        }
        if (target) { ev.preventDefault(); target.focus(); }
      });
    }
  }

  // Year filter for past meetings.
  document.querySelectorAll('[data-year-filter]').forEach(function (chip) {
    chip.addEventListener('click', function (ev) {
      ev.preventDefault();
      var year = chip.getAttribute('data-year-filter');
      document.querySelectorAll('[data-year-filter]').forEach(function (c) {
        if (c === chip) { c.setAttribute('aria-current', 'true'); } else { c.removeAttribute('aria-current'); }
      });
      document.querySelectorAll('.e-year').forEach(function (y) {
        y.hidden = !!year && y.getAttribute('data-year') !== year;
      });
      setParam('year', year);
    });
  });

  // Copy link on the event page.
  document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-copy-link');
      var done = function () {
        var out = document.getElementById(btn.getAttribute('aria-controls'));
        if (out) { out.textContent = btn.getAttribute('data-copied'); }
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done, done);
      } else {
        var t = document.createElement('textarea');
        t.value = url; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(t); done();
      }
    });
  });
})();

/* Chip preview on the month calendar (evening pass): a native <dialog>
 * with the event's title, dates, place, committee, Add to my calendar and
 * Open. Focus moves to the title, Escape closes (native), focus returns to
 * the chip. A modified click (new tab) and no script keep the plain link. */
(function () {
  'use strict';
  var dialog = document.querySelector('[data-preview-dialog]');
  if (!dialog || typeof dialog.showModal !== 'function') { return; }
  var body = dialog.querySelector('[data-preview-body]');
  var opener = null;

  document.querySelectorAll('a[data-preview]').forEach(function (chip) {
    chip.addEventListener('click', function (ev) {
      if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) { return; }
      var tpl = document.getElementById(chip.getAttribute('data-preview'));
      if (!tpl || !tpl.content) { return; }
      ev.preventDefault();
      body.textContent = '';
      body.appendChild(tpl.content.cloneNode(true));
      opener = chip;
      dialog.showModal();
      var title = body.querySelector('#e-pv-title');
      if (title) { title.focus(); }
    });
  });

  dialog.querySelector('[data-preview-close]').addEventListener('click', function () { dialog.close(); });
  // A click on the backdrop (the dialog box itself, outside the inner panel) closes.
  dialog.addEventListener('click', function (ev) { if (ev.target === dialog) { dialog.close(); } });
  dialog.addEventListener('close', function () {
    body.textContent = '';
    if (opener) { opener.focus(); opener = null; }
  });
})();
