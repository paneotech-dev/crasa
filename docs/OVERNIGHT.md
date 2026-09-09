# Long run — report

**563 tests, 2590 assertions, all passing. Pint clean. Nine commits, each
green before committing, none red at any point.**

Sections 9 to 16 are complete. The public site is finished and the
administration back office is built end to end.

| # | Section | Commit |
|---|---|---|
| 9 | Site search | `b545398` |
| 10 | The pages the footer links to, and a crawl that keeps them alive | `8c94ad9` |
| — | The footer destinations, logged as a disclosed variation | `291e75b` |
| 11 | The back office shell and dashboard | `1a6d09d` |
| 12 | Page and block editing | `4adc550` |
| 13 | The media library | `0649bd5` |
| 14 | Translation management | `6c44df1` |
| 15 | CRUD for every administered model | `40d3ada` |
| 16 | Onboarding | `c9c7ca0` |

---

## What the screens decided, and where I departed

Every visual decision in this run was taken from an approved screen, and the
markup or value is quoted in the stylesheet beside the rule that implements
it. Four departures, all logged in `docs/OPEN_ITEMS.md`.

**Screen 09 settled two things a guess would have got wrong.** The back
office nav is a horizontal section bar in the header, not a sidebar:

```
<nav aria-label="Back office" style="display:flex;flex-wrap:wrap;gap:2px;
min-width:0;margin-left:var(--s-4)">
```

And Lora stays on headings; only the interface itself is Inter:

```
h1 style="font-family:var(--f-head);font-size:clamp(25px,3.4vw,34px)"
```

**Screen 14 settled the shape of content editing.** It draws content as
rows in a panel, never as a drag-and-drop canvas:

```
display:flex;gap:24px;align-items:flex-start;padding:18px 24px;flex-wrap:wrap
```

so the block list is rows, and reordering is a Move up and a Move down
button. That is also the only reordering operable from a keyboard without
building a bespoke drag widget with its own live announcements.

**Screen 05 settled the translation editor**, including why the pair views
exist: `220px repeat(3,minmax(0,1fr))` for all three languages and
`220px repeat(2,...)` for English against one, because three columns of a
long body field on a laptop leave nobody enough width to read either.

### The four disclosed variations

| Variation | Reason | Where |
|---|---|---|
| Footer link destinations | Screen 02 draws every footer link as `href="#"`. Silent on a case that must be handled. | Case 2 |
| Screen 09's form fields, input border, breadcrumb, and two stat cards | Static rendition: it draws spans, not inputs. Its `#B9B2A2` input border is 2.11:1 and loses to SC 1.4.11. | Cases 1 and 2 |
| Content editing has no canvas; pages served by slug | No approved screen draws either. | Case 2 |
| Onboarding | No approved screen covers it. | Case 2 |

**And one departure from CLAUDE.md rather than from a screen.** Screen 09
sets every back office table header in tracked caps. Your ruling names table
headers as excluded in terms. The same conflict was already raised for the
hero eyebrow as question 6, and the position taken there was that the ruling
wins. **The same answer is taken here rather than the opposite one on the
same question.** The headers keep the screen's size, weight and quiet ink;
only the letterform changes. One rule in `public/css/admin.css` flips it back.

---

## Computed padding and nav state at the four widths

Nothing here is measured in a browser. These are the computed values, read
from the cascade.

### Section padding

`--section-y` and `--page-x` are tokens with one breakpoint at 767px.

| Width | `--section-y` | `--page-x` | `.b` | `.b--medium` | `.b--tight` |
|---|---|---|---|---|---|
| 375 | `--s-7` = **48px** | `--s-4` = **16px** | 48 | 64 | 48 |
| 768 | `--s-9` = **96px** | `--s-7` = **48px** | 96 | 64 | 48 |
| 1024 | `--s-9` = **96px** | `--s-7` = **48px** | 96 | 64 | 48 |
| 1440 | `--s-9` = **96px** | `--s-7` = **48px** | 96 | 64 | 48 |

**Before and after, for the change this run made.** Only the header changed:

| Width | Header padding, at rest | Header padding, compressed |
|---|---|---|
| 375 | `var(--s-4) var(--page-x)` = 16px / 16px | not applied below 1024 |
| 768 | 16px / 48px | not applied below 1024 |
| 1024 | 16px / 48px | **9px** / 48px |
| 1440 | 16px / 48px | **9px** / 48px |

