/*
 * The bell list (Day 3, notify). It is a details element and works with no
 * script; this only closes it on Escape, returning focus to the bell, and
 * on a click outside it.
 */
(function () {
  var bells = document.querySelectorAll('[data-bell]');
  if (!bells.length) return;

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    bells.forEach(function (bell) {
      if (bell.open) {
        bell.open = false;
        bell.querySelector('summary').focus();
      }
    });
  });

  document.addEventListener('click', function (event) {
    bells.forEach(function (bell) {
      if (bell.open && !bell.contains(event.target)) bell.open = false;
    });
  });
})();
