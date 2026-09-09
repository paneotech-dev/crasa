# Screen conformance audit

**CRASA Digital Anchor Ecosystem**
Contract ZM-COMESA-CRASA/WEBSITE/25/07/2025-as
Audited 9 September 2026, against the sixteen screens in `.design/screens/`

---

## Why this exists

D2 acceptance is checked against the approved screens. Four significant
omissions were found in the previous run by looking properly at four screens
— the membership band reduced to a bare `<h1>` on cream, the extranet
borrowing the back office's layout when screen 04 draws its own, the data
page shape built once and not applied, and the map-or-dropdown pair built
half. That is drift, and it was found by accident rather than by process.

This is the process. Every screen, one at a time, three columns.

**This is a conformance check, not a design pass.** Nothing here is an
opinion about whether a screen is right. It records what each screen draws,
what the build does, and where those differ.

**Read the third column as carefully as the second.** Built-but-not-drawn is
not automatically wrong — a contracted work stream with no screen has to be
built from the foundations — but each one has to name its authority or admit
it was invented.

### How to read the verdicts

| Verdict | Meaning |
|---|---|
| **GAP** | Drawn, not built, no decision recorded. Should be closed |
| **DECIDED** | Drawn, not built, and there is a written reason in `OPEN_ITEMS.md`, `QUESTIONS.md` or a scope instruction |
| **SCOPE** | Drawn, and explicitly excluded from this contract |
| **AUTHORISED** | Built, not drawn, and another screen or a contract clause authorises it |
| **INVENTED** | Built, not drawn, no screen authorises it. Named honestly |

---

## Summary

**Sixty-one findings across sixteen screens.** Thirty-one are gaps.

| Screen | Gaps | Decided or scope | Built-not-drawn |
|---|---|---|---|
| 01 Design foundations | 3 (1 partly closed) | 0 | 1 |
| 02 Homepage | 0 | 1 | 2 |
| 03 Regulatory comparator | 2 | 1 | 2 |
| 04 Committee workspace | 3 | 0 | 1 |
| 05 Translation editor | 1 | 0 | 1 |
| 06 Data submission | 0 | 0 | 2 |
| 07 Publications library | 4 (2 closed) | 0 | 1 |
| 08 Members | 5 | 1 | 3 |
| 09 Admin dashboard | 1 | 1 | 2 |
| 10 Trilingual and architecture | 1 | 1 | 0 |
| 11 Extranet login | 0 (1 closed) | 0 | 1 |
| 12 Calendar and folders | 2 (2 closed) | 0 | 1 |
| 13 Users, roles, audit | 0 | 0 | 2 |
| 14 Media and editing | 0 (1 closed) | 0 | 1 |
| 15 Responsive and app | 0 (3 closed) | 0 | 0 |
| 18 Delegate registration | 0 | 4 | 1 |
| **Total** | **31** | **9** | **21** |

---

## 01 — Design foundations

The token layer and the component set every other screen draws from.

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| The full token set, with three measured contrast deviations logged | **Select, choice, upload and search as one component** | Three choropleth tokens, from RegionMap |
| Three button levels, all states | **Modal and card variants** — the modal half | |
| Text input, all states | **Iconography as a set** — icons are inlined per use | |
| Status pills with fixed meanings | | |
| Table and document row | | |
| Alerts and notices, four tones | | |
| Navigation parts | | |
| Image system, slot specification, alt text in three languages | | |

**Select — CLOSED 9 September.** `.c-select` is now defined once in the foundations layer and referenced everywhere. Choice, upload and search remain a GAP.

**Original finding.** Screen 01 draws these as one
specimen. The build styles a select in three separate places: `.a-select` in
the back office, `.c-map-region__select` on the map, and
`.b-compare__control select` on the homepage. Three definitions of one
control is exactly the drift this audit exists to catch, and the next select
somebody adds will be a fourth.

**Modal — GAP, low priority.** Screen 01 draws a modal. Nothing on the
platform opens one: every destructive action confirms on its own page. That
is arguably better than a modal, but it is undocumented, so it is a gap until
somebody writes it down.

**Iconography — GAP, low priority.** Screen 01 specifies Lucide, one library,
consistently. The build honours the library and inlines each icon at its use
site, so there is no single place that says which icons the platform uses.
`x-extranet-icon` is the first component that collects any of them.

**Choropleth tokens — AUTHORISED.** RegionMap draws them; logged in
`OPEN_ITEMS.md`.

---