Two consecutive blocks on the same ground collapse to `padding-block-start: 0`,
so a run of cream sections reads as one section rather than as three with a
seam. Unchanged this run.

### Nav state

| Width | Primary nav | Utilities | Toggle | Drawer | Compressed state |
|---|---|---|---|---|---|
| 375 | hidden (`display:none`) | hidden | shown, `margin-left:auto` | available | n/a |
| 768 | second row: `flex-basis:100%`, `order:3`, 1px `--c-sand` rule above | first row | hidden | none | n/a |
| 1024 | inline, one row | inline | hidden | none | **available** |
| 1440 | inline, one row, full identity lockup | inline | hidden | none | **available** |

Without JavaScript at 375 the nav and the utilities both render inline and
wrap, which is the floor: a reader never loses the navigation.

**New this run: the compressed sticky state.** Screen 02 draws it under the
heading "Compressed sticky state, 1440px":

```
background:var(--c-white);border:1px solid var(--c-sand);
border-radius:var(--r-md);box-shadow:var(--e-2);padding:9px var(--s-5)
img width:30px;height:30px    span font-size:17px
```

It was not built. It is now, at 1024 and up, and it is what closes the
elevation finding below. The state is reported by an IntersectionObserver on
a sentinel at the top of the document: nothing reads a width or a position,
which is the failure mode that broke this header three times earlier in the
build. There is a test that fails if a measurement reappears.

---

## Page by page, what can be demonstrated

### Public

| Route | State |
|---|---|
| `/{locale}` | Homepage, composed from blocks, real data in every one |
| `/{locale}/about` | Six sections; four flagged `needs_content`, each with the footer anchor that lands on it |
| `/{locale}/committees` | Composed; committee mandates flagged |
| `/{locale}/members` | Fourteen member states, region map, real committee membership |
| `/{locale}/publications` | Library with filters, thumbnails, language badges |
| `/{locale}/publications/{slug}` | Document detail, clearance-gated streaming |
| `/{locale}/newsroom`, `/newsroom/{slug}` | Listing and article |
| `/{locale}/search`, `/search/suggest` | Faceted, highlighted, country-scoped |
| `/{locale}/contact` | Form, rate limited, no CAPTCHA |
| `/{locale}/completeness` | Indicator completeness matrix |
| `/{locale}/privacy`, `/terms` | Resolve; legal text flagged as client content |
| `/{locale}/accessibility` | **Written in full**, naming the conformance target and the exceptions |
| `/{locale}/comparator`, `/events` | Resolve; feature is a later phase and the page says so |
| `/{locale}/extranet` | Redirects to sign-in |
| `/{locale}/login`, password reset, 2FA challenge and enrolment | Complete |
| `/{locale}/account`, `/account/suspended` | Complete |
| `/{locale}/{slug}` | **New**: any published page, so a page the Secretariat creates has an address without a deployment |

40 public routes. `SiteNavigationTest` crawls every internal link on every
public page and fails if any stops resolving.

### Administration

| Route | State |
|---|---|
| `/admin` | Dashboard: six real counts, two real charts, real activity from the audit log, three honest empty states |
| `/admin/help` | Onboarding, all twelve block explanations, six platform rules |
| `/admin/pages` | Page list with language coverage and status |
| `/admin/pages/{page}` | Page fields in three languages, workflow, block list |
| `/admin/pages/{page}/blocks/*` | Add, edit, move, duplicate, hide, delete |
| `/admin/pages/{page}/preview` | The real template, unpublished |
| `/admin/translations` | Coverage per record per language |
| `/admin/translations/{type}/{id}` | Side by side or paired, with approve and rewrite |
| `/admin/translations/{type}/{id}/preview/{locale}` | The real resolver, fallback notice and all |
| `/admin/media` | Grid and list, search, filters, missing-alt count |
| `/admin/media/{asset}` | Alt text per language, renditions, rights, usage, replace, delete |
| `/admin/data` | Every administered model with a count |
| `/admin/data/{resource}` | List, create, edit, delete, restore, for eleven models |
| `/admin/users`, `/admin/users/invite` | Accounts, roles, suspension, the permission matrix |
| `/admin/audit` | Filterable, append only |

