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

## Totals

*Pending the crawl.* The crawler writes
`storage/app/legacy/inventory.json` with every page, document and image,
and this section is generated from it.

## Method

`/tmp/crawl.py`, preserved. One request per second with a descriptive
user-agent naming the migration and the client. Same-host links only.
Documents and images are recorded, not followed. Capped at 400 pages so a
link loop cannot run away on the client's server.
