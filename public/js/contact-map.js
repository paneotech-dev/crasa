/* CRASA: contact map (29 October 2026). Drawn on load over the saved image
 * of the same area. Leaflet is served from this platform. If the tile server
 * fails, the live layer is removed and the saved image with its pin stays.
 */
(function () {
  'use strict';

  var box = document.querySelector('[data-contact-map]');
  if (!box) return;

  function load(tag, attrs) {
    return new Promise(function (resolve, reject) {
      var el = document.createElement(tag);
      for (var k in attrs) el[k] = attrs[k];
      el.onload = resolve;
      el.onerror = reject;
      document.head.appendChild(el);
    });
  }

  function draw() {
    Promise.all([
      window.L ? Promise.resolve() : load('link', { rel: 'stylesheet', href: '/css/vendor/leaflet.css' }),
      window.L ? Promise.resolve() : load('script', { src: '/js/vendor/leaflet.js' })
    ]).then(function () {
      var L = window.L;
      var lat = parseFloat(box.getAttribute('data-lat'));
      var lng = parseFloat(box.getAttribute('data-lng'));
      var canvas = document.createElement('div');
      canvas.className = 'cp-map__canvas';
      canvas.setAttribute('role', 'region');
      canvas.setAttribute('aria-label', box.getAttribute('data-label'));
      box.appendChild(canvas);

      var map = L.map(canvas, { scrollWheelZoom: false }).setView([lat, lng], parseInt(box.getAttribute('data-zoom'), 10) || 15);
      var failures = 0;
      var layer = L.tileLayer(box.getAttribute('data-tiles'), {
        attribution: box.getAttribute('data-attribution'),
        maxZoom: 18
      });
      layer.on('tileerror', function () {
        failures += 1;
        if (failures === 3) {
          map.remove();
          canvas.remove();
          box.classList.add('is-static');
        }
      });
      layer.on('load', function () { box.classList.add('is-live'); });
      layer.addTo(map);

      var pin = L.divIcon({
        className: 'cp-map__marker',
        html: '<svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><path d="M12 23s8-7.2 8-13.5A8 8 0 0 0 4 9.5C4 15.8 12 23 12 23z" fill="#0F8757" stroke="#fff" stroke-width="1.5"/><circle cx="12" cy="9.5" r="3" fill="#fff"/></svg>',
        iconSize: [40, 40],
        iconAnchor: [20, 38]
      });
      L.marker([lat, lng], { icon: pin, title: box.getAttribute('data-label'), alt: box.getAttribute('data-label') }).addTo(map);
    }).catch(function () {
      box.classList.add('is-static');
    });
  }

  draw();
})();