44 administration routes.

---

## Not built, and why

### From BUILD_SPEC section 3.2

| Item | Why |
|---|---|
| Report builder and output (RP-12) | Out of this run's scope |
| SSO handoff and error states (IN-01) | Integration details still outstanding from CRASA. In "Ask before doing" |
| Public events section (WS-07) | Named in the not-to-build list. Slug resolves and says so |

### Named in the not-to-build list

Comparator tool, submissions and e-Forms, extranet file area and folders,
committee workspace, reports, events, delegate registration, SSO bridge.
Nothing conversational, AI-powered, or touching event and travel management
exists in this codebase.

The comparator and the events slugs resolve rather than 404, because screen
02 draws both links in the footer. Each page says the feature is being
prepared and invents nothing about it. They are deleted, not edited, when
WS-05 and WS-07 are built.

### From DATA_MODEL

Submissions, submission transitions and the extranet folder tree remain
unmodelled. Everything else in sections 2 to 7 is built and reconciled.

---

## The imagery brief

`docs/IMAGE_BRIEF.md` is unchanged and still outstanding. The short version:

- **The hero image is the largest single problem on the site.** The current
  one is consumer register, not institutional. It needs delegates in
  session, a signing, a plenary hall, telecom infrastructure at scale, or a
  regulator's building.
- Landscape, 3000px minimum on the long edge, **subject and interest in the
  right two thirds**, because the type sits left over a scrim.
- **Three images would enable the rotation.** One image shown three times is
  not a rotation and was deliberately not built.
- Eight images in `_review-needed` are held. Two are photographed outside
  the region entirely, which on a Southern African regulatory platform is
  not a small problem.
- Authority marks: fourteen needed, minimum 512px, transparent, SVG
  preferred. One current sample is 412 by 118 on a white plate.
- Licences are yours to resolve. Nothing is blocked on it; the media library
  records rights holder, licence, source and a confirmation date per asset
  so a COMESA query can be answered in seconds.

---

## What the Secretariat has to supply

`php artisan content:placeholders` prints this list with the full note for
each. Twelve blocks:

| Page | Block | What is needed |
|---|---|---|
| home | Hero | Institutional photography, three images for the rotation |
| about | Rich text | Governance: the AGM structure, the meeting cycle, current office holders |
| about | Rich text | Executive Committee: size, election, terms, current chair and vice-chair |
| about | Rich text | Secretariat: base, establishment, the Executive Secretary and functional roles |
| about | Rich text | **Partnerships: client-supplied without exception**, including the attribution wording and marks each partner requires |
| about | Rich text | Strategic plan: the current cycle, its priorities and its dates |
| committees | Pillars | Terms of reference for each of the six committees, chairs, seats |
| members | Rich text | Accession dates, and written consent from each authority to be listed |
| privacy | Rich text | Privacy notice. Legal text, from CRASA and its counsel |
| terms | Rich text | Terms of use. Legal text, from CRASA and its counsel |
| comparator | Rich text | Deleted when WS-05 is built |
| events | Rich text | Deleted when WS-07 is built |

No partner or funder attribution is drafted anywhere. No contract number,
deliverable reference, work-stream code or milestone date appears in any
template, lang file, page title, meta description, alt text or aria-label.
There is a test that fails if one does.

---

## The not-generic audit

### The three open findings

**1. The eleven `auto-fit` grids on the reflex formula — CLOSED.**

I checked every grid formula in the platform against every grid formula
drawn in the approved screens, mechanically:

```
value  kind      files                       in an approved screen?
180px  fit       blocks.css                  yes
196px  fit       admin.css                   yes
220px  fit       blocks.css, chrome.css      yes
236px  fit       admin.css                   yes
260px  fit       blocks.css                  yes
280px  fit       blocks.css                  yes
320px  fit       admin.css                   yes
340px  fit       blocks.css                  yes
420px  fit       blocks.css                  yes
440px  fit       admin.css                   yes
210px  fill      blocks.css                  NO  — deliberate, Ruling 2
150px  fit       blocks.css                  NO  — invented, now fixed
```

`repeat(auto-fit, minmax(min(100%, Npx), 1fr))` is **the approved screens'
own formula**, used throughout screen 02 including its footer at 220px. It
was never drift; I had mistaken the design's own convention for a reflex.

