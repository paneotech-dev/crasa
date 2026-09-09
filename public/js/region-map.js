/* CRASA Digital Anchor Ecosystem — the region map.
 *
 * The map is already drawn, already shaded, already keyboard reachable and
 * already carries a table equivalent before this file loads. Everything
 * below is one class of improvement only: removing a click.
 *
 * Without this file the dataset selector is a GET form with an apply
 * button, which works. With it, changing the selector navigates, and the
 * button is removed because it now has nothing to do. That is the whole
 * enhancement. Nothing here draws anything, and nothing here is the only
 * way to reach a member state.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-map-control]');
  if (!form) return;

  var select = form.querySelector('select');
  var apply = form.querySelector('.c-map-region__apply');
  if (!select) return;

  // The button goes only once we know the change handler is attached, so a
  // script that throws on an older browser leaves the form intact.
  select.addEventListener('change', function () {
    form.submit();
  });

  if (apply) apply.remove();
})();
