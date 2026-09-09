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

These depart from the written specifications and go to the client in
writing. Each is a hosting reality, a contractual standard overriding a
drawn value, or a conflict between two contractual documents. None is a
preference.

**All of them are consolidated for the client in `docs/VARIATIONS.md`,
which is written to be forwarded as it stands.**

| # | Specification says | Built as | Why |
|---|---|---|---|
| 1 | Laravel 11 | **Laravel 13.30.1** | The base install predates this build. Laravel 11 is out of active support; downgrading a working install to an older major would add risk and no benefit. |
| 2 | MySQL 8.0 | **MariaDB 10.11** | The server runs MariaDB. All tables are InnoDB, utf8mb4_unicode_ci. Behaviour differences that matter to this build are limited to the SET column type, addressed in DATA_MODEL section 2. |
| 3 | ElasticSearch | **Laravel Scout on MariaDB InnoDB FULLTEXT**, behind a driver interface | Resolved 2026-09-08. See below. |
| 4 | `--c-border` and `--c-navy-edge` as drawn in the approved foundations | **Darkened and lightened** | WCAG 2.1 AA is a contractual acceptance criterion and the drawn values fail SC 1.4.11. Resolved 2026-09-09; see the disclosed variation below. |
| 5 | Chart.js for graphs (Annexure B, RP-10 and the technology stack) | **The chart treatment screen 03 draws**: DOM bars that state their own figures, and the RegionMap SVG for the regional view | Resolved 2026-09-09. Two contractual documents disagree; see below. |

### 5. Chart.js and Leaflet — resolved 2026-09-09

**This is a conflict between two contractual documents, not a departure from
one.**

Annexure B names Chart.js for graphs and Leaflet for maps, both under RP-10
and in the technology stack. **Screen 03, which CRASA accepted, uses
neither.** Its regional-view bars are DOM elements that each state their own
figure beside them, and its map is the RegionMap component in choropleth
mode, drawn as inline SVG.

**Decision: the accepted screen governs.** Three reasons, and the first two
are themselves contractual requirements.

Accessibility. A canvas chart needs a separate table equivalent to satisfy
the WCAG 2.1 AA clause and the "charts have a table equivalent" requirement.
Bars that state their own figures satisfy it by construction, because they
are already text.

Print. The comparator is specified to print, and screen 03 says in its own
words that the screen "reads correctly as a printed page and under a screen
reader". A canvas and a slippy map both defeat that.

Self-hosting. A Leaflet map needs a tile provider, which is a third-party
request from a regulator's browser. This platform's stated position is that
nothing is hotlinked.

**Half of the stack row is satisfied as written: Leaflet remains in use on
the contact page** for the Secretariat's office location, which is a
geographic map and the right tool for it.

Chart.js is not currently used. If the client wants it, the natural home is
the analytics dashboards, which are not published figures and so need no
table equivalent. Swapping either library into the comparator later is a
change to one Blade partial, because the data comes from the `Comparison`
object rather than from the view.

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

## Corrections to the specification

**Clearance vocabulary, corrected 2026-09-08.** DATA_MODEL section 7 carried
`public / member / committee / restricted` for `folders.clearance`. That set
was drafting and had never been shown to the client. The accepted vocabulary
is `Public / Member / Confidential / Secretariat`, drawn as the
confidentiality badge set in screen 01 and granted by name in screen 13's
permission matrix. DATA_MODEL now matches the screens, and the same four
grades apply to documents, replacing the `is_public` boolean which cannot
express grades that screen 13 grants separately.

There is no `folders` table to migrate: the extranet is phase 3. The
correction lands in the specification and in the `Clearance` enum now, so
the table is built right when it arrives.

**Clearance is per-committee-role.** Access to a Confidential document comes
from the seat held on the committee that owns it, through `committee_user`.
An observer attends but does not hold the papers.

**users.status added.** Screen 13 grants invite and suspend; section 7 had
no column for either.

---

## Resolved decisions

### Two-factor: libraries, not a framework — resolved 2026-09-09

**Decision: `pragmarx/google2fa` and `bacon/bacon-qr-code`. Not Laravel
Fortify.**

Fortify would bring login, password reset and TOTP in one package, but it
carries opinions about routes and redirects that would fight three things
already built and tested:

- every public URL is prefixed with `{locale}`, and Fortify's route
  registration assumes it is not;
- `EnsureAccountActive` and `EnsureTwoFactorEnrolled` already run on the whole
  web group and already decide where an unenrolled or suspended account goes;
- the permission matrix, the policies and the country scope already answer
  what a role may do, which is most of what Fortify's authorisation layer
  would duplicate.

The two libraries are what Fortify uses internally for TOTP and QR generation
anyway, so this takes the parts that carry no opinions and leaves the parts
that do. The cost is roughly 300 lines of controllers, written against the
existing middleware rather than around it.

Hand-rolling RFC 6238 was considered and rejected: writing your own TOTP on a
platform with a contractual security requirement is not a saving.

### Geo-IP — available, no installation needed

Checked 2026-09-09. The specifications mention geo-IP blocking. This host
already carries MaxMind GeoLite2 City and Country databases at
`/usr/share/GeoIP`, and the `mmdblookup` binary at `/usr/bin/mmdblookup`.
There is no PHP geoip extension, but the databases can be read directly.

Nothing heavy needs installing. Geo-IP blocking itself is not built: it is
not in stage 8's scope, and blocking by country on a platform serving
fourteen member states plus a diaspora needs a policy decision from CRASA
before it becomes a rule in code.

---

## Disclosed variation: the 1024 header breakpoint

**Reason 2 — the screen is silent on a case that must be handled.**

Screen 01's canonical chrome names three widths and what the header does at
each: 1440 one row with all nine items, 768 the nav wraps with the switcher
and extranet staying on the first row, 375 everything into the drawer. It
says nothing about where the boundary between the 1440 behaviour and the 768
behaviour falls, and a media query has to be given a number.

**Delivered: 1024px.** Below it the nav takes its own row; at and above it
everything is inline. 1024 is the conventional tablet-landscape boundary and
it is comfortably above the width at which nine items in Portuguese stop
fitting one row alongside the identity and the utilities.

This replaces a runtime measurement that failed three times, each time
collapsing a navigation that fitted. The measurement is deleted rather than
fixed: a media query cannot be wrong about the viewport width.

---

## Disclosed variation: two neutrals differ from the approved foundations

**Resolved 2026-09-09. The standard wins.**