Two exceptions and both are now accounted for. The member grid is
`auto-fill` with a server-computed span, which is the Ruling 2 fix for the
one place content genuinely varies. And `.c-codes` used an invented 150px:
**changed to 160px, a width the screens actually draw.**

**2. `--e-2` unused, so elevation encoded almost nothing — CLOSED, and it
found a missing feature.**

The token was unused because the thing it belongs on was not built. Screen
02 draws a compressed sticky header carrying `box-shadow:var(--e-2)`, and
the header had no compressed state. Building it gives all three elevations a
distinct depth:

| Token | What carries it | Depth |
|---|---|---|
| `--e-1` | header at rest, `.b-card` | resting on the page |
| `--e-2` | header compressed, floating over scrolled content | lifted |
| `--e-3` | the drawer | over everything |

`HeaderBreakpointTest::test_each_elevation_is_used_and_each_one_means_a_different_depth`
fails if any of the three goes unused again.

**3. Radius lopsided 15:3 — CLOSED, with evidence.**

Current counts, and what each is on:

| Token | Uses | On |
|---|---|---|
| `--r-xs` | 9 | inputs, selects, table-cell chips |
| `--r-sm` | 36 | buttons, filters, chips, pager cells, small boxes |
| `--r-md` | 12 | panels, resource tables, the compressed header |
| `--r-lg` | 3 | `.b-card`, `.b-cta__panel`, `.c-article__hero` |
| `--r-pill` | 10 | status badges and coverage bars only |

Every use follows the rule in CLAUDE.md. The distribution is lopsided
because the element population is: a back office is mostly buttons and small
controls, and there are three card-shaped things on the whole platform. A
flat distribution would mean the radii had stopped encoding anything, which
is the failure the rule exists to prevent. **Not closed by changing values;
closed by checking each one against the rule and finding no violation.**

One value to note: `.a-search` takes `--r-sm` rather than `--r-xs`, because
screen 09 draws that field wrapper with `border-radius:var(--r-sm)`. Screen,
not preference.

### The rest of the audit

| Check | Result |
|---|---|
| Indigo, violet, purple, `#615fff`, `#8e51ff`, `#0f172b` | none, anywhere |
| Hard-coded colour outside `tokens.css` | none in `base`, `chrome`, `grid`, `blocks`, `admin` — the guard now covers all five, and strips comments so a stylesheet may quote the screen's literal beside the token it uses instead |
| Emoji standing in for icons | none in any view |
| Middle-dot meta strings | none |
| Arrow characters on link text | none |
| Numbered `01 / 02 / 03` markers | one, on the onboarding steps, which genuinely are a sequence a person works through once in order. Nothing else is numbered |
| Coloured gradient bar down a card's left edge | none. Three coloured left edges exist and each marks a band that stops work: the missing-alt band, the not-ready-to-publish band, and the fallback notice on an untranslated field. Each is drawn that way in screens 05 and 14 |
| Uniform section padding | three tiers, plus the same-ground collapse |
| Fade-and-slide on scroll, hover lift on every card | none. The one scroll-driven change is the compressed header state screen 02 draws, and it is instant under `prefers-reduced-motion` |
| Marketing adjectives (seamless, innovative, robust, leverage, empower, unlock, transform) | none in any lang file |

New files this run, and their lines, for anything above: `public/css/admin.css`
(the tracked-caps rule at 585, the elevation-free panels throughout),
`public/css/chrome.css` 78–105 (the compressed state),
`public/js/chrome.js` 19–43 (the observer),
`public/css/blocks.css` 629 (the 150 to 160 fix).

---

## The substitution test

**On the public site: passes.** Replacing CRASA with EACO or ARICEA breaks
the copy, which is the point. The rendered prose carries, in all three
languages: fourteen member states, SADC, 1997, the Protocol on Transport,
Communications and Meteorology, the three sectors, and the harmonisation
mandate. Counting lang files that carry each: `fourteen` 6, `quatorze` 6,
`catorze` 6, `SADC` 9, `harmonis*` 8, `postal` 10, `1997` 3.

**On the back office: it applies differently, and I want to be exact about
that rather than claim a pass.**

