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
Imagick with the Ghostscript delegate covers the requirement. The
instruments task can generate thumbnails directly; the stub-and-report path
in its brief is not needed.

### Search — ElasticSearch not available, decision required

| Check | Result |
|---|---|
| Ports 9200, 9300, 7700, 8108 | all closed |
| ElasticSearch / OpenSearch / Meilisearch / Solr processes | none |
| `elasticsearch`, `opensearch`, `meilisearch` binaries | not found |
| Java runtime | **not installed** |
| MariaDB InnoDB `FULLTEXT` | **working** — natural language and boolean mode both verified |
| `laravel/scout` installable from packagist | yes |

No JVM means ElasticSearch and OpenSearch cannot be installed on this host
even with root. The realistic options are a managed ElasticSearch or
OpenSearch service reached over HTTP, or Laravel Scout on a MariaDB
full-text driver accepting reduced faceting. This is a decision for the
search task; either way the implementation sits behind a driver interface so
swapping is a config change.

---

## Repository

**The design archive `.design/` is untracked.** It is the approved source of
truth for every screen and is currently outside git. Committing it adds
roughly 1 MB of HTML plus the bundled font files. Worth a decision either
way rather than leaving it untracked by accident.