WCAG 2.1 AA is a contractual acceptance criterion. Three token pairs in the
approved design foundations fail SC 1.4.11, which requires 3:1 for the visual
boundary of a user interface control. Where an approved value fails a success
criterion, the delivered value differs.

| Token | Foundations | Delivered | Why |
|---|---|---|---|
| `--c-border` | #C9C2B2 (1.77:1 on white) | **#908977** | 3.48:1 on white, 3.22:1 on cream, 3.14:1 on the back office ground |
| `--c-border-input` | #B9B2A2 (2.11:1 on white) | **deleted** | resolved to the same value as `--c-border` once both had to clear 3:1, so it was never a separate token |
| `--c-navy-edge` | #4A5670 (2.35:1 on navy) | **#5A6680** | 3.00:1 on navy |

`--c-border` is set against **cream**, not white. Cream is the darker of the
two grounds a control sits on, so it is the harder case, and a value that
clears cream clears white. The first computed proposal was measured against
white alone and failed on cream at 2.79:1 — caught by the per-pair test, not
by the audit that produced it.

`--c-disabled` #6E7A85 reads 4.06:1 against a 4.5:1 body threshold and is
**deliberately unchanged**: SC 1.4.3 exempts text that is part of an inactive
component. A test pins it so it is not "fixed" later.

`ContrastTest` measures every pair in use, 27 cases, from the token file
itself rather than from a copy.

---

## Disclosed variation: the destinations of the footer links

**Screen 02 draws every link in the site footer as `href="#"`.** The design
is explicit about which links exist, in which column, and in what order. It
is silent on where any of them go.

That silence has to be resolved, because a footer is on every page: seven of
its targets returned 404 on the live site. This is case 2 of the two
permitted departures — the screen is silent on a case that must be handled.
No link was added, removed or reordered.

| Footer link | Destination chosen | Reasoning |
|---|---|---|
| Who we are | `/{locale}/about` | The page exists. |
| Governance, Executive Committee, Secretariat, Partnerships | `/{locale}/about#…` | The About column holds five links and About is one page, so four of them are sections of it. Page blocks gained an `anchor` column and those four sections now exist on the About page, all flagged `needs_content`. |
| Regulatory library | `/{locale}/publications` | The built library. |
| Comparator | `/{locale}/comparator` | WS-05, later phase. The slug resolves to a page stating that, so the approved footer keeps its link without a dead end. |
| Events | `/{locale}/events` | WS-07, later phase. Same treatment. Delegate registration and travel remain out of this codebase entirely. |
| Committees, Members, Newsroom, Contact | the built pages | — |
| Extranet | redirect to sign-in | The extranet is phase 3. Its entry point for an unauthenticated visitor is the sign-in screen, which exists. |
| Privacy, Terms, Accessibility | `/{locale}/…` | Built. Privacy and terms carry placeholder copy; see "Awaiting the client". |
| X, Facebook | as drawn in the screen | Client-supplied URLs already in the markup. |
| LinkedIn | `linkedin.com/company/crasa` | The screen draws `href="#"` for this one where it gives real URLs for the other two. **To confirm with CRASA.** |

`SiteNavigationTest` crawls every internal link on every public page and
fails if any of them stops resolving, and checks that each footer fragment
lands on an element that actually carries that id.