Of 78 prose strings in `admin.php` and `help.php`, most describe mechanics —
what a button does, what happens when you delete something — and would read
correctly on any regulator's platform. That is not a failure. "The last row
is spare. Leave it empty and it is not saved." should not be padded with the
word SADC.

What the test does catch, and what I changed: two strings that were bland
where they had room not to be. The dashboard subtitle and the data section
subtitle now name the three sectors and the fourteen member states.

What passes the test in substance without naming the institution: the copy
that is specific to *this build's decisions* rather than to CRASA. "A
photograph taken at a meeting carries the coordinates of the room, the
camera serial number and often the photographer name." "The original is kept
and never served directly." "Machine drafts are never published as approved
copy." None of that would appear in a generated back office, which is the
underlying thing the test is for.

**Where the back office is unambiguously not generic:** the twelve block
explanations, the six platform rules on the help page, and every empty state
that names the specific data source it is waiting for rather than saying
"no data available".

---

## Two things worth your attention

**1. The dashboard has three visible gaps and they are deliberate.** Screen
09 draws a visitors chart, popular pages, popular search terms and
submission activity. There is no analytics collection on this platform and
the submission workflow is not built, so those four render as empty states
naming what each one needs. A dashboard that invents one number makes every
other number on it unusable, and this platform's figures get quoted in
ministerial briefs. Logging search queries in particular is a decision with
a privacy consequence and has not been taken.

**2. Question 6 in `docs/QUESTIONS.md` now covers two places.** Tracked caps
in the hero eyebrow and in back office table headers are the same conflict
between your ruling and the approved screens. One answer settles both, and
either answer is one CSS rule.

---
---

# Long run, sections 17 to 23 — report

**682 tests, 3713 assertions, all passing. Pint clean. Eight commits, each
green before committing, none red at any point.**

| # | Section | Commit |
|---|---|---|
| — | Question 6 answered: the ruling wins in both places | `b65daad` |
| 17 | The regulatory comparator | `1c31c36` |
| 18 | Submissions and e-Forms | `ff68a10` |
| 19 | Bulk import and export | `946aaa3` |
| 20 | Reports | `ab59144` |
| 21 | Extranet, folders and the committee workspace | `97296e1` |
| 22 | The SSO bridge, or the documented fallback | `2b97088` |
| 23 | Events and delegate registration | `667ab3c` |

**All seven sections were reached.** 121 routes: 66 public, 55
administration.

---

## What remains unbuilt

### From BUILD_SPEC

| Item | State |
|---|---|
| Report builder and output (RP-12) | **Built**, section 20 |
| SSO handoff and error states (IN-01) | **Built as the documented fallback**, section 22. The bridge itself waits on Form 3 |
| Public events section (WS-07) | **Built**, section 23 |
| Everything else in 3.2 | Built in earlier runs |

Nothing in BUILD_SPEC section 3.2 is now outstanding except the SSO bridge
itself, and that is blocked on a client dependency rather than on work.

### From DATA_MODEL

Everything in sections 2 to 7 is built. The tables added this run were
`submissions`, `submission_values`, `submission_field_comments`,
`submission_events`, `reports`, `folders`, `meetings`,
`delegate_registrations` and `notifications`.

Two schema corrections found by tests:

- `indicator_values.confidence` gained **validated**, because a figure the
  Secretariat has checked should not look like one a member state simply
  typed. It is what the comparator's provenance badge reads.
- `pages.status` gained **archived** in the previous run and is noted here
  only because the same enum-rebuild migration pattern was reused.

### Deliberately not built

Nothing conversational, AI-powered, translation-assisting, or search-over-
documents exists in this codebase. `is_machine_draft` is still set to true by
**zero** lines in `app/`, and the test that walks the directory still passes.

There is still exactly **one** notification class, `SubmissionStateChanged`,
and it is about regulatory data. Two separate tests assert that, in sections
18 and 23, because it is the seam through which delegate reminders would
arrive.

---

## What CRASA must supply for the real SSO bridge

**`docs/SSO_REQUIREMENTS.md` is written to be forwarded as it stands.** It
is 154 lines, it asks for exactly what is needed and nothing else, and it
says plainly that nothing is blocked if no answer comes.

The seven items:

