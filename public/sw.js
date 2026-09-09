/* CRASA Digital Anchor Ecosystem — service worker. WS-02, screen 15.
 *
 * WHAT IS CACHED, AND WHAT IS NEVER, is not a performance decision here. It
 * is the zero-trust requirement, and screen 15 states it in its own words:
 *
 *   Cached.       The interface shell in all three languages, your agendas,
 *                 and any document you explicitly save.
 *   Never cached. Documents graded Confidential or Secretariat. They require
 *                 a live authenticated session, so they cannot be left on a
 *                 device that is later lost.
 *
 * So this worker will not cache anything behind authentication, at all, ever
 * — not the extranet, not the back office, not a submission, not a document
 * that is not explicitly saved. A delegate's phone is a shared and lost
 * device, and a cache is a copy that outlives the session that fetched it.
 *
 * The rule is enforced by URL and by response, not by trust: a path under a
 * protected prefix is refused, and so is any response carrying a
 * Cache-Control of private or no-store, whatever its path.
 *
 * Documents are cached only when the reader asks, through "Save for
 * offline". Nothing is downloaded to a device because the reader happened to
 * open a page.
 */

/* Bumped from v1 on 9 September 2026. A v1 cache holds the stylesheets under
   their old unversioned URLs — /css/blocks.css with no query — which is the
   copy that rendered the map black and the section bands as stacked text.
   Changing this name makes the activate handler delete every one of them. */
const VERSION = 'crasa-v2';
const SHELL = VERSION + '-shell';
const PAGES = VERSION + '-pages';
const SAVED = VERSION + '-saved';

/* The shell, in all three languages. Screen 15: "The interface shell in all
   three languages". Each locale's offline page is precached so a reader who
   goes offline in Portuguese does not meet an English error. */
const SHELL_URLS = [
  '/img/crasa-logo.svg',
  '/img/app/icon-192.png',
  '/en/offline',
  '/fr/offline',
  '/pt/offline'
];

/* The stylesheets and scripts are deliberately NOT in that list.
 *
 * They used to be, by their bare paths. Pages now link them with a version
 * query -- /css/blocks.css?v=1757430000 -- and caches.match honours the
 * query string, so a precached /css/blocks.css could never satisfy a
 * request for the versioned URL. It sat in the cache being neither used nor
 * evicted, while the offline page it was meant to style rendered bare.
 *
 * Precaching them with versions would mean this file knowing the mtimes,
 * which a static script cannot. The cache-first handler below stores each
 * one the first time a page asks for it, which is before any reader can
 * reach the offline page: they have to load a page to install the app. */

/* Never touched. Anything under these, whatever it looks like: not cached,
   not served from cache, not read. Every one is behind a sign-in.

   /documents is deliberately NOT here, and that is the interesting part.
   The download route serves documents of every clearance, so blocking it
   wholesale would also block the one feature screen 15 asks for. Instead
   the worker asks the server, which knows the clearance and already says so
   in the response:

     public document   Cache-Control: public, max-age=3600
     anything else     Cache-Control: no-store, no-cache, must-revalidate, private

   A Confidential document is refused because the server declared it
   private, not because a path list happened to be right. The server is the
   authority on clearance and the worker does not second-guess it. */