## 02 — Homepage

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Navy hero band, 640px, the eyebrow, `clamp(32px,5.4vw,58px)` headline, standfirst, action | Photograph behind a scrim | Section band on composed pages |
| The photograph-free slide's data panel and weave | | Live comparison selectors |
| Carousel with pause, keyboard operation, live region, reduced-motion | | |
| Key figures, no count-up on scroll | | |
| Pillars, newsroom strip, member grid, social feed | | |
| Compressed sticky header, 768 and 375 states, drawer | | |
| Alternating grounds down the page | | |

**Photograph — DECIDED.** Section 31 and `CLIENT_REQUESTS.md` section 1. The
band runs on navy and typography until CRASA supplies an institutional
photograph. The screen's own photograph-free slide is what is built.

**Section band — AUTHORISED** by screen 08, which draws it.
**Comparison selectors — INVENTED**, in the sense that no screen draws
selectors on a homepage comparison teaser. The block's own intro promised an
interaction the page did not have, and the alternative was to weaken the
copy. Authorised by nothing; recorded here.

---

## 03 — Regulatory comparator

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Navy masthead with the demonstration notice beside it | **Sort results** — now built, see below | Sortable columns in browse mode |
| Browse and compare modes as links | **Result layout toggle** | PNG, PDF, CSV export |
| Member state and category selectors, four-state maximum | **Chart type toggle** | |
| The table, value types rendered distinctly | | |
| DOM bars stating their own figures | | |
| "Nothing selected yet" | | |
| "Not reported" never blank, never zero | | |
| The 375px transposition | | |

**Sort results — CLOSED 9 September**, section 40. Browse mode sorts by
member state and by value, with "Not reported" last in both directions.

**Result layout toggle — GAP, low priority.** Screen 03 draws an aria-label
"Result layout". One layout is built.

**Chart type toggle — DECIDED.** `chart_type` exists as a block setting and
the comparator renders the table-and-bars treatment only. Logged as the
Chart.js variation in `OPEN_ITEMS.md`: DOM bars beat a canvas on
accessibility and print, both contractual.

**Exports — AUTHORISED** by BUILD_SPEC 6.3.

---

## 04 — Committee workspace

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| The navy left rail, its links, the current state, the clearance panel | **Agenda and participants on a meeting** | Committee grid on the public site |
| Committee header with sector and cycle | **Workplan, 2023 to 2028** | |
| Documents list with clearance badges | **In-page document viewer** | |
| "This document is above your clearance" | | |
| "No meetings scheduled yet" | | |
| Meeting detail | | |
| The confidentiality key: four clearances, colour plus label plus icon | | |

**Agenda and participants — GAP.** Screen 04 draws a meeting with its agenda
and an attendance list. The build shows a meeting's title, date and
documents. Neither an agenda nor an attendance record has a table.

**Workplan — GAP.** Screen 04 draws a committee workplan across a strategic
cycle with items and due dates. Nothing is built and there is no model.
Screen 11 also draws "Workplan items due", so two screens depend on it.

**Document viewer — GAP, medium.** Screen 04 draws documents opening in place.
The build downloads them. Publications have an in-page preview already, so
the mechanism exists on the other surface.

**Committee grid — AUTHORISED by ruling**, 9 September, and derived from
`member_grid`. No screen draws it. `OPEN_ITEMS.md`.

---

## 05 — Translation editor

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Side-by-side source and target | **Cover image and media, per language** | Translation coverage grid |
| Field-by-field editing | | |
| Workflow states on a translation, with approve | | |
| Metadata shared across languages, SEO per language | | |
| What a reader sees on fallback | | |
| The Portuguese proof | | |

**Cover image and media — GAP, low priority.** Screen 05 draws media
attached per language. The build shares one image across all three, which is
correct for a photograph and wrong for a document cover carrying set type.

**Coverage grid — AUTHORISED** by the contract's trilingual clause; screen 10
draws the language layer it reports on.

---

## 06 — Data submission

The most completely conformant screen in the set.

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| All four workflow states, and the transitions between them | — | Bulk import with an inspect-then-commit report |
| "Returned with two comments", field-level comments | | CSV and JSON export |
| The e-Form, sectioned by category, source per field | | |
| Progress by category | | |
| Version history | | |
| Reports | | |
| Review queue as the Secretariat sees it | | |
| Submitted against published | | |

**Import and export — AUTHORISED** by RP-08 and BUILD_SPEC 6.2.

---

## 07 — Publications library

The least conformant public screen. Six gaps.

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Navy band, eyebrow, heading, standfirst | **Sector facet** | Member state facet |
| Facets with counts | **"Available in" language facet** | |
| "No documents match" | **Active filters summary** | |
| Featured band | **Sort results** | |
| Publication detail, in-page preview | **Result layout toggle** | |
| Supersession lineage | **Related publications** | |
| Document details, downloads served from the platform | **Available languages on the detail page** | |
| Translation fallback state | **Share and cite** | |