1. **Which platform is the identity source** — the Extranet, the EMP, a
   third system, or Entra ID / Google Workspace. And which is authoritative
   if more than one.
2. **Which protocol** — SAML 2.0, OpenID Connect, or neither.
3. **The endpoints** — metadata URL, or entity ID, SSO URL, SLO URL and
   signing certificate. For OIDC, the discovery document or issuer, endpoints,
   JWKS, client ID and secret, and permitted scopes.
4. **Which attribute is the stable identifier.** The document says why it
   must not be the email address: people change addresses, and an email as a
   primary key silently merges or orphans an account when they do.
5. **Whether the identity provider may create accounts here.** This is the
   one with a security consequence and it carries a recommendation: **no.**
   Otherwise anyone the identity provider trusts becomes a user of a platform
   holding country-scoped data and confidential committee papers, without
   anyone at the Secretariat deciding so.
6. **Two test accounts**, one member state and one Secretariat. A bridge
   cannot be built against a provider nobody can sign into.
7. **A named technical contact and the hours they answer in.** A bridge that
   fails at nine in the morning in Gaborone is not a procurement ticket.

It also carries the two values CRASA must allow-list — the callback URL
`https://<host>/{locale}/sso/callback` and the proposed entity identifier —
and states two things that do not change when the bridge lands: the password
form stays, and two-factor still applies to administrators.

---

## The Secretariat's placeholder list

`php artisan content:placeholders` prints this with the full note for each.
**Ten blocks**, down from twelve: the comparator and events placeholders were
deleted, not edited, as promised when they were created.

| Page | Block | What is needed |
|---|---|---|
| home | Hero | Institutional photography, three images for the rotation |
| about | Rich text | Governance: the AGM structure, the meeting cycle, current office holders |
| about | Rich text | Executive Committee: size, election, terms, current chair and vice-chair |
| about | Rich text | Secretariat: base, establishment, the Executive Secretary and functional roles |
| about | Rich text | **Partnerships: client-supplied without exception** |
| about | Rich text | Strategic plan: the current cycle, its priorities and its dates |
| committees | Pillars | Terms of reference for the six committees, chairs and seats |
| members | Rich text | Accession dates, and written consent from each authority to be listed |
| privacy | Rich text | Privacy notice. Legal text, from CRASA and its counsel |
| terms | Rich text | Terms of use. Legal text, from CRASA and its counsel |

Two new items that are not blocks and so do not appear in that list:

- **Committee mandates** now have somewhere to live: the workspace shows a
  designed empty state naming what is missing rather than a blank panel.
- **Meeting records.** The calendar and the events section are built and
  empty. Nothing is seeded, because a fabricated Annual General Meeting date
  on a regulator's platform is worse than an empty calendar.

---

## Underspecified, and what I did

### Raised as question 7: screen 03 uses neither Chart.js nor Leaflet

CLAUDE.md and the brief both name Chart.js for graphs and Leaflet for maps.
**Screen 03 uses neither.** Its regional-view bars are DOM elements that each
state their own figure, and its map is the `RegionMap` component in
`mode="choropleth"`, the same inline SVG already built for the members page.

I built what the screen draws, and gave three reasons in
`docs/QUESTIONS.md`: a canvas chart needs a table equivalent to satisfy the
accessibility clause and these bars already are one; a Leaflet map needs a
tile provider, which is a third-party request from a regulator's browser on a
platform whose pitch is that nothing is hotlinked; and screen 03 says in its
own words that the screen "reads correctly as a printed page and under a
screen reader", which a canvas and a slippy map both defeat.

Swapping either in later is a change to one partial, because the data comes
from `Comparison` rather than from the view.

### Screen 18 draws more than the scope permits

Not a question, because your instruction settles it: the website collects,
the Event Management Platform manages. But the difference is worth stating.
Screen 18 draws accommodation, nights required, dietary requirements and a
transfer schedule. None is built, and the absence is enforced by tests that
walk the column names, the form fields and the routes rather than by a
comment. Logged as a disclosed variation.

### The comparator's country scope

`indicator_values` carries no country scope, and that is deliberate rather
than an omission: it is the published regional dataset that the public
comparator and the completeness matrix both read. A member state's
unvalidated return lives in `submissions` until the Secretariat validates it.
That separation is what keeps the public read simple and safe, and there is a
test that submits a return and asserts the comparator still shows nothing.

