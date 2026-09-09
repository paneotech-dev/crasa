/* CRASA Digital Anchor Ecosystem — predictive search. WS-04.
 *
 * THE FLOOR IS THE FIELD. With this file absent the search input takes a
 * query and the form submits it. Nothing below is required to search, and
 * nothing below is the only way to reach a result.
 *
 * With it, the input becomes an ARIA combobox (pattern 1.2): a listbox of
 * suggestions the reader moves through with the arrow keys, takes with
 * Enter or Tab, and dismisses with Escape. The count is announced politely,
 * because a dropdown that only appears is a dropdown a screen reader user
 * is not told about.
 *
 * SCOPE. The suggestions are whatever the server returns, and the server
 * returns only titles this reader could open — Instrument::published(),
 * Publication::listed(), NewsItem::published(). Autocomplete is the usual
 * way a title leaks out of an authorisation layer, because nothing is being
 * "viewed" yet and the policies never run. That is fixed on the server,
 * where it belongs, and not by hiding rows here.
 */
(function () {
  'use strict';

  var input = document.querySelector('[data-search-input]');
  var list = document.querySelector('[data-suggestions]');
  var status = document.querySelector('[data-suggest-status]');
  if (!input || !list || !window.fetch) return;

  var url = input.getAttribute('data-suggest-url');
  if (!url) return;

  var timer = null;
  var lastQuery = '';
  var items = [];
  var active = -1;

  // Announced only once the combobox behaviour is actually wired up, so a
  // reader is never told about a listbox that will not open.
  input.setAttribute('role', 'combobox');
  input.setAttribute('aria-expanded', 'false');
  input.setAttribute('aria-controls', list.id);
  input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-haspopup', 'listbox');

  input.addEventListener('input', function () {
    var value = input.value.trim();

    if (value.length < 2) {
      close();
      lastQuery = '';
      return;
    }

    if (value === lastQuery) return;

    // Debounced. A regulator types a whole phrase; one request per
    // keystroke would be fourteen requests for "spectrum harmonisation".
    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      lastQuery = value;
      request(value);
    }, 180);
  });

  input.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      // Escape closes the list and leaves the query alone. A reader who
      // wanted the field cleared has a second Escape and the browser's own
      // clear control for that.
      if (isOpen()) {
        event.preventDefault();
        close();
      }
      return;
    }

    if (!isOpen()) {
      if (event.key === 'ArrowDown' && items.length) {
        event.preventDefault();
        open();
        move(0);
      }
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      move(active + 1 >= items.length ? 0 : active + 1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      move(active - 1 < 0 ? items.length - 1 : active - 1);
    } else if (event.key === 'Home') {
      event.preventDefault();
      move(0);
    } else if (event.key === 'End') {
      event.preventDefault();
      move(items.length - 1);
    } else if (event.key === 'Enter') {
      if (active >= 0) {
        // Take the suggestion and search for it. The form still submits, so
        // the reader lands on a real, linkable results URL.
        event.preventDefault();
        take(active);
        input.form.submit();
      }
    } else if (event.key === 'Tab') {
      if (active >= 0) take(active);
      close();
    }
  });

  list.addEventListener('mousedown', function (event) {
    // mousedown rather than click: blur fires first on a click and would
    // have closed the list out from under the pointer.
    var option = event.target.closest ? event.target.closest('[role="option"]') : null;
    if (!option) return;

    event.preventDefault();
    take(items.indexOf(option));
    input.form.submit();
  });

  input.addEventListener('blur', function () {
    window.setTimeout(close, 120);
  });

  function request(value) {
    fetch(url + '?q=' + encodeURIComponent(value), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (response) { return response.ok ? response.json() : { suggestions: [] }; })
      .then(function (data) { render(data.suggestions || [], data.announce || ''); })
      .catch(function () {
        // Suggestions are a convenience, not the feature. The field still
        // searches.
        close();
      });
  }

  function render(suggestions, announce) {
    list.innerHTML = '';
    items = [];
    active = -1;

    suggestions.forEach(function (suggestion, index) {
      var option = document.createElement('li');
      option.id = 'search-suggestion-' + index;
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', 'false');
      option.className = 'c-suggest__option';
      option.textContent = suggestion;
      list.appendChild(option);
      items.push(option);
    });

    if (status) status.textContent = announce;

    if (items.length) open();
    else close();
  }

  function move(index) {
    if (active >= 0 && items[active]) {
      items[active].setAttribute('aria-selected', 'false');
      items[active].classList.remove('is-active');
    }

    active = index;
    var option = items[active];
    if (!option) return;

    option.setAttribute('aria-selected', 'true');
    option.classList.add('is-active');
    // The reader's focus never leaves the input, so the listbox says which
    // option is current rather than moving focus into it.
    input.setAttribute('aria-activedescendant', option.id);

    if (option.scrollIntoView) option.scrollIntoView({ block: 'nearest' });
  }

  function take(index) {
    if (!items[index]) return;
    input.value = items[index].textContent;
    close();
  }

  function isOpen() {
    return input.getAttribute('aria-expanded') === 'true';
  }

  function open() {
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }

  function close() {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');

    if (active >= 0 && items[active]) {
      items[active].setAttribute('aria-selected', 'false');
      items[active].classList.remove('is-active');
    }

    active = -1;
  }
})();