const PRIVATE_PREFIXES = ['/admin', '/extranet', '/submissions', '/account', '/login', '/two-factor', '/completeness'];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(SHELL).then(function (cache) {
      // addAll fails the whole install if one URL 404s, which would leave
      // the worker never activating and no offline page at all. Added one
      // at a time so a missing asset costs that asset and nothing else.
      return Promise.all(SHELL_URLS.map(function (url) {
        return cache.add(url).catch(function () { /* that one asset only */ });
      }));
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (names) {
      return Promise.all(names.map(function (name) {
        // Anything from an older version goes, including saved documents:
        // a document saved against an old shell may no longer be one the
        // reader is cleared for.
        return name.indexOf(VERSION) === 0 ? null : caches.delete(name);
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

function isPrivate(url) {
  // The locale segment sits in front of every path, so match on the
  // segment after it rather than on the start of the string.
  var path = url.pathname.replace(/^\/[a-z]{2}(?=\/|$)/, '');

  return PRIVATE_PREFIXES.some(function (prefix) {
    return path === prefix || path.indexOf(prefix + '/') === 0;
  });
}

function isCacheable(response) {
  if (!response || !response.ok || response.type === 'opaque') return false;

  // no-store means no store. Laravel marks every page "no-cache, private"
  // because they carry a session, so "private" alone cannot be the test: it
  // would refuse the whole site. no-store is what the document controller
  // sends for anything above public clearance.
  return !/no-store/i.test(response.headers.get('Cache-Control') || '');
}

/* A document may be kept on a device only if the server said so.
 *
 * Not "did not forbid it" — said so. An explicit public is the server
 * asserting that this file is publishable and may outlive the session that
 * fetched it, and only the server knows the clearance. Anything without it
 * is refused, including anything ambiguous. */
function isKeepable(response) {
  if (!isCacheable(response)) return false;

  return /(^|,|\s)public(\s|,|;|$)/i.test(response.headers.get('Cache-Control') || '');
}

self.addEventListener('fetch', function (event) {
  var request = event.request;

  if (request.method !== 'GET') return;

  var url = new URL(request.url);

  if (url.origin !== self.location.origin) return;

  // Never touched. Not cached, not served from cache, not read.
  if (isPrivate(url)) return;

  // A document the reader explicitly saved. Cache first, because the
  // reason they saved it is that they expect it without a connection.
  if (request.headers.get('X-Saved-Document') === '1') {
    event.respondWith(caches.match(request).then(function (hit) {
      return hit || fetch(request);
    }));

    return;
  }

  // Assets: cache first, keyed on the full URL including the version query.
  // A changed file is a changed URL, so this can never serve a stale
  // stylesheet, and a stylesheet fetched over hotel wifi on every page is
  // the difference between usable and not.
  if (/\.(css|js|woff2?|svg|png|jpg|webp|ico)$/i.test(url.pathname)) {
    event.respondWith(
      caches.match(request).then(function (hit) {
        return hit || fetch(request).then(function (response) {
          if (isCacheable(response)) {
            var copy = response.clone();
            caches.open(SHELL).then(function (cache) { cache.put(request, copy); });
          }

          return response;
        });
      })
    );

    return;
  }

  // Content: network first. A regulator must never read a superseded
  // instrument because a cache was quicker, so the network always wins
  // when there is one.
  event.respondWith(
    fetch(request).then(function (response) {
      if (isCacheable(response)) {
        var copy = response.clone();
        caches.open(PAGES).then(function (cache) { cache.put(request, copy); });
      }

      return response;
    }).catch(function () {
      return caches.match(request).then(function (hit) {
        if (hit) return hit;

        // The offline page in the reader's own language, not an English
        // one and not the browser's error.
        var locale = (url.pathname.match(/^\/([a-z]{2})(?=\/|$)/) || [])[1] || 'en';

        return caches.match('/' + locale + '/offline').then(function (page) {
          return page || caches.match('/en/offline');
        });
      });
    })
  );
});

/* Saving a document for offline, on the reader's explicit request. */
self.addEventListener('message', function (event) {
  var data = event.data || {};

  if (data.type === 'save-document' && data.url) {
    var url = new URL(data.url, self.location.origin);

    // The page asks; the worker still checks. A page can be wrong, and a
    // Confidential document on a lost phone is the failure this exists to
    // prevent.
    if (isPrivate(url)) {
      event.source && event.source.postMessage({ type: 'save-refused', url: data.url });

      return;
    }

    event.waitUntil(
      fetch(data.url).then(function (response) {
        if (!isCacheable(response)) {
          event.source && event.source.postMessage({ type: 'save-refused', url: data.url });

          return;
        }

        return caches.open(SAVED).then(function (cache) {
          return cache.put(data.url, response).then(function () {
            event.source && event.source.postMessage({ type: 'save-done', url: data.url });
          });
        });
      }).catch(function () {
        event.source && event.source.postMessage({ type: 'save-failed', url: data.url });
      })
    );
  }

  if (data.type === 'forget-document' && data.url) {
    event.waitUntil(caches.open(SAVED).then(function (cache) {
      return cache.delete(data.url).then(function () {
        event.source && event.source.postMessage({ type: 'forget-done', url: data.url });
      });
    }));
  }

  if (data.type === 'list-saved') {
    event.waitUntil(caches.open(SAVED).then(function (cache) {
      return cache.keys().then(function (keys) {
        event.source && event.source.postMessage({
          type: 'saved-list',
          urls: keys.map(function (request) { return request.url; })
        });
      });
    }));
  }
});
