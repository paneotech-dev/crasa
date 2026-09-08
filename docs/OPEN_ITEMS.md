# Open items

Decisions, blockers and deferred work. Updated as items land or close.
Nothing in this file appears on the rendered site.

---

## Awaiting the client

| Item | Needed for | Note |
|---|---|---|
| CRASA official logo, vector | Header, footer, favicon | `crasa-logo.svg` in the design archive is a trace, not the official mark. Request from the Secretariat via Form 1. |
| EU emblem and funding statement wording | Footer partner row | Must come from the European Commission visual identity page; wording from the programme contact. Binding rules on proportion, clear space and permitted colours. Never drafted or approximated. |
| Partner and funder attribution | Footer partner row | Removed from the build 2026-09-08. The footer carries no partner or funder marks until CRASA supplies both the copy and the artwork. |
| Confirmation of the fourteen authority records | Members, comparator | All fourteen seeded rows are `is_sample = true`. Names and acronyms are believed correct but unverified against the CRASA member register. `established_year` null throughout. |
| Written consent to list member authorities | Members page, D5 documentation | Membership generally implies consent, but the Secretariat should confirm in writing before go-live. |
| Committee register | Committees | Six committee names taken from the approved screens. Mandates, chairs and membership are placeholders, all flagged sample. No chair is asserted. |
| Sector accent colours | Sector styling | The design foundations do not define per-sector accents. Three placeholders chosen to avoid the status colours. A designer should confirm. |
| Source Serif 4 | — | Removed from the token layer 2026-09-08. Three families, three roles. Reinstate only on a design decision. |

---

## Disclosed deviations from the specifications

These three depart from the written specifications and go to the client in
writing. Each is a hosting or platform reality, not a preference.

| # | Specification says | Built as | Why |
|---|---|---|---|
| 1 | Laravel 11 | **Laravel 13.30.1** | The base install predates this build. Laravel 11 is out of active support; downgrading a working install to an older major would add risk and no benefit. |
| 2 | MySQL 8.0 | **MariaDB 10.11** | The server runs MariaDB. All tables are InnoDB, utf8mb4_unicode_ci. Behaviour differences that matter to this build are limited to the SET column type, addressed in DATA_MODEL section 2. |
| 3 | ElasticSearch | **Laravel Scout on MariaDB InnoDB FULLTEXT**, behind a driver interface | Resolved 2026-09-08. See below. |

### 3. Search driver — resolved 2026-09-08

**Decision: Laravel Scout on MariaDB InnoDB FULLTEXT, built behind a driver
interface.**

ElasticSearch is not installable on this host, not merely absent. The check
on 2026-09-08 found ports 9200, 9300, 7700 and 8108 closed, no ElasticSearch,
OpenSearch, Meilisearch or Solr process or binary, and **no Java runtime**.
Both ElasticSearch and OpenSearch require a JVM, so neither can run here even
with root access. This is shared cPanel hosting; a JVM is not something to add
to it.

MariaDB InnoDB `FULLTEXT` was tested on the live database and works in both
natural language and boolean modes. `laravel/scout` installs from packagist.

The accepted trade-off is reduced faceting: faceted counts across country,
sector, type and year are more expensive in SQL than in an inverted index, and
predictive suggestions are simpler. The contracted search behaviour is
deliverable; the query cost is higher.

The implementation sits behind a driver interface, so moving to a managed
ElasticSearch or OpenSearch service over HTTP later is a configuration change
and a new driver class, not a rewrite. That option stays open if the client
wants to fund a managed service.

Search itself is not built yet. It is Task 6.

---

## Deferred to a later phase

**Selenium four-width regression suite — D3 testing phase, infrastructure TBD.**
The acceptance criterion is no horizontal overflow at 375, 768, 924 and 1440,
measured on a fresh reload. Browser automation is already contracted under
Testing and QA for the D3 cycle, so it gets built there, on infrastructure
that suits it. No headless browser is installed on this box: Playwright or
Puppeteer on shared cPanel would fight for disk and duplicate that work badly.
Until then the width pass is manual, in Chrome DevTools at the four widths.

**SSO bridge.** Blocked on Form 3. If the Extranet platform details and test
credentials have not arrived by the end of phase 2, build the documented
fallback so IN-01 has a deliverable path.

---

## Environment findings

Checked 2026-09-08 on the live host.

### PDF thumbnails — available, no action needed

| Component | Status |
|---|---|
| PHP Imagick | installed |
| Ghostscript (`/usr/bin/gs`) | installed |
| ImageMagick `policy.xml` PDF restriction | none present |
| Live PDF page 1 to PNG round trip | **succeeds**, 150 dpi, verified |

Poppler (`pdftoppm`, `pdftocairo`), MuPDF and qpdf are all absent, but
Imagick with the Ghostscript delegate covers the requirement.

**The stub-and-report path in the instruments task is cancelled, confirmed
2026-09-08.** Thumbnail generation is built properly against Imagick when
that task is reached.

### Search — ElasticSearch not installable, driver decided

| Check | Result |
|---|---|
| Ports 9200, 9300, 7700, 8108 | all closed |
| ElasticSearch / OpenSearch / Meilisearch / Solr processes | none |
| `elasticsearch`, `opensearch`, `meilisearch` binaries | not found |
| Java runtime | **not installed** |
| MariaDB InnoDB `FULLTEXT` | **working** — natural language and boolean mode both verified |
| `laravel/scout` installable from packagist | yes |

No JVM means ElasticSearch and OpenSearch cannot be installed on this host
even with root. **Resolved: Scout on MariaDB FULLTEXT behind a driver
interface.** See the disclosed deviations above for the reasoning and the
accepted trade-off.

---

## Repository

**The design archive `.design/` is untracked.** It is the approved source of
truth for every screen and is currently outside git. Committing it adds
roughly 1 MB of HTML plus the bundled font files. Worth a decision either
way rather than leaving it untracked by accident.