**Darkened a second time, 2026-09-09.** #958E7E cleared white and cream but
reached only 2.93:1 on `--c-admin-bg` (#F4F3F0), the back office ground drawn
in screen 09, which is darker than cream. The border neutral now clears 3:1
on all three grounds a control sits on. Found by the per-pair test the moment
the back office ground became a token, which is what that test is for.

---

## Disclosed variations: where screen 09 is silent

Screen 09 is a static rendition. Three things it cannot state, and what was
done about each. All three are case 2: the screen is silent on a case that
has to be handled.

**1. Form fields.** Screens 09 and 13 draw every "input" as a span, so
neither says what a real field looks like. The geometry is taken from the
boxes the screens do draw (padding 9px 11px, a 1px rule, a white ground).
The two things a static rendition cannot decide come from the standards
instead: `--r-xs`, because CLAUDE.md fixes that radius for inputs, and
`--c-border` rather than `--c-line`, because SC 1.4.11 asks 3:1 for the
boundary of a control and `--c-line` reaches 1.5:1 on white.

**2. The input border screen 09 does draw.** The search field in the
resource table is drawn `border:1.5px solid #B9B2A2`. That is 2.11:1 on
white. The standard wins, as it did for `--c-border` and `--c-navy-edge`:
the delivered field takes `--c-border`. Same precedent, same reasoning.

**3. A breadcrumb.** Screen 09 shows the dashboard, which is the root of the
back office and has nothing above it, so no trail is drawn. A trail is
needed the moment a section has a detail view. `.a-crumbs` follows the type
and rule values the screen sets for its other quiet metadata.

**And one substitution, not a variation.** Screen 09's stat row is six
cards: Publications, News items, Committees, Member regulators, Upcoming
events, Projects. Events and projects have no model in this build, so the
row shows Regulatory instruments and Indicators in their place. Six cards,
same shape, every figure counted from the database.

---

## Disclosed variation: content editing has no canvas

**Screen 14 draws page content as rows, not as a canvas.** Its "Homepage and
page content" panel is a list: field name, value, where it appears, which
languages it exists in, and one Edit link, each row drawn as

```
display:flex;gap:24px;align-items:flex-start;padding:18px 24px;flex-wrap:wrap
```

with the row tinted when a language is missing. There is no drag-and-drop
editor anywhere in the approved screens.

The block list is built in that register: rows in a panel, and reordering is
a Move up and a Move down button on each row. That is case 2 — the screen is
silent on arranging blocks, and blocks have to be arrangeable.

It is also the only version of reordering that is operable from a keyboard
without building a bespoke drag widget with its own live announcements, and
WCAG 2.1 AA is an acceptance criterion here rather than an aspiration. If
CRASA later wants dragging, it is an addition on top of these buttons, not a
replacement for them: the buttons stay as the keyboard path.

Two smaller silences in the same screen, handled the same way: the workflow
controls and preview. Neither appears in any approved screen.

---

## Disclosed variation: pages are served by slug

Every public route was named individually. A page created in the back office
therefore had no address, which makes a page editor a page editor for eleven
existing pages and nothing else.

A single-segment catch-all is registered last, after every named route, so
naming a page "publications" cannot capture the library. Unpublished and
missing pages both 404, so an address does not reveal that a draft exists
behind it.

---

## Closed: the three not-generic findings

All three are resolved. The detail is in `docs/OVERNIGHT.md`; the short
version:

**The eleven `auto-fit` grids.** Not drift. `repeat(auto-fit, minmax(min(100%,
Npx), 1fr))` is the approved screens' own formula, used throughout screen 02
including its footer at 220px. Every width in the platform was checked
mechanically against every width the screens draw: all but two match. The
two exceptions are the member grid, which is `auto-fill` with a
server-computed span under Ruling 2, and `.c-codes`, which had an invented
150px and is now 160px, a width the screens do draw.

**`--e-2` unused.** The token was unused because the thing it belongs on was
not built: screen 02 draws a compressed sticky header carrying
`box-shadow:var(--e-2)` and the header had no compressed state. Built. All
three elevations now carry a distinct depth, and a test fails if any of them
goes unused again.

**Radius 15:3.** Closed by checking each use against the rule rather than by
changing values. `--r-xs` on inputs, `--r-sm` on buttons and small controls,
`--r-md` on panels, `--r-lg` on the three card-shaped things that exist,
`--r-pill` on status badges only. No violations. The distribution is
lopsided because the element population is.

---

## Disclosed variation: onboarding, and what it deliberately is not

**No approved screen covers onboarding.** Case 2: the back office needs an
introduction and the screens are silent on what one looks like.

It is **not** a modal overlay walkthrough. That pattern traps focus, is
close to unusable with a screen reader, breaks the moment the layout
reflows, and cannot be re-read once dismissed. WCAG 2.1 AA is a contractual
acceptance criterion on this project, so it loses, exactly as it did for the
contrast values.

What is built instead:

- A panel at the top of the dashboard, in the flow of the document, using
  the panel and row treatments the screens already establish. Dismissible
  per account rather than per browser, because one officer signing in from
  the office and from a laptop has read it once.
- The same content permanently at `/admin/help`, plus the plain-language
  explanation of all twelve block types and the six rules the platform
  holds to.
- Inline help drawn as a `<details>` disclosure, never a hover tooltip. A
  tooltip does not exist for anyone on a touch screen and does not exist for
  a keyboard user unless it is rebuilt from scratch with focus handling.

**One numbered list, deliberately.** CLAUDE.md forbids `01 / 02 / 03`
markers where the content is not a sequence. The introduction is a sequence:
a person works through the six sections once, in order. Nothing else in the
back office is numbered.

---

## Disclosed variation: screen 18 is built at half its width

**Screen 18 draws more than the scope permits, and the difference is
commercial rather than aesthetic.** The ruling is that the website collects
the information and the Event Management Platform manages the operation,
and that division protects a separate commercial agreement.

Built, from screen 18:

- Arrival airport or point of entry, date, time and flight reference
- Departure date, time and flight reference
- "Anything else the Secretariat should know", as one free field
- The Secretariat's roster as a plain sortable list, with a CSV export

**Deliberately not built, though screen 18 draws them:**

| On the screen | Why not |
|---|---|
| Accommodation, nights required | Hotel booking is the managing half |
| Dietary requirements | Dietary tracking is the managing half |
| Transfer schedule, driver allocation | Grouped scheduling is the managing half |
| Status workflow, reminders, chase emails | Reserved scope, and nothing on this platform notifies a delegate |
| Post-event feedback | Reserved scope |

The absence is enforced rather than merely intended. `EventsAndDelegatesTest`
walks the `delegate_registrations` and `meetings` column names and fails if a
word like "accommodation", "transfer", "dietary" or "driver" ever appears in
one; it walks the registration form and fails if a field is added; it walks
every route touching events or meetings and fails if a write appears that is
not a delegate filing their own arrival; and it asserts that the platform's
one notification class is still the regulatory one.

It is called **delegate registration** everywhere, including in all three
lang files, and there is a test for that too. "Travel logistics" is the name
of the thing that is not being built.

---

## Disclosed variation: tracked caps, resolved 2026-09-09

**Ruled: the ruling wins, in both places. Sentence case for the hero eyebrow
and for back office table headers. Footer column headings remain the only
carve-out.**

Both are departures from an approved screen and CRASA has seen both screens,
so both are disclosed here.

### 1. The hero eyebrow, screen 02

```
font-size:13px;font-weight:600;letter-spacing:.14em;
text-transform:uppercase;color:var(--c-green-light);
margin-bottom:var(--s-5)
```

Delivered as `.b-hero__eyebrow`: 13px, weight 600, `--c-green-light`,
`margin-bottom: var(--s-5)`. The size, the weight, the colour and the spacing
are the screen's. `letter-spacing` and `text-transform` are dropped.

The same treatment applies to the section eyebrow the screen draws in
`--c-green-dark` with `margin-bottom:var(--s-3)`, delivered as `.b__eyebrow`.

The eyebrow itself is back. It had been rendered as nothing at all while the
question was open, which was the reversible option; it now carries the
client-supplied copy in all three languages.

### 2. Back office table headers, screen 09

```
font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;
font-weight:700;color:var(--c-slate)
```

Delivered as `.a-table th[scope="col"]`: 12px, weight 700, the quiet ink, and
the rule beneath. Only the letterform changes, so a header still reads as a
header.

### Why both, and not one of each

Taking the ruling for the hero and the screen for the table would have been
arbitrary: it is one rule, stated once, and the two cases differ only in
which surface they sit on. The carve-out in CLAUDE.md names section eyebrows
and table headers as excluded in terms.

**Footer column headings stay the only place tracked caps are used**, at
12px, weight 700, `letter-spacing: .1em`, as drawn in screen 02.

---


## Disclosed variation: the committee grid is derived, not drawn, 2026-09-09

`docs/DATA_MODEL.md` fixed the block library at twelve types and said a
thirteenth required a decision. The decision was taken on 9 September and
`committee_grid` is the thirteenth.

**No approved screen draws a public committee list.** All sixteen were
searched. Screen 04 draws one committee's workspace behind authentication and
its breadcrumb implies an index it does not draw; screen 12 has a committee
filter on the calendar; screen 02 links to Committees from its navigation.
There is no index in the set.

So the card is derived from `member_grid`, which is the platform's existing
pattern for a set of institutional records: a bordered white card whose height
follows its content, the name, a supporting line, and a counted tally on a
rule at the foot. Nothing new was invented; an existing pattern was applied to
a second kind of record.

**What screen 04 did settle, and is followed.** The meta line leads with the
sector. And the empty state is the screen's own words: a committee that has
been constituted and has not yet met says so, rather than showing zeroes.

If CRASA would rather the committees were presented differently, the block is
one Blade file and one CSS block, and the records behind it do not change.

---

## Rejected: grouped left navigation in the back office, 2026-09-09

A comparable platform groups its left navigation by function — My Workspace,
Knowledge Base, Collaboration Network, Capacity Building, Platform
Management — and it was proposed for the back office and the extranet here.

**Rejected for the back office.** Screen 09 draws a horizontal section bar in
the page header and not a sidebar:

```html
<nav aria-label="Back office" style="display:flex;flex-wrap:wrap;gap:2px;
     min-width:0;margin-left:var(--s-4)">
```

The screen is approved and CRASA has seen it. A pattern from another platform
does not override one, however good the pattern is. The bar stays.

**Adopted for the extranet, because the extranet already had it.** Screen 04
draws the extranet with its own navy left rail, and the build had been
extending the back office layout instead — screen 09's pattern on a surface
that has an approved pattern of its own. That is now corrected: the extranet
has `layouts/extranet.blade.php` and the rail screen 04 draws, including the
clearance panel pinned to its foot.

**Rejected in both: the grouping itself.** Screen 04 draws seven flat links
and no group headings. Grouping five items under three named groups adds
headings without adding navigation, and at this size the group label is more
text than the links it organises. If the extranet grows past a dozen
destinations the question is worth reopening; at five it is not.

---

## Deferred: two map capabilities, logged 2026-09-09

Four gaps in the region map were named in the report at the end of the
responsiveness run. Four were closed on 9 September; two were ruled out of
scope at the same time and are recorded here so they are deferred rather than
forgotten.

**Change over time.** The map shades one period. It cannot answer "who
improved", which needs a period selector and a difference mode: shade by the
change between two periods rather than by a value, with a diverging scale
because an improvement and a decline are opposite directions and one hue in
four steps cannot say that.

That is a second colour scale on a platform that has deliberately kept to
one, so it is a design decision as well as a build one. Screen 03 and the
RegionMap screen both draw a single-hue sequential scale and neither draws a
diverging one, so there is nothing approved to build against.

**Composite categories.** A reader who wants "spectrum readiness" rather than
one spectrum indicator has to pick the indicators one at a time. A composite
needs a weighting, and a weighting is a methodological decision that belongs
to CRASA rather than to us: publishing a "readiness index" whose weights we
chose would be publishing an opinion as a figure.

The completeness dataset is the only composite the map carries, and it is
composite over a count rather than over values, which needs no weighting.

Both stay out until CRASA asks for them, and both are additions to
`App\Support\MapView` rather than changes to it.

---

## Disclosed variation: two hero panel colours substituted, 2026-09-09

Screen 02's data panel draws its bar track at `#0B1522` and its bar fill at
`#2E9C6C`. Neither is a token in the approved foundations.

Rather than promote two more literals, both take the nearest existing token:

| Screen | Token used | Value | Difference |
|---|---|---|---|
| `#0B1522` | `--d-bg` | `#0B1220` | 3 on green, 2 on blue |
| `#2E9C6C` | `--d-green` | `#2FA76F` | 1 on red, 11 on green, 3 on blue |

Neither difference is visible, and both tokens already exist for exactly this
purpose: they are the dark-surface set from screen 09, and the hero's navy
band is a dark surface. Adding two tokens to record a difference nobody can
see would grow the palette for nothing.

Measured, not assumed: `--d-green` on `--d-bg` is 6.13:1, against the 3:1 that
SC 1.4.11 asks of a non-text graphic. The bar states its own figure as text
beside it in any case, so the bar is never the only carrier.

The same panel's amber notice uses `--c-amber-dark` where the screen writes
`#7A4606`, which is the substitution the comparator already made in section
17 for the identical band.

---

## Disclosed variation: three colour tokens added for the choropleth, 2026-09-09

The approved foundations carry `--c-green-light` (`#E8F4EC`) and
`--c-green-dark` (`#0E5536`) but not the two steps between them, and not the
second tone of the "not reported" hatch. The choropleth needs all five.

Three tokens are added:

```css
--c-green-step-1:#9CCFB2; --c-green-step-2:#4FA97B; --c-hatch:#CFC8B8;
```

**They are not invented.** All three are drawn in the approved region map
screen, `RegionMap.dc.html`, lines 90 and 91:

```js
const SHADES = ["#E8F4EC", "#9CCFB2", "#4FA97B", "#0E5536"];
const MISSING_FILL = "repeating-linear-gradient(135deg,#E5E0D5 0 4px,#CFC8B8 4px 8px)";
```

`#E8F4EC` and `#0E5536` are the two existing tokens; `#E5E0D5` is
`--c-sand`. The three added values are the remainder of a set the screen
already defines, promoted from literals in one screen to tokens the platform
can use. No new hue is introduced: the scale is one green in four steps.

CLAUDE.md requires asking before adding a token. The rule exists to stop
values being invented to fill a gap, and these were specified by the screen
before the gap appeared, so they are logged here rather than raised as a
question. If CRASA would rather the scale used a different set, it is three
values in `public/css/tokens.css` and nothing else changes.

**Contrast.** `--c-green-step-2` (`#4FA97B`) and `--c-green-dark` (`#0E5536`)
carry white text on the map; `--c-green-light` and `--c-green-step-1` carry
`--c-navy`. That switch is the screen's own rule (`inkFor`: band 2 and above
takes `#FFFFFF`). Every shape also states its figure as text, so the shading
is never the only carrier of the value.