---

## Three real defects the tests found

**1. A member state representative could have briefed on any member state.**
Screen 13's matrix grants `ExportReports` **conditionally**, and the report
controller was treating that as an outright grant. Now a policy, with two
rules: a country brief only for their own member state, and no indicator
brief at all, because an indicator brief is by definition the figure across
all fourteen and that is exactly what the condition withholds.

**2. Regional folders were refused to member state representatives.**
`FolderPolicy` read a null `country_id` as "not yours" rather than as
"belongs to no one member state". The model's `countryScopeIncludesShared()`
and `DocumentPolicy` both had it right; the new policy did not. All three
now agree.

**3. `CountryFactory` was flaky.** `lexify('??')` is unique per factory
instance, but the fourteen real member states are already in the table when a
test seeds them, so a collision with a seeded ISO code was a one-in-fifty
failure that would have appeared in CI and nowhere else.

Also, a route-ordering bug: `submissions/template.csv` was being matched as a
submission called "template.csv".

---

## Question 6, resolved

Sentence case for the hero eyebrow and for back office table headers.
Footer column headings remain the only tracked-caps carve-out. Both are
logged in `docs/OPEN_ITEMS.md` as disclosed variations, because screens 02
and 09 both draw tracked caps and CRASA has seen them.

The hero eyebrow is back. It had been rendering as nothing at all while the
question was open, which left the homepage without a line the approved copy
supplies in all three languages.

---
---

# Consolidation run, sections 24 to 28 — report

**728 tests, 5877 assertions, all passing. Pint clean. Five sections, all
reached, none red at any point.**

| # | Section | Commit |
|---|---|---|
| 24 | The variation note | `b4561e9` |
| 25 | The Secretariat pack | `e9b4351` |
| 26 | A demonstrable dataset, and a tested strip | `0e562b0` |
| 27 | Hardening | `8823ecb` |
| — | The region map, restored, with the test that settles it | `a14f893` |
| 28 | The demonstration script | `a1be838` |

Four documents are now ready to send without editing: `VARIATIONS.md`,
`CLIENT_REQUESTS.md`, `SECURITY_REVIEW.md` and `DEMO_SCRIPT.md`. Tests keep
each of them in step with the platform, because a document that quietly
falls out of date turns a disclosed decision back into an undisclosed one.

---

## The security findings, in full

Every one was verified against the running site. Where this says something
executed, it was made to execute.

### 1. The uploads directory executed PHP — Critical, fixed

A file planted in `storage/app/public` and requested over HTTPS returned
`EXECUTED:8.3.33`. It executed as `.phtml` as well. On a host where a
sibling account had two web shells dropped on it the week before, that is
one mistaken file-type check away from the same outcome.

Fixed in three layers, because a cPanel host may run PHP through any of
several handlers and only one layer works for each. Re-tested: `403` for the
canary in both extensions, `200` for a thumbnail.

**The part that mattered as much as the fix:** those rules live in a
directory the repository excludes, so a deployment to a fresh directory
would have arrived without them. The canonical copy is now in the
repository, `php artisan security:harden-uploads` installs it, and `--check`
reports on it. **That command must run after every deployment.**

### 2. Debug mode was on, on a public host — Critical, fixed

`APP_ENV=local`, `APP_DEBUG=true`, on a host whose URL is a public HTTPS
domain. Laravel's debug page prints the environment, which on this host
includes the database password, the mail credentials and the AWS keys, to
anyone who could cause an error. Now `production` and `false`, verified by a
404 that leaks nothing.

### 3. No security headers of any kind — High, fixed

No `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`,
`Referrer-Policy`, `Permissions-Policy` or `Strict-Transport-Security`. The
administration interface could be framed and clicked through, and
administration URLs naming member states leaked as referrers.

All now set. The content policy costs nothing here because it states what
was already true: every asset is served from this origin. A test asserts no
page loads anything the policy would block, so the policy cannot be quietly
outgrown.

### 4. The session cookie was not marked secure — High, fixed

`SESSION_SECURE_COOKIE` was unset, so the session cookie travelled over
plain HTTP as well as HTTPS, on a platform holding confidential committee
papers. Now `secure; httponly; samesite=strict`, encrypted at rest, and no
longer named after the framework. Verified on the wire.

