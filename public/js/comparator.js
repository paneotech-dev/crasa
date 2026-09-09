/* CRASA regulatory comparator — the React layer.
 *
 * WHAT THIS DOES, AND WHAT IT DELIBERATELY DOES NOT.
 *
 * The comparator is already complete before this file runs. Every control
 * is a link, every state has a URL, and the server renders the whole
 * comparison. That is not a fallback: it is the version that prints, the
 * version a screen reader gets, and the version that makes a pasted
 * permalink resolve to figures rather than to a spinner.
 *
 * This file makes selection feel immediate. It intercepts the links,
 * fetches the same JSON the page was built from, swaps the result panel and
 * pushes the new URL, so the address bar still describes what is on screen.
 * If it never loads, nothing is lost but the speed.
 *
 * React 18, per the stack. No JSX and no build step: the platform serves
 * static CSS and Blade, and a bundler for one component would be a second
 * toolchain to keep in step with the design.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-comparator]');
  if (!root || !window.React || !window.ReactDOM) return;

  var h = window.React.createElement;
  var useState = window.React.useState;
  var useEffect = window.React.useEffect;

  /* The strings the component needs, read from the document rather than
     duplicated here, so there is one set of translations. */
  var t = function (key, fallback) {
    var el = document.querySelector('[data-cmp-string="' + key + '"]');
    return el ? el.textContent.trim() : fallback;
  };

  function Cell(props) {
    var cell = props.cell;
    if (!cell) return null;

    return h('td', null,
      h('span', {
        className: 'c-cmp__value',
        'data-missing': cell.reported ? undefined : ''
      }, cell.display),
      h('span', { className: 'c-cmp__pill', 'data-provenance': cell.provenance }, props.provenanceLabel(cell.provenance)),
      cell.source ? h('span', { className: 'c-cmp__source' }, cell.source + (cell.period ? ', ' + cell.period : '')) : null
    );
  }

  function Matrix(props) {
    var data = props.data;

    return h('div', { className: 'c-cmp__tablewrap c-cmp__wide' },
      h('table', { className: 'c-cmp__table c-cmp__table--matrix' },
        h('thead', null,
          h('tr', null,
            h('th', { scope: 'col', className: 'c-cmp__sticky' }, props.labels.indicator),
            data.countries.map(function (country) {
              return h('th', { scope: 'col', key: country.code },
                h('span', { className: 'c-cmp__state-cell' },
                  h('span', { className: 'c-cmp__code c-cmp__code--on-navy' }, country.code),
                  h('span', { className: 'c-cmp__state-names' },
                    h('span', { className: 'c-cmp__state-name' }, country.name),
                    h('span', { className: 'c-cmp__state-acr' }, country.authority || '')
                  )
                )
              );
            })
          )
        ),
        h('tbody', null,
          data.indicators.map(function (indicator) {
            return h('tr', { key: indicator.slug },
              h('th', { scope: 'row', className: 'c-cmp__sticky' },
                h('span', { className: 'c-cmp__indicator-name' }, indicator.name),
                h('span', { className: 'c-cmp__indicator-unit' }, indicator.unit || '')
              ),
              indicator.cells.map(function (cell, i) {
                return h(Cell, {
                  key: cell ? cell.country : i,
                  cell: cell,
                  provenanceLabel: props.provenanceLabel
                });
              })
            );
          })
        )
      )
    );
  }

  /* The same figures as cards, for the same reason the server renders both:
     screen 03 transposes below 720px rather than scrolling sideways. */
  function Cards(props) {
    var data = props.data;

    return h('div', { className: 'c-cmp__narrow' },
      data.indicators.map(function (indicator) {
        return h('article', { className: 'c-cmp__card', key: indicator.slug },
          h('h3', null, indicator.name),
          h('p', { className: 'c-cmp__indicator-unit' }, indicator.unit || ''),
          indicator.cells.map(function (cell, i) {
            if (!cell) return null;
            var country = data.countries[i];
            return h(window.React.Fragment, { key: cell.country },
              h('div', { className: 'c-cmp__card-row' },
                h('span', { className: 'c-cmp__card-state' }, country ? country.name : cell.country),
                h('span', {
                  className: 'c-cmp__card-value',
                  'data-missing': cell.reported ? undefined : ''
                }, cell.display)
              ),
              cell.source ? h('p', { className: 'c-cmp__source c-cmp__card-source' },
                cell.source + (cell.period ? ', ' + cell.period : '')) : null
            );
          })
        );
      })
    );
  }

  function Comparator(props) {
    var state = useState({ data: null, loading: false, error: null });
    var current = state[0];
    var setCurrent = state[1];

    var load = function (endpoint, push) {
      setCurrent({ data: current.data, loading: true, error: null });

      window.fetch(endpoint, { headers: { Accept: 'application/json' } })
        .then(function (response) {
          if (!response.ok) throw new Error(String(response.status));
          return response.json();
        })
        .then(function (data) {
          setCurrent({ data: data, loading: false, error: null });
          /* The server-rendered result steps aside only once there is a
             live one to replace it, so the panel is never empty. */
          root.setAttribute('data-live', '');
          if (push) window.history.pushState({ endpoint: endpoint }, '', data.permalink);
        })
        .catch(function () {
          /* A failed fetch falls back to a full page load, which is the
             version that always works. Nothing is left broken on screen. */
          setCurrent({ data: null, loading: false, error: true });
          window.location.href = props.permalinkFor(endpoint);
        });
    };

    useEffect(function () {
      var onClick = function (event) {
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || !root.parentNode.contains(link)) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        if (link.getAttribute('aria-disabled') === 'true') { event.preventDefault(); return; }

        var url = new URL(link.href, window.location.origin);
        if (url.pathname !== window.location.pathname) return;

        event.preventDefault();
        load(url.pathname.replace(/\/comparator$/, '/comparator/data') + url.search, true);
      };

      var container = root.closest('.c-cmp') || document;
      container.addEventListener('click', onClick);

      var onPop = function () { window.location.reload(); };
      window.addEventListener('popstate', onPop);

      return function () {
        container.removeEventListener('click', onClick);
        window.removeEventListener('popstate', onPop);
      };
    });

    if (!current.data) return null;

    return h('div', { 'aria-busy': current.loading ? 'true' : 'false' },
      h(Matrix, { data: current.data, labels: props.labels, provenanceLabel: props.provenanceLabel }),
      h(Cards, { data: current.data, provenanceLabel: props.provenanceLabel })
    );
  }

  /*
   * Mounted beside the server-rendered result, not over it.
   *
   * React renders nothing until a selection changes, so the markup the
   * server sent is what a reader sees on arrival and what the printer
   * prints. Only once the reader picks a different member state does the
   * component take the panel over.
   */
  var mount = document.createElement('div');
  mount.setAttribute('data-comparator-live', '');
  mount.className = 'c-cmp__result';
  root.appendChild(mount);

  var labels = {
    indicator: t('col_indicator', 'Indicator')
  };

  var provenanceLabel = function (key) {
    return t('provenance_' + key, key);
  };

  var permalinkFor = function (endpoint) {
    return endpoint.replace('/comparator/data', '/comparator');
  };

  var reactRoot = window.ReactDOM.createRoot(mount);

  reactRoot.render(h(Comparator, {
    endpoint: root.getAttribute('data-endpoint'),
    labels: labels,
    provenanceLabel: provenanceLabel,
    permalinkFor: permalinkFor
  }));
})();