**Sector facet — CLOSED 9 September.** Screen 07 draws Sector first among its facets.
The build has no sector facet at all. Publications carry `sector_id`; the
filter was simply never added.

**"Available in" — CLOSED 9 September.** A reader who works in French needs to filter
to what exists in French. The data is there — documents carry a locale and
publications have translations — and this is the one facet that is a
trilingual-obligation question rather than a convenience.

**Active filters summary — GAP, medium.** Screen 07 draws the applied filters
as a removable set above the results. The build shows which chips are active
and offers a clear-all only from the empty state, so a reader with four
filters applied and results showing has no single place to see or undo them.

**Related publications, Available languages, Share and cite — GAP, medium.**
All three are drawn on the detail page. None is built. "Share and cite" is
the one a regulator would miss: a citable reference for a document that will
be quoted in a ministerial brief.

**Sort and layout — GAP, low.** As screen 03.

**Member state facet — AUTHORISED** by screen 08, which cross-links
publications by member state, and by the country scoping the contract
requires.

---

## 08 — Members

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Navy band, eyebrow, heading, standfirst, counts row | **Authority mandate text** | Choropleth on the map |
| The region map, selectable, cross-linked | **Location, with a locator** | Map export |
| "Select a member state" as a dropdown | **Representatives and their roles** | National flags |
| Directory of fourteen | **Contact details** | |
| Member profile: acronym, sectors, joined, committees | **Working language** | |
| Regulatory indicators with provenance, open in comparator | | |
| Publications from this member | | |
| Language handling | | |

**Mandate, location, representatives, contact, working language — GAP, high
as a group.** Screen 08's member profile is substantially richer than what is
built. The build shows ISO code, acronym, sectors, accession, committee seats,
filed figures and publications. The screen adds the authority's mandate in
prose, a locator, the named representatives with their roles, contact details
and the working language.

Three of the five need content from CRASA and are already on the Secretariat
list — the mandate text, the representatives and the contact details.
**Working language is derivable today** from `countries.official_languages`,
which is populated. **Location needs a city per authority**, which is one
field and one request.

**Mentioned in the newsroom — GAP, medium**, and not separately counted: the
screen draws news items that mention a member state; the build shows
publications only.

**Choropleth, export, flags — AUTHORISED.** The choropleth is drawn in
RegionMap. The export matches the comparator's, which BUILD_SPEC requires.
The flags are **INVENTED** — no screen draws a flag — and adopted on an
explicit instruction, with sources and licences recorded.

---

## 09 — Admin dashboard

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Horizontal section bar in the header | **Visitors, popular pages, popular search terms** | Onboarding checklist |
| Statistic tiles | | Mail delivery banner |
| Publications by year, by sector and type | | |
| Submission activity | | |
| Recent activity | | |
| The publications table | | |
| The dark surface set, same components both themes | | |

**Analytics panels — DECIDED.** Screen 09 draws sessions over thirty days,
popular pages and popular search terms. No analytics source is provisioned
and none is in the contract's technology stack. Recorded in `OPEN_ITEMS.md`
under deferred items; the dashboard shows the panels it has real figures for
and no invented ones.

**Onboarding — AUTHORISED** by the contract's training and handover clause.
**Mail banner — INVENTED**, and deliberately: `MAIL_MAILER=log` fails
silently and the platform now says so. `MAIL_SETUP.md`.

---

## 10 — Trilingual and architecture

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Three surfaces on one content core | **Donor visibility band** | — |
| The same homepage three times | | |
| The extranet in French and Portuguese | | |
| The back office in Portuguese | | |
| The language switcher, every state | | |
| `/{locale}/` URL scheme | | |
| Fallback with an inline notice, never a blank page | | |

**Donor visibility — DECIDED, and correctly.** CLAUDE.md is explicit:
partner and funder attribution is supplied by the client, never drafted,
never approximated. The space is left empty until CRASA supplies the copy and
the marks. The EU emblem has binding rules and a wrong one on a donor-funded
platform is raised formally.

**SSO bridge — GAP by definition, not by omission.** Screen 10 draws it.
`SSO_REQUIREMENTS.md` sets out exactly what CRASA must supply; the
`IdentityProvider` interface is built and bound to the platform's own store
until Form 3 arrives.

---

## 11 — Extranet login

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Sign-in, email and password | **The extranet dashboard** | Suspended-account route |
| "Credentials not recognised" | | |
| Two-factor challenge, step 2 of 2 | | |
| What each category cannot reach | | |