### 5. Four public write paths were unthrottled — Medium, fixed

Sign-in, the two-factor challenge, password reset and delegate registration
had no route-level limit. The sign-in and challenge controllers throttle per
account, which is the more important half, but one address trying one
password across many accounts never reaches a per-account counter.

**My own test then found a fifth:** the single sign-on callback. A test now
walks every route and fails if a public write path is added without a limit.

### 6. No off-site backups — Medium, not fixed, needs a decision

A weekly whole-account cPanel backup runs on Saturdays. There is nothing
hourly and nothing off-site. AWS credentials exist in the environment but
the S3 driver package is not installed, so nothing can write to S3 today:

```
Error: Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found
```

**Exactly what is needed:** `composer require league/flysystem-aws-s3-v3`;
confirmation that the credentials are current and the bucket writable (not
tested, because that would write to a bucket this review does not own); a
backup command; and one cron entry. Roughly half a day once the first two
are settled.

**Related:** this application has no cron entry at all. The account's
crontab runs a scheduler and a queue worker for a different site. Nothing in
the platform currently depends on a queue, but the hourly backup will need
that entry created.

### 7. Mail is written to a log file — Low, not fixed, needs credentials

`MAIL_MAILER=log`. A password reset would report success and deliver
nothing. Needs SMTP credentials or an API key, and the from-address the
Secretariat wants.

### What was checked and found correct

`.env`, `.git`, `vendor`, `composer.json`, `artisan` and `docs/` are all
unreachable from the web. No directory listings. Documents are never served
from the filesystem. The country scope is enforced on reads as a global
scope and on writes by policy, both tested. Confidential documents are gated
on a committee seat. Two-factor is mandatory for administrators. Passwords
are bcrypt at 12 rounds; two-factor secrets and recovery codes are encrypted
at rest. No secret is committed, and a test asserts it. CSRF is on every
state-changing form. The audit trail throws on update and delete.

---

## What remains unbuilt

### BUILD_SPEC

Nothing in section 3.2 is outstanding except the SSO bridge, which is
blocked on Form 3 rather than on work. The documented fallback is built,
tested, and complete as a deliverable.

### DATA_MODEL

Everything in sections 2 to 7 is built.

### Operational, not code

| Item | Needs |
|---|---|
| Hourly off-site backups | The S3 package, credential confirmation, one cron entry |
| Outgoing mail | SMTP credentials |
| A cron entry for this application | Creating, when the backup lands |
| The SSO bridge | Form 3 |

### Content, not code

Ten placeholder blocks and the twelve items in `CLIENT_REQUESTS.md`. Every
one is a place where the software is finished and waiting.

---

## What I would fix first with two more days

**In this order.**

**1. The homepage photograph. Half a day, and it is the highest-value half
day available.** The banner is a technician at an equipment testing bench,
which is the closest the supplied library gets to an institutional subject
and is still wrong for a treaty body that fourteen national regulators
answer to. It is the first thing anyone sees at the workshop. If CRASA has
even one usable photograph of a plenary, a signing or the Secretariat
building, swapping it in is an hour.

**2. Off-site backups. Half a day.** Everything else on this list is
cosmetic beside a platform with one weekly backup on the same infrastructure
it is backing up. The work is small and the exposure is not.

**3. Committee terms of reference, if CRASA sends them. Two hours.** Six
paragraphs turn a page that names six committees and says nothing else into
a page worth reading, and it fills the largest visible gap after the
photograph.

**4. A second look at the region map with fresh eyes. Two hours.** It was
withdrawn and restored during this run on evidence that supported both
readings. `RegionMapTest` now settles the questions I could identify, but
the episode says the map deserves one careful look by someone who has not
been staring at it.

**What I would not spend the two days on:** more features. The contracted
scope is built. The gap between this platform and a finished one is content
and operational configuration, not code.

---

## One correction to an earlier report

The section 27 commit message states that a withdrawal of the region map was
discarded. It was not: the withdrawal was in that commit, and the message
was wrong. Commit `a14f893` restores the map and explains why both readings
were available from the same evidence. The map now renders all fourteen
member states, twelve as mainland shapes and two as inset chips, and a test
holds each of those conditions.
