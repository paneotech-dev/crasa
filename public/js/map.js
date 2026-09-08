/* CRASA Digital Anchor Ecosystem — contact map.
 *
 * Click to load. Map tiles come from a third-party server, so requesting
 * them sends the reader's IP address off this platform. Nothing loads until
 * the reader asks, and the Secretariat's address is readable as text whether
 * the map ever loads or not.
 *
 * Leaflet itself is served from this platform, not from a CDN.
 */
(function () {
  'use strict';

  var container = document.querySelector('[data-map]');
  if (!container) return;

  var trigger = container.querySelector('[data-map-load]');
  if (!trigger) return;

  function loadStylesheet(href) {
    return new Promise(function (resolve, reject) {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      link.onload = resolve;
      link.onerror = reject;
      document.head.appendChild(link);
    });
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = src;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  trigger.addEventListener('click', function () {
    trigger.disabled = true;

    Promise.all([
      loadStylesheet('/css/vendor/leaflet.css'),
      loadScript('/js/vendor/leaflet.js')
    ]).then(function () {
      var canvas = document.createElement('div');
      canvas.className = 'c-map__canvas';
      canvas.setAttribute('role', 'application');
      canvas.setAttribute('aria-label', container.getAttribute('data-label'));
      container.innerHTML = '';
      container.appendChild(canvas);

      var lat = parseFloat(container.getAttribute('data-lat'));
      var lng = parseFloat(container.getAttribute('data-lng'));
      var map = window.L.map(canvas).setView([lat, lng], parseInt(container.getAttribute('data-zoom'), 10));

      window.L.tileLayer(container.getAttribute('data-tiles'), {
        attribution: container.getAttribute('data-attribution'),
        maxZoom: 18
      }).addTo(map);

      window.L.marker([lat, lng]).addTo(map).bindPopup(container.getAttribute('data-label'));
    }).catch(function () {
      trigger.disabled = false;
      // The address above is still readable. A failed map is not a failed page.
    });
  });
})();