**The extranet dashboard — CLOSED 9 September**, except the workplan panel.
Screen 11's second half draws the landing page a member reaches after signing
in: next meetings, recently filed in your committees, workplan items due,
notifications. The build redirected straight past it to the folder list, so a
member signed in and landed in a file browser.

Three of the four panels are now built — meetings scoped to the reader's own
committees, recent documents filtered through the same Gate the document page
uses, and notifications — each with its own empty state. The fourth needs the
workplan model that screen 04 also depends on, and remains a gap.

---

## 12 — Calendar and folders

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Country folders, country-scoped | **Month grid** | Upload with clearance |
| Folder tree | **Meeting type filter and colour key** | |
| "Who can see this folder", stated | **Calendar export** | |
| The access boundary, stated on screen | **Week view** | |
| "No meetings scheduled" empty state | | |
| Upcoming, next six months | | |

**The month grid — CLOSED 9 September.** Screen 12 draws a month grid with
meetings on their dates, a colour key by meeting type and a committee filter.
Built: the month is in the query string so a month is a URL; type is carried
by colour and by the label in every cell, which is the screen's own rule; an
empty month says so.

**Calendar export — CLOSED 9 September**, for download. `.ics` per month,
generated per request and filtered through the same Gate the page uses,
because a cached calendar is one that leaks the moment a clearance changes.

**Subscribe feed — GAP, and it needs a decision.** Screen 12 draws "Subscribe,
ICS" alongside the download. A subscription URL is fetched by a calendar
client that cannot present a session, so it needs a per-user token, and a
long-lived token that leaks meeting titles if the URL is forwarded is a
security decision rather than a build one. Logged as Question 9.

**Week view — GAP, low.** Screen 12 draws one. The month grid and the
upcoming list cover the same ground.

---

## 13 — Users, roles, audit

Fully conformant.

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Users and access, with member state and last login | — | Invite and reactivate |
| Roles and permissions matrix, granted, conditional and denied | | Suspension with a reason |
| Two-factor enrolment, setup key, recovery codes, enforced for administrators | | |
| Notification preferences | | |
| Audit log with actor, action and time | | |

Both additions are **AUTHORISED** by the contract's access-management clause.

---

## 14 — Media and editing

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Media library, grid and detail | **Navigation builder** | Usage tracking, "used on" |
| Alt text in three languages, required | | |
| Dimensions, renditions generated, uploaded | | |
| Replace an asset in place | | |
| Homepage and page content editing, block by block | | |
| Editability targets | | |

**Navigation builder — CLOSED 9 September.** Screen 14 draws an interface for
building the site's menus, and the build had no route, no view and no model:
the header read a config array and the footer's four columns were written
into a Blade template.

Built as `menu_items` records with a label per locale, a destination that is
a route, a page or an address, one level of nesting, ordering and show/hide.
Both menus. Config remains as the floor, so an empty table falls back to the
built-in navigation rather than rendering a site with no header.

---

## 15 — Responsive and app

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| 375 and 768 for the homepage | **Installable app: manifest, icon, splash** | — |
| The comparator transposed to cards at 375 | **Offline state** | |
| Extranet dashboard and an administration table at 375 | **What is cached and what is not** | |
| The reduced-motion variant | | |

**The progressive web app — CLOSED 9 September.** Manifest served per locale,
service worker, install prompt on the second visit, offline page in all three
languages, and "save for offline" on a document.

The caching policy is screen 15's own, including the part that is a security
requirement rather than a performance one: nothing behind a sign-in is
touched, and a document is kept only when the server declares it public. See
the run report for what works on Android and what iOS ignores.

---

## 18 — Delegate registration

| Built as drawn | Drawn, not built | Built, not drawn |
|---|---|---|
| Delegate registration form | **Transfer schedule** | Roster CSV export |
| Arrivals roster, sortable | **Grouped transfer scheduling** | |
| Your travel details | **Driver allocation** | |
| | **Hotel and accommodation booking** | |

All four omissions are **SCOPE**, and explicitly so: event and travel
management is reserved for a separate commercial addendum and must not appear
in this codebase. CLAUDE.md states it and the section 23 instruction repeated
it item by item. The screen is titled "travel logistics" in the file name and
the built surface is labelled Delegate Registration everywhere, as instructed.

**Roster CSV — AUTHORISED** by WS-07's reporting requirement.

---

## Closed since this audit was written, 9 September

| # | Gap | Screen | Commit |
|---|---|---|---|
| 1 | Extranet dashboard, three of four panels | 11 | `27e5e07` |
| 2 | Sector facet | 07 | `6ec67df` |
| 3 | "Available in" language facet | 07 | `6ec67df` |
| 4 | One select component | 01 | `6ec67df` |
| 5 | Calendar month grid, type key, committee filter | 12 | `08cdb2a` |
| 6 | Calendar export | 12 | `08cdb2a` |

