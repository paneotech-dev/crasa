# Legacy site inventory — WS-12

*Started 9 September 2026. Crawl of https://www.crasa.org and
https://events.crasa.org, one request per second, both hosts' robots.txt
checked first and both allow it (`Disallow:` empty).*

**Status: partial.** The full crawl is still running at the rate limit. This
records what is confirmed. The totals section is filled when it completes.

---

## Information architecture, confirmed from the homepage

Twenty-six distinct internal links. The structure is deeper than the new
site's nine flat links, and it groups — which is what Part C's submenus have
to carry.

| Legacy path | Section | New home |
|---|---|---|
| `/about` | About | About CRASA |
| `/about/#vision-mission-statement` | About | About ▾ Vision, mission and history |
| `/about/#structure` | About | About ▾ Structure |
| `/about/#secretariat` | About | About ▾ Secretariat |
| `/about/#members` | About | Members |
| `/projects/current-projects` | Projects | **Projects ▾ Current — does not exist yet** |
| `/projects/past-projects` | Projects | **Projects ▾ Past — does not exist yet** |
| `/publications` | Publications | Publications |
| `/post-category/annual-reports` | Publications | Publications ▾ Annual reports |
| `/post-category/postal-regulatory-guidelines` | Publications | Publications ▾ Guidelines, sector postal |
| `/post-category/press-releases` | News | Publications ▾ Press releases |
| `/post-category/consultancies` | News | Publications ▾ Consultations |
| `/post-category/tenders` | News | Publications ▾ Tenders |
| `/news/current-news` | News | Newsroom |
| `/news/archived-news` | News | Newsroom, flagged archived |
| `/blog-categories/crasa-news` | News | Newsroom, category |
| `/blog-categories/members-news` | News | Newsroom, category |
| `/post-articles/...` | News | Newsroom item |
| `/post/...` | News | Newsroom item |
| `/contact` | Contact | Contact |

**Two findings that change scope.**

1. **Projects has no equivalent on the new site at all.** Two sections,
   current and past. WS-12's no-content-loss clause requires them and
   WS-03's IA does not list them. Logged for Part A7.
2. **The legacy site distinguishes `/news/...` from `/post-category/...`
   from `/blog-categories/...`** — three different content groupings that
   the new site collapses into `newsroom` plus `publications`. The mapping
   above preserves each as a category rather than flattening it, which is
   what "no content loss" means for a taxonomy.

One category named in the migration brief — Electronic Communications
Regulatory Guidelines — is not linked from the homepage. It is expected
under `/publications` and the crawl will confirm.

## Hosts

| Host | robots.txt | Sitemap |
|---|---|---|
| `www.crasa.org` | 200, empty, crawl permitted | none (`/sitemap.xml` and `/sitemap_index.xml` both 404) |
| `events.crasa.org` | 200, `Disallow:` empty | `https://events.crasa.org/sitemap_index.xml` |

## The finding that governs the whole import

**The legacy site is Webflow, and no document is linked from any listing
page. Every PDF lives on its own detail page.**

A breadth-first crawl of the six category pages returned **zero documents**,
which looked at first like an empty site. It is not. The pattern is two
levels:

```
/post-category/electronic-communications-regulatory-guidelines
    → 16 links to /post-articles/<document-name>
        → one PDF each, on cdn.prod.website-files.com
```

Confirmed on `/post-articles/sadc-roaming-guidelines`, which carries
`.../612fe9d9e1d1a95bb6ccffc9_2021%20REVISED-SADC-ROAMING-GUIDELINES.pdf`.
The CDN filename preserves the original, and the article titles carry the
edition year — "Edition 2021", "Edition 2019" — which is the publication
date the manifest needs where the page does not state one separately.

So A2's import is a two-pass crawl, not a one-pass one: collect
`/post-articles/` URLs from the six category pages, then fetch each for its
document. Sixteen in the electronic communications category alone.

**The host is slow and unreliable under sustained crawling.** Two runs
stalled: the first for twenty-five minutes on a hanging request, the second
after twenty-five pages with 407 URLs still queued. Every request is
rate-limited to one a second and many take several seconds to answer. The
import needs to run as a resumable job that writes after every fetch and can
be restarted, not as a single pass — which is how it is now written.

## Totals

*Partial.* Thirty pages reached before the second stall:

| | Count |
|---|---|
| Pages fetched | 30 |
| Documents found on those pages | 0 — see the finding above |
| Images found | 17, of which the crasa.org ones are all UI icons |
| Errors | 0 |

The `/post-articles/` layer, which is where the documents and the real
photographs are, had not been reached. `storage/app/legacy/inventory.json`
holds what there is.

## Method

`/tmp/crawl.py`, preserved. One request per second with a descriptive
user-agent naming the migration and the client. Same-host links only.
Documents and images are recorded, not followed. Capped at 400 pages so a
link loop cannot run away on the client's server.

---

## The crawl completed, 10 September 2026

`php artisan legacy:import` ran to an empty queue in about ninety seconds.
The host answered every request in well under a second, which it did not on
either of the two earlier attempts; the resumable shape stays regardless,
because a run that has to be repeated on a bad day should not start again
from the first page.

| | Count |
|---|---|
| Pages fetched | **91** |
| Documents found | **35** |
| Images found | **101** |
| Errors | 1 |

**Every document is on `cdn.prod.website-files.com`**, which confirms the
two-level finding above: the PDFs are not linked from any listing page and
are reached only through `/post-articles/<slug>`. The earlier breadth-first
crawl found zero for exactly that reason.

The filenames carry their own year, which is the publication date the
manifest needs where the page does not state one separately:

```
6127444d969e0b4cd2348592_2000 INTERCONNECTION GUIDELINES FOR SADC.pdf
612fd699e5c497569908f0cd_2000 TARIFF GUIDELINES IN SADC.pdf
612fd7dc188086cc3041ca15_2002 LICENSING GUIDELINES FOR SADC.pdf
612fdaa73d03c8570bfc4dac_2003 GUIDELINES ON HARMONISATION ON NUMBERING.pdf
612fdb57cc121c7a14bd1471_2011_SADC TOOLKIT ON UA FUNDING AND USF IMPLEMENTATION.pdf
612fde3b2c63ed470831f931_2015 SADC GUIDELINE FOR DEVELOPMENT OF NATIONAL BROADBAND PLANS.pdf
612fdebfa2759d84ac69dbf7_2015 SADC POLICY ON ROAMING APPROVED.pdf
```

**The one error** was `/post-articles/2nd-meeting-of-the-finance-and-audit-committee-fac#`
— a link on the legacy site ending in a bare fragment, fetched as a separate
URL and answered with nothing. Fixed at the source: a fragment is stripped
before a URL is queued, so a re-run is clean.

## What the import deliberately does not do

**It writes no records.** The command produces a manifest and nothing else.
Mapping a legacy category onto a `publication_type`, and deciding which of
the 35 are publications and which are news, is a decision about CRASA's own
taxonomy. It is made once, with the manifest in front of somebody, rather
than guessed by a crawler. `storage/app/private/legacy/state.json` holds
every URL, the page it was found on, and the page titles.

**Nothing is downloaded yet.** The PDFs are on the client's CDN and 35 of
them is a few hundred megabytes; fetching them belongs with the decision
about which are wanted, not before it.