---

## Disclosed variation: the map stays clickable while it is shaded, 2026-09-09

`RegionMap.dc.html` disables selection in choropleth mode — the shapes take
`pointer-events:none` and the labels take `disabled`. That is correct on
screen 03, where the map feeds the comparator and a shaded country is a
figure being read rather than a destination.

On the membership page the map is the page's navigation, so the link to each
member state's entry stays live in both modes. A map that stopped being
clickable the moment a reader shaded it would have taught them that shading
breaks the page.

Everything else about choropleth mode follows the screen exactly: the same
four shades, the same hatch for a state that has not filed, the same value
printed under the ISO code, the same legend.

---

## Superseded: the contrast finding that produced the above

Computed 2026-09-09 against the actual token values, for the pairs actually
used in the stylesheets. Three genuine WCAG 2.1 SC 1.4.11 failures, all on
control boundaries at 3:1. These are approved token values, so they are not
changed without a decision.

| Token | Used for | Current | Needs | Smallest fix on the same hue |
|---|---|---|---|---|
| `--c-border-input` | input and select borders | #B9B2A2 on white, **2.11:1** | 3:1 | **#9B9484** (3.02:1) |
| `--c-border` | icon-button and chip borders | #C9C2B2 on white, **1.77:1** | 3:1 | **#9B9484** (3.02:1) |
| `--c-navy-edge` | switcher border on navy | #4A5670 on navy, **2.35:1** | 3:1 | **#5A6680** (3.00:1) |

