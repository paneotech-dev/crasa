# Overnight run — morning report

**430 tests, 1862 assertions, all passing. Pint clean. Eight commits, each
green before committing.**

Questions are in `docs/QUESTIONS.md`, six of them, each with a recommendation
and what was done in the meantime.

---

## Stages completed

| Stage | State |
|---|---|
| Ruling 1 — contrast tokens | done, `0125754` |
| A6 — nav collapse at 1440 | done, `0125754` |
| Ruling 2 — grid drift, `.b-figure` accent | done, `3379a55` |
| H — fabricated committee membership | done, `3379a55` |
| A1–A3 — hero, block data, section rhythm | done, `9a02a54` |
| Design pass — region map, composition, art direction | done, `1932bea` |
| G — indicators and completeness dashboard | done, `4ef5585` |
| B — authentication and account UI | **already built** as stage 8, `fbdeffa` |
| **F — search** | **not reached** |

Stage B was in the brief twice. It was built and committed earlier in the
evening: 19 routes, 32 tests, login with screen 11's states, 2FA with hashed
recovery codes, password reset, account settings, user administration and the
filterable audit log. It was verified rather than rebuilt.

Stage F is the only numbered stage untouched. Question 5.

---

## The homepage, fixed

**A1. The hero was inside out.** The photograph rendered in a rounded box
below the headline. It is now what screen 02 draws: a full-bleed navy band
with the image behind a gradient scrim running from 95 per cent opaque where
the text sits to 38 per cent at the far edge, which is what makes a headline
readable over a photograph nobody chose for its contrast.

The rotation did not exist. It does now, and it stops on hover and on focus,
never advances while a control inside it holds focus, takes arrow keys,
announces each change through a polite live region, carries an explicit pause,
and under `prefers-reduced-motion` does not rotate at all with the controls
removed rather than left inert.

**A2. Three empty states on a seeded database.** Not the seeders: 30
instruments, 10 publications and 6 articles were all present. The blocks were
written with their empty states first and never given a data source, so they
queried nothing and correctly reported nothing. `BlockData` supplies them now.
The test asserts both directions, which is the part that matters — a seeded
database must not render an empty block, and an unseeded one must still
render the empty state.

**A3. One padding for every section.** Screen 02 uses three tiers by density.
Prose and grids at 96, card strips at 64 because the cards carry their own
padding, dense figure rows at 48.

**A4/H. "Sits on 6 committees", fourteen times.** Every state had been
attached to every committee. Seats went 84 → 0. The member card shows the
authority's sector mandates instead, which are real and vary 1 to 3.

**A5. Uniform card heights.** Fixed across the library, not just the
homepage: every grid carries `align-items: start`, held by test.

**A6. Nav collapsed to a hamburger at 1440.** The measurement asked whether
the nav fitted on one row and went straight to the drawer when it did not.
In French the nine items stop fitting a single row well above 1440. There is
a wrapped state between inline and drawer now; the drawer is only reached
when identity and utilities alone fill the row.

---

## Which grids changed, and which did not

**Changed — `.b-members`.** `auto-fit` with equal fractions gave every member
card the same width whatever it held. It is `auto-fill` on a narrower track
now, and a card claims two columns when its content earns them, decided
server-side from the authority's name length and mandate count.

**Left, deliberately:**

- `.b-cards` — a 16:9 image sets the rhythm; variation shows in card height,
  which already varies.
- `.b-figures__grid`, `.b-pillars__grid` — figures are a number and a short
  label; pillars are peers whose column count is an editor setting.
- `.c-meta`, `.c-codes`, `.c-enrol`, `.c-preview`, `.c-form--filters` — label
  and value pairs, recovery codes, form fields. Genuinely uniform.

Forcing variation onto uniform content is just a different default.

`.b-figure` carried 3px of `--c-green` on all four figures identically,
drawing no distinction at all. Removed.

---

## Indicators: what was sourced and what was dropped

**Fourteen sourced. Two categories dropped.**

Both source documents were **fetched and read**, not recalled: the ITU Core
List of Indicators (March 2022), which reproduces the ITU Handbook for the
Collection of Administrative Data on Telecommunications/ICT with page
references, and the UPU Postal Statistics technical notes. Every indicator
carries the document, the page or heading, and a URL.

**ITU, seven:** fixed-telephone subscriptions (p.25), mobile-cellular (p.43),
fixed-broadband (p.83), active mobile-broadband (p.48), international
bandwidth per inhabitant (p.74), 3G population coverage (p.57), multichannel
TV subscriptions (A10).

**UPU, seven:** licensed operators (2.1), permanent post offices (4.1),
domestic letter-post items (9.2), domestic parcels (10.4), total staff (3.1),
transit-time standard (8.8), percentage within standard (8.10).

**Dropped: spectrum and interconnection.** Neither ITU nor UPU publishes a
definition at the level a regulator reports against; they are regionally
specific and most likely live in the SADC guidelines. Both categories are
seeded empty and a test pins them empty. Question 4.

