# Rendered-site checks: `qa:crawl` and the contrast audit

Written 17 September 2026.

## What this is, against the contract

D3 names a Selenium regression suite. That suite does not exist yet.
**`php artisan qa:crawl` is the interim answer**: a repeatable, scriptable
walk of the running site that fails on what a person would hit. It is not a
substitute for scripted user journeys, and it is not presented as one.

## `qa:crawl`

Black box over HTTP. It talks to nginx, not to the framework, so the web
server, PHP-FPM, the storage link and the real session cookie are all in
the path. GET only; it never follows sign-out or two-factor enrolment.

**Fails on:** any 5xx; a 404 on a link the site printed; a debug trace in a
page; an image, stylesheet, script or download that does not answer 2xx/3xx.
**Records but does not fail:** a 403, which is a permission boundary working.

```
# As a visitor in all three languages, against the configured APP_URL
php artisan qa:crawl

# The full run used for the third audit, on the test host before DNS:
sudo -u crasa php artisan qa:crawl \
  --base=https://crasadev.paneo.tech --resolve=crasadev.paneo.tech:127.0.0.1 --insecure \
  --accounts=/path/to/accounts.txt --max=1500 \
  --json=storage/app/private/qa/latest.json
```

The accounts file has one account per line: an address, optionally
`totp:SECRET`, and the password as the last word. Lines without an address
are ignored, so the demonstration credentials file works as it is. An
account with two-factor sign-in needs its secret, or it is reported as a
sign-in failure. Keep the file outside the web root at mode 600, and delete
it after use.

Exit status is non-zero on any failure, so it can gate a deploy. Twelve
query-string variants are followed per path, because the extranet calendar
links a month ahead indefinitely and the facet filters combine into
thousands of addresses.

**First signed-in run found a real fault**: `/en/admin/media` answered 500
for every imported photograph (fixed the same day, `d8d3b78`).

## The contrast audit (not in the repository)

Colour contrast needs a browser that computes styles. It is a Playwright
script kept outside the repository, because Playwright is a Node dependency
the project has not agreed to add. It measures every visible text node, in
the light and dark admin themes, against WCAG 1.4.3, and filled controls
against 1.4.11.

**Standing exemptions**, all disabled controls (WCAG 1.4.3 exempts inactive
components):

- `button.a-linkbutton[disabled]`: "Move up" on the first block and
  "Move down" on the last, 4.39:1 on white, 3.9:1 on the cream
  incomplete-row tint.
- The pager's inactive "Previous" and "Next" (`span`), 4.39:1.
- Disabled facet options on the public filters.

**Boundary notes, not changed:** `.a-btn--danger.a-btn--small` (white fill
on the admin ground, border 1.61:1) is identified by its label;
`.c-btn--secondary` on navy-mid is 1.68:1 at the boundary.

## Results, 17 September 2026

| Run | Before | After |
|---|---|---|
| Admin light theme, 120 pages | 271 failing text nodes | 20, all exempt (disabled controls) |
| Admin dark theme, 120 pages | 1,520 | 36 before the import; 351 after it (the media library had content for the first time); **20**, all exempt, after the 17 September fixes |
| Public, 3 languages | 12 failing signatures | disabled facet options only |
| `qa:crawl`, visitor + 7 accounts, production | 1 failure (media library 500) | 0 |