The two light neutrals converge on the same value at 3:1, which is worth
knowing: they may not need to stay two separate tokens.

`--c-disabled` #6E7A85 on cream is 4.06:1 against a 4.5:1 body-text
threshold, but **this is not a failure**. WCAG 2.1 SC 1.4.3 exempts text
that is part of an inactive user interface component. No change proposed.

Every other pair passes, most comfortably: body text 13.59:1, headings
16.00:1, the amber focus ring 3.43:1 on cream and 4.66:1 on navy.

---

## Deferred to a later phase

**Selenium four-width regression suite — D3 testing phase, infrastructure TBD.**
The acceptance criterion is no horizontal overflow at 375, 768, 924 and 1440,
measured on a fresh reload. Browser automation is already contracted under
Testing and QA for the D3 cycle, so it gets built there, on infrastructure
that suits it. No headless browser is installed on this box: Playwright or
Puppeteer on shared cPanel would fight for disk and duplicate that work badly.
Until then the width pass is manual, in Chrome DevTools at the four widths.

**SSO bridge — fallback built, 2026-09-09.** Form 3 has still not come back,
so there is no identity source, no endpoints and no test credentials. The
documented fallback is built rather than waiting: the platform authenticates
against its own user store behind an `App\Auth\IdentityProvider` interface,
and the SSO handoff and all four of its failure paths exist and are tested
against a stand-in provider.

**What CRASA must supply is in `docs/SSO_REQUIREMENTS.md`, written to be
forwarded as it stands.** Seven items: which platform is the identity source,
which protocol, the endpoints, which attribute is the stable identifier,
whether the provider may create accounts here, two test accounts, and a named
technical contact.

One of those seven has a security consequence and carries a recommendation:
**federated identity should not create accounts on this platform.** Otherwise
anyone the identity provider trusts becomes a user of a platform holding
country-scoped data and confidential committee papers, without anyone at the
Secretariat deciding so.

Two things that do not change when the bridge lands. The password form stays,
because a bridge that is the only way in becomes an outage for the whole
platform when the identity provider has one. And two-factor still applies to
administrators: a federated sign-in is a claim about who someone is, not a
second factor.

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

**The design archive `.design/` is in git**, committed 2026-09-08. It is the
approved baseline every remaining screen is checked against, and losing it
would lose the reference for the whole build. It is committed whole,
including the bundled fonts and the React and Babel builds the previews
load, so the screens still render offline years from now.

---

## Decision: sample data is flagged and disclosed verbally, not badged

*Taken 9 September 2026. Disclosed variation from screens 02, 03 and 08.*

Every rendered demonstration-data notice has been removed from the pages.
Screen 02 draws one inside the hero panel on an amber ground with a 4px left
edge; screen 03 draws one beside the comparator's standfirst and says it
"remains in place, on screen and in every export, until each value is
confirmed by its national regulator". Both are followed no longer, and this
records why and exactly what is left.

**Removed — five rendered banners:**

| Surface | What it was |
|---|---|
| Homepage hero, all three slides | `b-hero__panel-notice` inside the data panel |
| Homepage featured comparison | `c-notice--pending` under the table |
| Comparator masthead | `c-cmp__notice` beside the standfirst |
| Completeness matrix | `c-notice--pending` above the matrix |

**Kept, deliberately, and each is a judgement I would want overruled in
writing rather than assumed:**

1. **The per-cell provenance label.** The comparator's source column reads
   Validated, Reported, Demonstration or Not reported against each figure.
   That is RP-10, not a badge. A source column that omitted "Demonstration"
   would state that a number came from somewhere it did not, which is worse
   than the banner ever was.
2. **The notice in every export.** CSV and PNG carry
   `comparator.sample_notice_short`. An export leaves the platform and can
   reach a ministerial brief; the ruling was about pages, and a document
   circulating without its provenance is the specific harm the flag exists
   to prevent.
3. **The admin pre-download warning** on `admin/reports/{id}`. It tells a
   Secretariat officer, before they download a brief as PDF, that it is
   built on unconfirmed figures. Admin-only, and the same class of internal
   safeguard the ruling asked to keep.

**Added:** the admin dashboard now counts sample rows by table, above the
statistics because it qualifies every figure in them, with the strip command
beside it. It reads from `StripSampleData::ORDER`, so a model added to the
strip is counted from the same commit. It disappears when the count reaches
zero rather than showing an all-clear somebody has to keep believing.

**The one exposure this creates, stated plainly.** The completeness matrix
is behind authentication and is read by member state representatives, who
cannot see the admin dashboard. They now have no signal at all that the
matrix is built on unconfirmed figures, and it is the one page whose entire
subject is data quality. The recommendation is to restore the notice there
alone; doing so is reverting one hunk in
`resources/views/pages/completeness/index.blade.php`, which carries a
comment saying so.

Nothing about the data changed. `is_sample` is untouched on 18 models,
`content:strip-samples` is unchanged, and `data:check` still runs nightly.

---

## Decision: the hero's state note is removed

*Taken 9 September 2026. Disclosed variation from screen 02.*

Screen 02 closes the carousel's controls bar with a counter and then a note
on what the carousel is doing — "font-size:13px;color:#B9C2D0" after the
`margin-left:auto`. The counter stays. The note is gone.

It narrated the control's own behaviour back to a reader who could already
see the control: three buttons, three numbered dots, a counter, and then a
sentence saying the thing rotates. What it genuinely carried was for a
reader who cannot see the bar, and that belongs on the control itself, where
it is announced at the moment the reader reaches it rather than sitting in
a paragraph they may never land on.

So the pause button now names its own action. The visible label stays one
word and the accessible name says what stops:

| Locale | Visible | Accessible |
|---|---|---|
| en | Pause / Resume | Pause rotation / Resume rotation |
| fr | Pause / Reprendre | Pause de la rotation / Reprendre la rotation |
| pt | Pausa / Retomar | Pausa da rotação / Retomar a rotação |

Both names swap together when the button is pressed. Each accessible name
contains its visible label, which SC 2.5.3 requires so that a speech-input
user can say what they see; the first attempt failed that in French and
Portuguese, where "Interrompre la rotation" does not contain "Pause". A test
now checks the containment in all three locales rather than trusting the
translation.