**Seven of thirty-one gaps closed.** One new question raised: the calendar's
subscribe feed needs a decision about a per-user token (Question 9).

Twenty-four remain, and the list below is what is left of the priority order.

---

## Deferred, with reasoning — decided 9 September

Fourteen days. These are the gaps we are **not** closing before the workshop,
each with the reason. Nothing here is closed by pretending it does not
matter; each is a judgement about what a delegate will meet in Lilongwe.

### Genuinely minor. CRASA will not raise these.

| Gap | Screen | Why it can wait |
|---|---|---|
| Result layout toggle | 03, 07 | One layout is built and it is the right one for regulatory documents. A grid of covers helps a bookshop and not a library |
| Sort results on the library | 07 | The library is filtered, not browsed at length, and it is ordered newest first — which is what a regulator wants nine times in ten. The comparator, where sorting matters, now sorts |
| Week view | 12 | The month grid and the upcoming list cover the same ground. A week view of a calendar that averages two meetings a month shows one meeting or none |
| Modal | 01 | Screen 01 draws one and nothing on the platform opens one: every destructive action confirms on its own page, which is better. Now documented rather than merely absent |
| Iconography as a set | 01 | The library is honoured and every icon is Lucide; what is missing is one place collecting them. A tidiness gap with no user-facing effect |
| Per-language cover image | 05 | Correct for a photograph, wrong for a document cover carrying set type — and CRASA has supplied no document covers in any language, so there is nothing to differ |

### Real, and deferred on a reason.

**Agenda, participants and workplan — screens 04 and 11.** The agenda is
closest: `meetings.agenda` already exists as a column and is unused, so
displaying it is small. Participants and the workplan need models that do not
exist, and the workplan needs a decision about what a workplan item *is* —
who owns one, what states it moves through, whether it is per committee or
per cycle. That is a conversation with CRASA rather than a build, and the
right place for it is the workshop.

**Progressive web app — screen 15.** No manifest, no service worker, no
offline page. It is worth having for delegates on hotel wifi and it is not
worth shipping in a fortnight untested: a service worker that caches the
wrong thing serves a stale page to a regulator and there is no way for them
to tell. Deferred deliberately, and it is the first thing I would build with
two more days.

**Document viewer in the extranet — screen 04.** The mechanism exists on the
public side and moving it is a day. It is deferred because downloading works
and the demonstration script does not walk the extranet's document view.

**Active filters summary — screen 07.** Real, and the chips already show
which filters are active with a clear-all from the empty state. What is
missing is a single place to see and undo four filters at once. A reader with
four filters applied is an unusual reader.

**Mentioned in the newsroom — screen 08.** The member profile shows
publications and not news items mentioning that state. Needs a relation that
does not exist. Small, and nobody has asked for it.

**Analytics panels — screen 09.** No analytics source is provisioned and none
is in the contract's technology stack. Adding one is a privacy decision as
well as a build: the platform's own privacy notice currently says there is no
analytics cookie on any page, and that is a claim worth keeping.

**Calendar subscribe feed — screen 12.** Question 9. It needs a per-user
token and that is a security decision.

---

## What to work through with CRASA

Ordered by what a delegate or a Secretariat user meets first.

| # | Gap | Screen | Why it matters |
|---|---|---|---|
What is left after 9 September, in order.

| # | Gap | Screen | Why it matters |
|---|---|---|---|
| 1 | Progressive web app | 15 | Offline matters on hotel wifi in Lilongwe. Deferred on time rather than on merit |
| 2 | Workplan, and the decision behind it | 04, 11 | Two screens depend on it. What a workplan item *is* needs CRASA, and the workshop is the place to ask |
| 3 | Agenda and participants on a meeting | 04 | `meetings.agenda` already exists as a column and is unused |
| 4 | Document viewer in the extranet | 04 | The mechanism exists on the public side |
| 5 | Member profile: mandate, location, representatives | 08 | All three need content only CRASA has. The confirmation session now collects the website, the year and the sectors |
| 6 | Related publications | 07 | Screen 07 draws it; needs a relation that does not exist |
| 7 | Active filters summary | 07 | Real, and only bites a reader with four filters applied |
| 8 | Choice, upload and search as components | 01 | The select is collected; these three are not |
| 9 | Mentioned in the newsroom | 08 | Needs a relation. Nobody has asked for it |
| 10 | Analytics panels | 09 | A privacy decision as much as a build |
