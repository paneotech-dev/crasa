# CSS delivery

Written 9 September 2026, after a set of screenshots disagreed with a report.
The screenshots were right and the report was wrong. This records what was
actually happening, because the failure was invisible to every check the
build had.

---

## The finding, first

**The stylesheets were always reaching the browser. They were reaching it a
day late.**

Every CSS file returns 200 and `text/css`, parses without a single syntax
error, and contains every rule the pages need. The origin is correct. The
Cloudflare edge copy is correct and byte-identical to the working tree.

What is wrong is the URL. Cloudflare serves these files with
`cache-control: max-age=31536000` — one year — and the href never changes:

```
<link rel="stylesheet" href="https://crasadev.paneo.tech/css/blocks.css">
```

```
$ curl -sID https://crasadev.paneo.tech/css/blocks.css
cache-control: max-age=31536000
expires: Thu, 09 Sep 2027 15:16:06 GMT
cf-cache-status: HIT
age: 62
```

A browser that fetched `/css/blocks.css` at any point holds that copy for a
year and will not ask again. Editing the file changes nothing for that
browser, because nothing about the request changed. The HTML is
`cache-control: no-cache, private` and is always fresh, so the markup
arrives new and the styling arrives old. That is the exact shape of the
reported symptoms.

Every screenshot symptom follows from it, and from nothing else:

| Seen | Rule that exists on the server but not in the cached copy |
|---|---|
| Section band `dl` as stacked lines | `.c-band__counts { display: grid; ... }` |
| Map as solid black polygons | `.c-map-region__member path { fill: var(--c-green-light) }` — an SVG `path` with no `fill` declaration renders black |
| Island insets as full-width blobs | `.c-map-region__inset svg { width: 66px; height: 53px }` |
| Install prompt unstyled in the content flow | `.c-install { position: fixed; ... }` |
| Committee and event lists as bullets | `.b-cards`, `.c-event` — a `ul` with no rule gets the browser's discs |
| Header wrapped onto two rows | `.c-nav`, `.c-header__inner` |

None of these is a missing rule. All of them are a rule the browser has not
been told about.

**Why the build could not see it.** Every check fetched with curl on a fresh
connection, which has no cache, so it always got the current file. A test
that asserts a rule exists in `public/css/blocks.css` passes whether or not
any browser will ever receive it. The build was measuring the origin and
reporting on the browser.

---

## a) and b) — what is linked, and what it returns

Nine stylesheets across the public surfaces. Every one, on every page, in
every locale:

| File | Status | Content-Type | Bytes | Cache-Control |
|---|---|---|---|---|
| `css/fonts.css` | 200 | text/css | 8797 | max-age=31536000 |
| `css/tokens.css` | 200 | text/css | 6142 | max-age=31536000 |
| `css/vendor/bootstrap-grid.min.css` | 200 | text/css | 51789 | max-age=31536000 |
| `css/grid.css` | 200 | text/css | 2198 | max-age=31536000 |
| `css/base.css` | 200 | text/css | 6869 | max-age=31536000 |
| `css/chrome.css` | 200 | text/css | 8402 | max-age=31536000 |
| `css/blocks.css` | 200 | text/css | 89610 | max-age=31536000 |
| `css/print.css` (media=print) | 200 | text/css | 3490 | max-age=31536000 |
| `css/comparator.css` (comparator only) | 200 | text/css | 17871 | max-age=31536000 |

No 404. No wrong Content-Type. No redirect.

## c) — parse

All nine parse clean under tinycss2: zero error nodes, final brace depth
zero, no unterminated comment or string in any file. The unclosed-brace
theory was worth testing and is not what happened.

## d) — selector reachability

Every class name in the served markup of all ten public pages was checked
against every rule in the stylesheets that page links. One real gap:

- `c-header__toggle-label` — used in the header's drawer button, no rule
  anywhere. It has been given one in `chrome.css`.

`__cf_email__` also appears with no rule. That class is injected by
Cloudflare's email obfuscation into our markup; it is not ours and is
excluded from the guard.

An earlier pass reported thirty missing `c-cmp__*` rules on the comparator.
That was a fault in the checking script, which read the homepage's
stylesheet list and applied it to every page. `comparator.css` is linked on
the comparator page and always was. Corrected before anything was changed on
the strength of it.

## e) — cache busting

This is the fix. Hrefs carried no version and no hash, so a changed file was
not a changed URL. `App\Support\Assets::url()` now appends the file's
modification time:

```
<link rel="stylesheet" href="https://crasadev.paneo.tech/css/blocks.css?v=1757430000">
```

The long `max-age` is now correct rather than harmful: the URL changes when
the bytes change, so the browser fetches the new file immediately and caches
it for a year, which is what a year-long TTL is for. Scripts carry the same
treatment; `js/hero.js` had the identical defect.

## f) — .htaccess and middleware

`public/.htaccess` is stock Laravel: rewrite rules, the authorization header
passthrough, the trailing-slash redirect, the front-controller fallback. It
sets no `Header`, no `ExpiresByType` and no `AddType`. No middleware touches
static files, which never reach PHP.

The one-year TTL is therefore **Cloudflare's**, not ours. It is not
removable from the application, which is why versioning the URL is the fix
rather than weakening the header. Cloudflare is also rewriting our HTML,
which is how `__cf_email__` appears in markup no template contains.

---

## The guard

`tests/Feature/StylesheetDeliveryTest.php` walks every public route, pulls
every class out of the served markup, and fails if any class has no rule in
a stylesheet that page links. It also asserts every asset href carries a
version. It would have caught `c-header__toggle-label`, and the versioning
assertion is what stops this class of failure recurring.

It cannot see a browser cache. Nothing in a test suite can. What it can do
is guarantee that a changed file is a changed URL, which removes the
mechanism.