"Play" became "Resume" in the process. It is better English for a carousel
and it is what makes "Resume rotation" satisfy 2.5.3.

---

## Four disclosed variations on the map

*Taken 9 September 2026, after the map was reverted to the approved box and
still read badly. Each is a departure from RegionMap.dc.html and each serves
the screen's own intent, which is a map that reads as real geography and
behaves like a control.*

**1. Geometry at 1:50m, not 1:110m.** RegionMap specifies 1:110m for the
mainland and 1:50m for the islands. 1:110m is a world-map resolution: at the
size the members card renders it turns Tanzania's coast into a zigzag and DR
Congo into a blob. Everything is 1:50m now, from the same Natural Earth
release already recorded in `docs/LICENCES.csv`, at the same projection. The
twelve shapes land within three pixels of where they were, so every label
coordinate is unchanged, and a test asserts the bounding boxes rather than
the path text. It costs 43KB of path data against 8KB.

One ring is dropped deliberately: 1:50m South Africa includes the Prince
Edward Islands at 46 degrees south, which project 200px below the box.

**2. The country's name instead of its ISO code, from 768 up.** The screen
draws "CD / ARPTC". To a delegate that is two abbreviations, and CD for DR
Congo is actively misleading. From 768 the short name replaces the code;
below it the name will not fit and the code returns. Both labels are served
and a media query chooses.

Lesotho and Eswatini keep the code at every width. Their labels already sit
outside their shapes because the shapes cannot hold them, and a name out
there would run across South Africa and Mozambique. Both carry their full
name on the inset chip, which is what the chips are for.

**3. A marker under Seychelles.** 115 islands totalling 450 square
kilometres is three pixels at this projection, so the name sat in open ocean
with nothing under it and read as a stray word rather than a place. A 6px
circle in the context colour now sits at the archipelago's position. This is
what a cartographer does for a settlement too small to draw.

**4. Hover and focus.** RegionMap draws none: its shapes have one fill and
one stroke and nothing responds to a pointer. With nothing selected that
leaves fourteen identical pale shapes and no signal that any of them is a
control. Hover and focus now lift a state to `--c-green-step-1` — the
`#9CCFB2` of the choropleth's second step — and give it the stroke the
screen reserves for the selected state, so pointing at a member state looks
like what selecting it will do.

**Also fixed, and not a variation.** The map now sits in the white card
screen 08 draws at line 108, headed "The region" with "Select a member
state" at right, beside the directory it draws at line 117. Both were in the
approved screen and neither had been built: the map floated on the bare
section ground with a grid of member cards under it. The two controls above
the map had a label at `width:100%` with a negative bottom margin, so each
took its own line and sat on the control it labelled.

---

## The statement band takes a photograph, and not the hero's scrim

*Taken 9 September 2026. Disclosed variation from screen 02.*

Screen 02 draws the statement band at line 376 on flat navy with two crossed
weaves over it and no photograph. It now carries
`hero/hero-delegates-boardroom-01.jpg` behind four layers: the image, the
hero's 140deg colour-blend tint, a scrim, and the weave on top.

The band is the one place on the homepage where CRASA speaks in its own
voice rather than listing what it holds, and a claim about fourteen
regulators reads better over a room with delegates in it than over a
gradient.

**The scrim is not the hero's, and the measurement is why.** The instruction
was to use the hero's photo-slide treatment and then measure the result. The
hero's scrim runs `rgba(15,27,45,.94)` at the left to `.34` at the right,
which works there because its copy is one column on the left and the right
third is deliberately texture. This band has the statement on the left and
fourteen figure cells on the right — content at both ends. Over a white
pixel, which this photograph has in its windows and its sunburst:

| Scrim stop | Composite ground | `--c-white` | `--c-sand` | `--c-green-light` |
|---|---|---|---|---|
| left, .94 | rgb(29,41,58) | 14.72:1 | 11.18:1 | 13.03:1 |
| mid, .82 | rgb(58,68,83) | 9.84:1 | 7.48:1 | 8.71:1 |
| **right, .34** | rgb(173,177,184) | **2.14:1** | **1.63:1** | **1.90:1** |

So it takes the flat `rgba(15,27,45,.82)` that `.c-band__scrim` already
carries as the measured floor for a photograph whose own tones are not
fixed. Every colour on the band clears 7:1 against it, and `ContrastTest`
holds all seven, including a check that the hero gradient does not come
back.

**The image is reused.** The same photograph carries the hero four sections
above. One institutional photograph is doing the work of two bands, which is
thin, and it is recorded in `storage/app/public/images/README.md` and in
`docs/CLIENT_REQUESTS.md` rather than left for someone to notice.

---

## Decision: third-party brand marks are the one exception to the palette

*Taken 9 September 2026, on instruction.*

CLAUDE.md's rule is that the palette is the palette and a colour is a
decision. Four values now sit outside it:

| Mark | Colour | Source |
|---|---|---|
| X | `#000000` | X brand toolkit |
| LinkedIn | `#0A66C2` | brand.linkedin.com |
| Facebook | `#1877F2` | Meta brand resources |
| YouTube | `#FF0000` | YouTube brand resources |

These are registered trademarks whose owners publish usage guidelines
specifying the exact geometry, the exact colour and the clear space around
them. A redrawn or recoloured brand mark is a guideline breach, and on a
donor-funded treaty body's site that is the same class of exposure as
getting the EU emblem wrong.

**The carve-out is scoped and the scope is the point.** Nothing on this
platform may use those values for anything of its own. A test asserts both
that the marks carry their own colours and that CLAUDE.md still says the
scope out loud, because a carve-out that loses its scope reads as permission
to add colours.

On the navy footer the marks are white on a 36px circle of the brand colour.
`#000000` on `#0F1B2D` is not a mark, it is a hole, and the guidelines
permit a reversed mark on a brand-colour field.

All four are in `docs/LICENCES.csv` with their guideline URL and a note that
the mark must not be altered.

**What this replaced.** The three accounts were hardcoded in
`social-feed.blade.php` and two of the three were wrong: `x.com/Crasa2C`,
`facebook.com/pages/CRASA` and `linkedin.com/company/crasa`. None of those
is CRASA's account. A link on a regulator's site pointing at an account that
is not theirs is worse than no link, and a value nobody can edit is a value
nobody checks. They are site settings now, with YouTube registered and empty
so the Secretariat can fill it without a deploy.

---