The demonstration values are deliberately uneven — South Africa and Botswana
complete, Comoros reporting nothing, periods from 2022 to 2025 — because a
dashboard whose purpose is showing gaps proves nothing against a complete
grid. An empty cell reads "Not reported", not zero: a zero is a figure
someone reported.

---

## What is demonstrable, page by page

| Page | State |
|---|---|
| `/{locale}` homepage | Full-bleed hero with rotation, key figures, four pillars, featured comparison (empty state — indicators are not wired to it yet), featured publications and newsroom both showing real records, member grid, social links |
| `/{locale}/about` | Sourced founding statement in three languages, two placeholder-flagged sections |
| `/{locale}/committees` | Intro, mandates placeholder-flagged |
| `/{locale}/members` | **The region map.** Twelve mainland states, Madagascar and Seychelles as context, Comoros and Mauritius as insets, every one linking to its entry |
| `/{locale}/publications` | Faceted by type, state, committee, year; supersession lineage across three generations; thumbnails; download counts |
| `/{locale}/newsroom` | Category, sector and tag filters, pagination, article pages with related items |
| `/{locale}/contact` | Honeypot, rate limit, store-then-queue, click-to-load map |
| `/{locale}/login` + 2FA + reset | Screen 11 states, QR and manual entry, hashed recovery codes |
| `/{locale}/account` | Details, language, notifications, 2FA, password |
| `/{locale}/admin/users`, `/admin/audit` | Role matrix, invite, suspend, filterable trail |
| `/{locale}/completeness` | **New.** Fourteen states against four categories, freshness badges, gaps highlighted, taxonomy with sources |

---

## The honest not-generic audit

Read against CLAUDE.md's list, naming files and lines.

**Clean:** no indigo/violet/purple anywhere; no middle-dot meta strings
(comma separators, `blocks.css:290`); no arrows appended to links; no emoji;
no gradient bars on card edges; no `01/02/03` markers; no scroll-triggered
animation (`AccessibilityTest` fails on any `@keyframes` in the block
stylesheet); tracked caps only in the sanctioned footer carve-out
(`chrome.css:305`).

**Still not right, three findings:**

1. **`blocks.css` — eleven `auto-fit` grids remain** at lines 134, 230,
   256–258, 279, 501, 505, 534, 540, 558. Ruling 2 says leave them where
   content is uniform and I believe that holds for each. But the honest
   version is that the *formula* is still the reflex reach, and only one grid
   on the platform responds to content. If a second one ever should, nothing
   in the code will prompt it.

2. **Elevation still encodes almost nothing.** `--e-1` twice, `--e-3` once,
   `--e-2` never. Three levels exist to encode hierarchy and two are doing
   the work of one. `--e-2` is defined for hover, dropdowns and sticky
   headers; the sticky header is the obvious home and needs a scroll listener
   I have not written.

3. **Radius is honest but lopsided:** `--r-sm` 15 uses against `--r-lg` 3.
   That is defensible — buttons and inputs outnumber cards — but it means the
   card radius is carrying very little of the hierarchy CLAUDE.md says radii
   should encode.

### The substitution test

**Replace CRASA with EACO or ARICEA. Does the design still fit perfectly?**

Mostly yes, and that is the finding.

The **region map is the exception** and it is the only one. It is fitted to
this region, its member fills use the platform's own "in force" green, and
the two island insets exist because *these* fourteen states include Comoros
and Mauritius. Swap in EACO and the map is wrong, which is exactly what
should happen.

Everything else survives substitution intact. The typographic scale, the card
strips, the facet chips, the document rows, the completeness matrix — all of
it would suit any regional regulatory association without a single edit. The
copy is CRASA-specific in ten places (SADC, fourteen, 1997, the Protocol);
the *design* is not.

The three-generation supersession lineage on a publication page is the
nearest thing to a second distinctive idea, and it is under-designed: it
renders as a modest indented list where it should be the page's memorable
moment. That is the gap I would close next, before search.

So: **the platform is specific in its content and generic in its form,
everywhere except the members page.** You would have found that in Lilongwe.

---

## Placeholder content still needed from the Secretariat

1. Governance — AGM, Executive Committee and Secretariat structure, current
   office holders, meeting cycle
2. Strategic priorities — the current cycle and its priorities
3. Committee mandates — terms of reference, chairs, **and which member states
   hold which seats**, now that the fabricated membership is gone
4. Accession dates, and written consent for each authority to be listed
5. **Spectrum and interconnection indicator definitions**, most likely from
   the SADC guidelines
6. A broadcasting photograph — the sector has none, and no image is borrowed
   from another sector
7. Image licences — `LICENCES.csv` source and licence columns are blank for
   all five accepted images

---

## Still underspecified

- `authority_id`/`country_id` consistency is enforced but section 7 never
  said it should be
- Screen 13's matrix has four roles where section 7 has five;
  `committee_member`'s row was derived
- The hero eyebrow: screen 02 sets tracked caps, your carve-out forbids them
  outside the footer. Rendering no eyebrow in the meantime — question 6
