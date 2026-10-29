/* Back office events: the form in steps with a summary card, and the
 * delete confirmation. Without this file every step shows at once in
 * order and Delete opens a confirmation page. */
(function () {
  'use strict';

  // ---- Delete confirmation ---------------------------------------------
  var dialog = document.getElementById('event-delete');
  var opener = null;
  if (dialog && typeof dialog.showModal === 'function') {
    document.addEventListener('click', function (ev) {
      var link = ev.target.closest('[data-delete-event]');
      if (!link) { return; }
      ev.preventDefault();
      opener = link;
      dialog.querySelector('[data-delete-form]').setAttribute('action', link.getAttribute('data-action'));
      var body = dialog.querySelector('[data-delete-body]');
      body.textContent = body.getAttribute('data-template').replace('__TITLE__', link.getAttribute('data-title'));
      dialog.showModal();
      var keep = dialog.querySelector('[data-modal-close]');
      if (keep) { keep.focus(); }
    });
    dialog.addEventListener('close', function () {
      if (opener) { opener.focus(); opener = null; }
    });
    dialog.querySelectorAll('[data-modal-close]').forEach(function (b) {
      b.addEventListener('click', function () { dialog.close(); });
    });
  }

  // ---- Steps -------------------------------------------------------------
  var form = document.querySelector('[data-steps]');
  if (!form) { return; }

  var steps = Array.prototype.slice.call(form.querySelectorAll('.e-step'));
  var stepper = form.querySelector('[data-stepper]');
  var status = form.querySelector('[data-step-status]');
  var back = form.querySelector('[data-step-back]');
  var next = form.querySelector('[data-step-next]');
  var save = form.querySelector('[data-step-save]');
  var groups = JSON.parse((form.querySelector('[data-groups]') || {}).textContent || '{}');
  var labels = JSON.parse((form.querySelector('[data-labels]') || {}).textContent || '{}');
  var current = 1;

  form.classList.add('e-steps--js');
  stepper.hidden = false;
  back.hidden = false;
  next.hidden = false;

  function val(name) {
    var el = form.elements[name];
    return el ? String(el.value || '').trim() : '';
  }

  function fmt(iso, opts) {
    if (!iso) { return ''; }
    var d = new Date(iso + 'T00:00:00');
    var lang = document.documentElement.lang || 'en'; try { return d.toLocaleDateString(lang === 'en' ? 'en-GB' : lang, opts); } catch (e) { return iso; }
  }

  function buildPreview() {
    var tpl = form.querySelector('[data-preview-template]');
    var out = form.querySelector('[data-preview]');
    if (!tpl || !out) { return; }
    var card = tpl.content.cloneNode(true);
    var set = function (k, text) { var el = card.querySelector('[data-p="' + k + '"]'); if (el) { el.textContent = text; } return el; };
    var start = val('starts_on'), end = val('ends_on') || start;
    set('day', start ? fmt(start, { day: 'numeric' }) : '');
    set('month', start ? fmt(start, { month: 'short' }) : '');
    set('year', start ? start.slice(0, 4) : '');
    var code = val('committee_code') || 'CRASA';
    var g = groups[code] || groups.CRASA || { name: code, colour: '#25384A' };
    var badge = set('badge', code); if (badge) { badge.style.setProperty('--e-c', g.colour); }
    set('group', g.name);
    set('title', val('title') || labels.empty);
    var dates = start ? (start === end ? fmt(start, { day: 'numeric', month: 'long', year: 'numeric' }) : fmt(start, { day: 'numeric', month: 'long' }) + ' – ' + fmt(end, { day: 'numeric', month: 'long', year: 'numeric' })) : labels.empty;
    set('dates', dates);
    var place = val('location');
    set('place', place && place.toLowerCase() === 'online' ? labels.online : (place || labels.empty));
    set('visibility', val('clearance') === 'public' ? labels['public'] : labels.members);
    out.innerHTML = '';
    out.appendChild(card);

    var list = form.querySelector('[data-summary-list]');
    list.innerHTML = '';
    form.querySelectorAll('[data-summary]').forEach(function (el) {
      if (el.name === 'status_note') { return; }
      var v = el.tagName === 'SELECT' ? (el.selectedOptions[0] ? el.selectedOptions[0].textContent : '') : el.value;
      if (el.tagName === 'SELECT' && el.value === '') { v = ''; }
      var dt = document.createElement('dt'); dt.textContent = el.getAttribute('data-summary');
      var dd = document.createElement('dd'); dd.textContent = (v || '').trim() || labels.empty;
      list.appendChild(dt); list.appendChild(dd);
    });
    var files = form.elements['files[]'];
    if (files && files.files && files.files.length) {
      var dt2 = document.createElement('dt'); dt2.textContent = files.labels && files.labels[0] ? files.labels[0].firstChild.textContent.trim() : 'Files';
      var dd2 = document.createElement('dd'); dd2.textContent = Array.prototype.map.call(files.files, function (f) { return f.name; }).join(', ');
      list.appendChild(dt2); list.appendChild(dd2);
    }
  }

  function show(n, focus) {
    current = Math.max(1, Math.min(steps.length, n));
    steps.forEach(function (s, i) { s.hidden = i + 1 !== current; });
    stepper.querySelectorAll('[data-goto]').forEach(function (b) {
      var k = +b.getAttribute('data-goto');
      if (k === current) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); }
      b.classList.toggle('is-done', k < current);
    });
    back.hidden = current === 1;
    next.hidden = current === steps.length;
    save.hidden = current !== steps.length;
    if (current === steps.length) { buildPreview(); }
    var legend = steps[current - 1].querySelector('.e-step__legend');
    if (status && legend) { status.textContent = legend.textContent.replace(/\s+/g, ' ').trim(); }
    if (focus && legend) { legend.setAttribute('tabindex', '-1'); legend.focus(); }
  }

  // A step is checked before moving on: required fields filled, and the
  // browser's own checks on dates, times and addresses.
  function valid(n) {
    var bad = null;
    steps[n - 1].querySelectorAll('input, select, textarea').forEach(function (el) {
      if (!bad && el.willValidate && !el.checkValidity()) { bad = el; }
    });
    if (bad) { bad.reportValidity(); bad.focus(); return false; }
    return true;
  }

  next.addEventListener('click', function () { if (valid(current)) { show(current + 1, true); } });
  back.addEventListener('click', function () { show(current - 1, true); });
  stepper.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-goto]');
    if (!b) { return; }
    var k = +b.getAttribute('data-goto');
    if (k > current && !valid(current)) { return; }
    show(k, true);
  });

  // After a failed save, open the step with the first error.
  var firstError = form.querySelector('[aria-invalid="true"]');
  var start = 1;
  if (firstError) {
    var holder = firstError.closest('.e-step');
    start = holder ? +holder.getAttribute('data-step') : 1;
  }
  show(start, false);
  if (firstError) { firstError.focus(); }

  // An error summary link opens the step that holds the field.
  document.querySelectorAll('[data-errors] a').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      var target = document.getElementById(a.getAttribute('href').slice(1));
      if (!target) { return; }
      ev.preventDefault();
      var holder = target.closest('.e-step');
      if (holder) { show(+holder.getAttribute('data-step'), false); }
      target.focus();
    });
  });
})();