## The client review state, and the two admin surfaces it needed

*9 September 2026.*

**What changed on the public side.** The privacy notice and the terms of use
each opened with a paragraph announcing themselves a draft CRASA had not
reviewed. That is a fact about this project rather than a fact about privacy
or about terms of use, and CLAUDE.md's writing rules are explicit that
project state stays in `docs/` and never reaches a rendered page. It is gone
from all three languages of both pages. The text is otherwise untouched.

**Where it went.** `pages.needs_client_review`, with `client_reviewed_at` and
`client_reviewed_by` beside it. The back office reads it in three places: a
client review column on the page list, a band on the dashboard naming every
page still waiting, and a panel on the page editor listing what is
outstanding. `PageVersioningTest::test_no_public_template_reads_the_review_state`
holds the boundary statically as well as at request level, because a block
template added later could read the flag and no rendered-output test would
catch it until a public page carried it.

**The list is derived, not maintained.** `App\Support\ClientReview` reads the
square-bracketed sentences out of the page's own English text. A second list
of outstanding items, kept by hand alongside the text, goes stale the first
time somebody fills a blank in and forgets to update it. Reading the brackets
cannot go stale.

**Two variations to disclose.**

1. **`.a-review` and `.a-history` have no screen.** Screen 14 draws the
   content editor as panels of rows and stops there; it draws no version
   history and no review panel. Both are built from the panel's own type
   scale and `--c-line`, and neither introduces a value. Case 2.
2. **Signing off is gated on `ManageSettings`, not `PublishContent`.**
   Screen 13's permission matrix grants publishing to editors as well as
   administrators. Recording that the client has approved its own legal text
   is not an editorial act, and the instruction was that it clears when an
   administrator marks it reviewed. `ManageSettings` is the
   administrator-only grant in the same matrix, so no new permission was
   invented.

**One thing left open.** The square brackets are still in the public text.
Removing them would delete content rather than a banner, and the instruction
was that the text stays. Raised in `docs/QUESTIONS.md` and named in
`docs/CLIENT_REQUESTS.md` item 8, which now lists all thirteen decisions.

**Two bugs found on the way.**

- A rich text body was capped at 4,000 characters in the block editor. The
  French terms of use ran to 4,013 before a word was added, so the legal
  pages were the one thing on the platform the page editor could not save.
  The cap is per field now: `BlockType::maxLengthFor()`, 60,000 on a rich
  text body and 4,000 everywhere else.
- The same textarea rendered at six rows. Right for an intro, unusable for a
  privacy notice. The row count follows the field's cap.

---

## The filter sidebar, and what was actually wrong with the filters

*9 September 2026.*

### The diagnosis, before the rebuild

Both paths worked. `/en/publications?type=guideline` returned one
publication and said so; the same URL with `X-Requested-With` returned every
region `filters.js` swaps; the script parsed, was served as `text/javascript`
with a versioned href, and was byte-identical to the source. Nothing was
broken in the sense of throwing.

What a reader met was this. **Twelve of the fourteen member state chips were
inert grey text**, because only two of twelve publications carry a
`country_id`, and two of the three language chips were inert for the same
reason. A row of chips where most of the row does not respond to a click
reads as a broken control, whatever the server does. The count beside each
chip was also computed with the other filters applied but not its own, which
is honest for a single-select facet and wrong the moment a facet can hold
more than one value.

### What was built

Screen 07 line 115 draws an aside at `flex:1 1 270px` with `max-width:320px`
beside a main at `flex:999 1 360px`, and every facet inside it is a list of
checkboxes with a count on the right. That is what the library and the
newsroom now carry. A checkbox holds a set, so every facet takes
`?type[]=guideline&type[]=study`; a scalar `?type=guideline` still resolves,
because links written before today have to keep working.

**Every count is now the size of the result the click would produce.** One
grouped query per facet while that facet has nothing selected, which is the
ordinary case and the case where a group count is exactly that size. Once a
facet has a selection, a click changes a set rather than defining one and no
single grouped query can answer it, so it costs one count per option.
`Facets::values()` caps a facet's selection at thirty so a crafted query
string cannot turn one page load into a thousand counts.

`FacetSidebarTest` walks every option of every facet on both pages, clicks
it, and asserts the result count against the number that was printed beside
the box. It does the same again from a state that already has a filter on,
which is the case a single grouped query gets wrong.

### Three variations to disclose

1. **The facet headings are sentence case.** Screen 07 line 129 sets them
   `font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:
   uppercase`. Question 6 was ruled on 9 September 2026 and the footer
   column headings are the only carve-out. Size, weight, colour and spacing
   are the screen's.
2. **The checkbox border is `--c-border`, not the screen's `#B9B2A2`.** That
   value is 2.1:1 on white and SC 1.4.11 wants 3:1 for the boundary of a
   component.
3. **The year rows are controls.** Screen 07 line 149 draws year as a label,
   a bar and a count with nothing to click. A year facet that cannot be
   operated is not a facet, so the row carries a checkbox and keeps the
   bar. The bar is filled with `--c-green-step-2`, the choropleth's own
   scale, so a bar and a map step mean the same thing on the same platform.

### Two bugs fixed on the way

- `filters.js` removed the apply buttons once at start-up. The sidebar is
  inside a swapped region, so the second filter click brought its button
  back. It is removed after every swap now.
- The search page still uses the old chip facets. Not in scope today and
  not yet rebuilt.

### Not done

Screen 07 also draws a sort control, a search-within-the-library field and a
much richer empty state that names the filters narrowing the search and
offers "Remove the year filter, 4 results". None of those is the sidebar and
none was built today.

---

## The hero controls come off their strip

*9 September 2026, on a ruling.*

**What screen 02 draws.** Line 152: the controls as a bar across the whole
width of the band, `margin-top:auto;background:rgba(11,21,34,.86);
border-top:1px solid #2C3A55`, padding `var(--s-3) var(--s-7)`, closing with
a counter in `--f-admin` at 13.5px.

**Overruled.** The strip added a fourth horizontal band before any content
began, and it separated the hero from the figures band the hero belongs
with. Every control the screen draws is kept and moves onto the hero as one
compact group at the bottom left, aligned to the content column's left edge,
`--s-6` above the band's bottom edge.

**What is in the group,** left to right: previous, three dots, next, a 12px
gap, pause. The arrows and the pause button are 32x32 with a 1.5px border
and `--r-sm`. The dots are 10px circles 12px apart, the current one filled
cream and the others outlined, with no visible number: each carries the name
"Slide 2 of 3: featured comparison", which is what a reader needs and what a
figure alone does not give. The counter is gone with the strip — three dots
already say which of three slides is current.

**The pill, measured.** `rgba(11,21,34,.55)`, `--r-pill`, padding 6px 10px.
The group sits where the hero scrim is at its darkest, `rgba(15,27,45,.94)`
at 0%, and that is what makes a 55% pill work over an unknown photograph.
Composited over the brightest photograph a reader could load:

| | Measured | Threshold |
|---|---|---|
| Cream `#F8F6F1` on the pill | **15.56:1** | SC 1.4.3, 4.5:1 |
| The 1.5px border, `--c-navy-soft` `#6B7691` | **3.70:1** | SC 1.4.11, 3:1 |

The ruling specified `#6B7691` for the border and the measurement confirms
it: `--c-navy-edge`, which the old arrows used, measures 2.92:1 on this
ground and does not clear. Both are existing tokens; no colour was added.
The arithmetic is in `HeroCarouselTest::test_the_controls_clear_their_thresholds_on_the_pill`,
so it is re-derived on every run rather than recorded here and trusted.

**Two consequences worth naming.**

1. **The dots' hit area is 22px, not 44px.** A 22px square drawn by a
   pseudo-element is the largest that fits between two marks 12px apart
   without two targets overlapping, and overlapping targets are worse than
   small ones: the click lands on whichever element is later in the source.
   22px is under WCAG 2.2's 24px minimum; this platform is held to 2.1 AA,
   where target size is AAA, and the same three slides are reachable from
   the 32px arrows beside them and from the left and right arrow keys.
2. **The copy column now clears the group.** `.b-hero[data-hero]
   .b-hero__inner` takes `padding-bottom: calc(var(--s-8) + 44px +
   var(--s-4))`, and `var(--s-7)` in place of `--s-8` below 768. Applied at
   every width rather than only at 375, because a long headline in French
   can reach the bottom of the band at 1440 as easily as on a phone.

**Every behaviour is unchanged.** Eight-second rotation, pause on hover and
on focus, never advancing while a control has focus, arrow keys, swipe, the
polite live region, static under `prefers-reduced-motion`, and readable with
no script at all.

**SC 2.5.3 no longer applies to the pause button**, because it no longer has
a visible label: the button is an icon and the criterion matches an
accessible name against visible text. The lang guard that keeps "Pause
rotation" containing "Pause" in all three languages is kept anyway, as a
constraint on the strings should a visible label ever come back.

---

## The administration shell, and screen 09's section bar

*10 September 2026, on a ruling.*

**What screen 09 draws.** A horizontal section bar in a white page header:
`background:var(--c-white);border-bottom:1px solid var(--c-line);
padding:var(--s-3) var(--s-6);display:flex;align-items:center;
gap:var(--s-5);flex-wrap:wrap`, with the identity on the left, the sections
beside it and the language, theme and identity pushed right.

**Overruled.** `flex-wrap: wrap` is the tell. With twenty-four destinations
the bar wrapped two and three rows deep before any content began, and the
back office is the surface the Secretariat uses every day rather than the
one CRASA looks at once. Screen 09's *content* is untouched: the figures,
both charts, the recent activity, the resource table and the sample-data
count are all still there, inside the new shell.

**The shell.** A 264px navy sidebar, fixed, full height, with the mark at
the top, grouped navigation, and the signed-in reader pinned at the foot
beside the way out. A 64px white top bar carrying a search field, the
language as a pill group, notifications, help and the reader's initials.
A content area on `--a-ground`.

**The extranet uses the same shell.** Screen 04 draws a navy left rail 220
to 280px with the mark, a label, flat links with Lucide icons and the
clearance at the bottom — which is this shell at 264px. Two shells for two
signed-in surfaces is two things to keep in step.

### Four variations to disclose

1. **The sidebar's group labels are tracked caps.** The second carve-out
   from CLAUDE.md's sentence-case rule, and it is written into CLAUDE.md
   rather than only here. At 11px a group label in sentence case reads as a
   disabled item rather than as a heading over one.
2. **`--a-ground` is a new token, `#F0F4F1`, admin-only.** Defined in
   `admin.css` under `[data-surface="admin"]`, absent from `tokens.css`,
   and no public rule may reach it — held by test in both directions.
   `--c-admin-bg` was drawn for a page with a white header above it and
   reads as a second paper colour against navy. Charcoal measures 13.22:1
   on it and slate 4.90:1.
3. **The extranet rail is grouped, not flat.** Screen 04 draws seven links
   with no headings, which was right while that was all there was. It now
   carries a row per committee the reader sits on.
4. **The clearance note moved from the rail to the extranet dashboard.**
   Under a scrolling list of committees it is a boundary statement nobody
   reads; on the dashboard it sits above the folders it describes.

### What the rebuild found

- **`/en/admin/users/invite` carried two h1s**, its own under the layout's.
  Two h1s in one document is a structure failure under WCAG 2.1 AA.
- **The completeness matrix rendered in the public chrome** while being an
  authenticated page both administered surfaces reach. It uses
  `layouts.workspace`, which picks the navigation the same way
  `LoginController::destinationFor` picks a landing page.
- **A viewer's sidebar would have offered one link to a 403.** The
  dashboard row carries no permission of its own, because everybody in the
  back office may see it; the navigation now returns nothing at all to a
  reader who cannot enter.
- **The admin search escaped `%` and `_` without an `ESCAPE` clause**,
  which made an email address with an underscore in it unfindable rather
  than safer.
- **The primary action on the dashboard was a green button with no text
  in it.** Every button on the platform carries a label, and a test now
  walks them.
- **The dashboard's date was formatted from a UTC instant**, so it showed
  yesterday to anybody in Gaborone before two in the morning.

### Two things built to fill gaps the sidebar exposed

**Sample data**, `/en/admin/samples`: the per-table breakdown behind the
dashboard's total. "196 rows" is a number the Secretariat cannot act on.
**There is no purge button and that is deliberate** — the strip removes two
hundred rows across fourteen tables in one transaction and cannot be
undone, so it is a command run at a terminal by somebody who can read what
it says before confirming. Held by test, including that no route exists
that strips or purges.

**Delegate registrations**, `/en/admin/meetings`: the way in to the
rosters, which were reachable only by knowing a meeting's slug.

### Not done

Screen 09's visitors chart, popular pages and popular search terms still
render as empty states naming what would fill them: there is no analytics
collection on this platform. The notification bell links to the account
page rather than opening a panel.
