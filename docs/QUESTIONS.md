# Questions from the overnight run

Numbered, each with a recommendation and what was done in the meantime.
Everything here took the reversible option and continued.

---

## 1. No browser was available, so no width pass happened

**Question:** can the Claude in Chrome extension be connected?

Stages A and E both asked for `claude-in-chrome` against the live site at
375, 768, 924 and 1440 with screenshots. The skill is not registered in this
session at all — the earlier session listed it but it refused with "the
Claude in Chrome extension is not set up", and this session does not list it.

**Recommendation:** connect the extension, or do the DevTools pass yourself.
The four-width and keyboard passes are the two things I genuinely cannot do
from the server.

**Done in the meantime:** every layout claim in this run is reasoning from
the CSS, and is labelled as such. Where a rule could be held by a test
instead, it is: card geometry, focus rings, labels, one h1 per page, asset
resolution. The nav collapse in A6 was fixed by reasoning about the
measurement logic, and the reasoning is written into the code.

---

## 2. Stage B was already built

**Question:** none, flagging a duplicate.

Stage B is the authentication and account UI. That was built and committed
earlier as stage 8 (`fbdeffa`): login with screen 11's states, 2FA enrolment
with QR and manual entry, hashed recovery codes, the challenge with a
recovery path, password reset, account settings, user administration with
the role matrix, the filterable audit log, and `user:create` which prompts for
the password and has no password option at all.

**Done:** verified rather than rebuilt. 19 routes, 32 tests in
`AuthenticationTest`. Stage B is reported as complete on that basis.

---

## 3. Committee membership was fabricated

**Question:** which member states actually sit on which committees, and in
what seat?

Every one of the fourteen states was attached to every one of the six
committees, which rendered as "Sits on 6 committees" fourteen times. That is
fabricated uniformity, and on a regulatory platform it is worse than an
acknowledged blank.

**Recommendation:** get the committee register from the Secretariat. It is
already question 3 on the placeholder list.

**Done in the meantime:** the seeded membership is removed entirely. The
member grid shows the authority and no committee count, and the committees
page carries its existing placeholder flag. Nothing invented, nothing
uniform.

---

## 4. Indicator taxonomy: two categories could not be sourced

**Question:** where do the spectrum and interconnection indicator definitions
come from?

DATA_MODEL section 4 names six categories. Fourteen indicators were sourced
and seeded, covering four of them: licensing, market, quality and universal
access. **Spectrum and interconnection have none.**

ITU's Core List and the UPU technical notes, both fetched and read for this
build, define subscriptions, coverage, bandwidth, postal traffic, offices,
staff and quality of service. Neither publishes a definition for spectrum
assignment or for interconnection and termination rates at the level a
regulator would report against. Those are regionally specific, and the most
likely source is the SADC guidelines rather than ITU or UPU.

**Recommendation:** ask the Secretariat for the SADC guideline references for
spectrum and interconnection before Lilongwe. If none exists, those two
categories are a workshop agenda item rather than a reporting gap: the
regulators define them together.

**Done in the meantime:** both categories are seeded empty and a test pins
them empty, so nobody fills them by invention later. Twenty sourced beats
forty with a dozen invented.

---

## 5. Stage F, search, was not reached

**Question:** none, reporting a stage not done.

The night went to the homepage fix, the design pass, the contrast rulings,
stage H and stage G. Search is the one numbered stage untouched.

**Recommendation:** it is the next thing. The decision is already made and
recorded — Scout on MariaDB FULLTEXT behind a driver interface — and the
facet and result patterns it must reuse already exist in the publications
library. The one thing to hold onto from the brief: the country scope must
apply to search results exactly as it does everywhere else, and that needs
its own explicit test, because search is the classic hole in an otherwise
sound authorisation layer.

**Done in the meantime:** nothing. Flagged rather than half-built.

---

## 6. Tracked caps — ANSWERED 2026-09-09

**Answer: the ruling wins, in both places.** Sentence case for the hero
eyebrow and for back office table headers. Footer column headings remain the
only carve-out.

Both are logged in `docs/OPEN_ITEMS.md` as disclosed variations, because
screens 02 and 09 both draw tracked caps and CRASA has seen them.

Done: `.b-hero__eyebrow` and `.b__eyebrow` keep the screens' size, weight,
colour and spacing and drop `letter-spacing` and `text-transform`. The hero
eyebrow, which had been rendering as nothing while the question was open, now
carries the client-supplied copy in all three languages.
`.a-table th[scope="col"]` was already changed under the same reasoning.

---

## 7. Screen 03 draws neither Chart.js nor Leaflet

**Question:** does the comparator keep the screen's own chart and map
treatment, or do Chart.js and Leaflet go in because the stack names them?

CLAUDE.md and your brief both name Chart.js for graphs and Leaflet for maps.
Screen 03 uses neither.

Its "Regional view" bars are DOM elements, each stating its own figure
beside it:

```
<span style="flex:1;height:20px;background:var(--c-sand);border-radius:2px">
  <span style="{{ r.barStyle }}"></span>
</span>
<span style="font-family:var(--f-admin);font-size:12.5px;font-weight:600">{{ r.display }}</span>
```

And its map is the `RegionMap` component in `mode="choropleth"`, the same
inline SVG already built for the members page, not a tile layer.

**Recommendation: keep what the screen draws.** Three reasons.

A canvas chart needs a table equivalent to satisfy the accessibility clause;
these bars *are* text, so they satisfy it by construction and they print. A
Leaflet map needs a tile provider, which is a third-party request from a
regulator's browser on a platform whose whole pitch is that nothing is
hotlinked. And screen 03 says in its own words that the screen "reads
correctly as a printed page and under a screen reader", which a canvas and a
slippy map both defeat.

Leaflet stays vendored and is still the right tool for the contact page's
office location. Chart.js is not currently used anywhere; if you want it,
the natural home is the analytics dashboards, which need no table
equivalent because they are not published figures.

**Done in the meantime:** built the screen's treatment. Swapping either in
later is a change to one partial, because the data comes from `Comparison`
rather than from the view.

---


## Question 8: the committees page cannot list the committees — RESOLVED 2026-09-09

The brief for section 35 asks for counts on committee cards — member states
seated, documents, meetings.

**There are no committee cards.** The committees page carries a rich-text
block and a three-column pillars block describing the three sectors. The six
committees that exist as records — with their mandates, their chair
authorities, their member state seats and their meetings — are not rendered
anywhere on the public site. A reader can find which committees a member
state sits on, from that member state's page. They cannot go the other way.

Listing them needs a `committee_grid` block, and `docs/DATA_MODEL.md` section
8 is explicit about that:

> The twelve block types. Fixed set. **Adding a thirteenth requires a
> decision, not a commit.**

So this is the decision, and it is yours rather than mine.

**Recommendation: yes, add it.** Three reasons.

The data is already complete and already administered. Committees, their
sector, their mandate, their chair authority, their member state seats with
roles, and their meetings are all in the schema and all editable in the back
office. Nothing needs collecting.

The committees are one of the four things CRASA visibly *is* — fourteen
member states, three sectors, the 1997 protocol, and the committees that
carry the harmonisation work between annual general meetings. A site that
names them in its navigation and cannot show them is conspicuously
incomplete, and it is the kind of gap a delegate notices in the first two
minutes because they sit on one.

It costs one block type and changes nothing else. The settings would be
`{sector filter, show_counts}`, matching `member_grid` exactly, and the block
would render from `Committee` records the way `member_grid` renders from
`Country` records. DATA_MODEL section 8 would gain a thirteenth row.

**What is in place meanwhile.** The committees page's section band states the
three real figures — committees, member state seats, meetings recorded — and
each is a link. Every member state page lists that state's committee seats
with the role it holds. So the counts asked for in section 35 exist and are
computed; what is missing is the page that lists the committees themselves.

**If the answer is no**, the honest alternative is to remove "Committees"
from the main navigation and fold the material into the About page, because a
navigation item that leads to three sector descriptions and a placeholder is
worse than no navigation item.

### Ruled: yes, with a condition

> "Yes, add the thirteenth block type for committee cards. But check first
> whether any screen draws a committee list — if one does, build what it
> draws. Twelve was my number, not CRASA's, and the constraint was against
> inventing layouts, not against completing the set."

**Checked. No screen draws a public committee list.** All sixteen were
searched. Screen 04 draws a single committee *workspace* behind
authentication, and its own breadcrumb — "Committees / Spectrum and Frequency
Management" — implies an index it does not draw. Screen 12 has a committee
filter on the calendar. Screen 02 links to Committees from the header and the
footer. No index, anywhere.

So `committee_grid` is derived from `member_grid`, which is this platform's
own pattern for a set of institutional records, and logged as a disclosed
variation in `docs/OPEN_ITEMS.md`. `docs/DATA_MODEL.md` section 8 now reads
thirteen and says a fourteenth needs a decision.

**Two things screen 04 did settle, and both are followed.** Its meta line
puts the sector first. And it writes the empty state out in full: "The Human
and Institutional Capacity Committee has been constituted but has not yet
scheduled its first meeting. Documents and the workplan appear here once the
Chair opens the cycle." A committee with nothing yet is a stage in its life,
not a fault, and the card says which.

**One thing building it exposed.** `committee_country` is empty: nobody has
told us which member states sit on which committee. That made "0 member
states" true of every committee and "0 committees" true of every member
state — fourteen identical zeroes that are a fact about our data rather than
about the region.

Zero is information when it is measured. That zero is not measured, it is
unasked. So while nothing is recorded anywhere, the seat count is withheld on
both sides and the committees page says once, under the grid, that the
composition is still to be confirmed. It is already item 6 on the Secretariat
list.

---


---

## Question 9: should the calendar offer a subscribe feed as well as a download?

Screen 12 draws two export controls beside the month grid: "Subscribe, ICS"
and "Download this month". The download is built. The subscription is not,
and it needs a decision rather than a build.

**Why it is not just more code.** A subscribe URL is fetched by Outlook,
Google Calendar or Apple Calendar on a schedule, with no browser and no
session. It has to authenticate on the URL itself, which means a per-user
token in the query string.

That token is then in the reader's calendar client, in any backup of it, and
in any email where they forwarded the link to a colleague. Anyone holding it
reads that person's meeting titles, committees and locations for as long as
the token lives. On a platform where a meeting title can be "Closed session,
chairs only", that is a disclosure.

**Recommendation: yes, with three conditions.**

1. **A token per user, revocable from their account page**, not a signed URL
   derived from the app key. A reader who forwards a link by mistake needs to
   be able to undo it themselves, and rotating the app key to revoke one
   person's feed would invalidate every password reset link on the platform.
2. **The feed carries only Public and Member clearance**, never Confidential
   or Secretariat, whatever the reader's own clearance is. A calendar client
   caches, syncs to phones and shows notifications on lock screens, and none
   of that is a place for a confidential meeting title.
3. **The account page states what the feed exposes and who can read it**, in
   the same plain terms the extranet rail states clearance.

If that is more than CRASA wants to carry, the download covers the practical
need: a regulator downloads the month and their calendar keeps it. The
difference is that the download does not stay in step when a meeting moves.

**Done in the meantime:** the download, clearance-filtered per request.

---

## Question 10: can the host give us a second database schema?

**Recommendation: yes, ask for one. It costs nothing and it is the
difference between a restore procedure that is tested and one that is
described.**

`php artisan backup:rehearse` proves everything about the backup except the
part that matters most — that the dump actually restores. It cannot, because
the database user holds `ALL PRIVILEGES` on `ptechweb_crasadev` and `USAGE`
on everything else, so it cannot create a schema to restore into. Verified on
9 September, not assumed.

Restoring into the live schema to prove the point is not a rehearsal, it is
an outage.

**What is needed:** one empty database, any name — `ptechweb_restoretest`
would do — with the same user granted privileges on it. On a cPanel account
that is two clicks in the database wizard and needs no new credential.

**What it buys:** `backup:rehearse --database=ptechweb_restoretest` becomes a
full cycle, on a schedule, reporting failure the way any other check does.
The go-live restore is then something that has been done successfully many
times rather than something that will be attempted for the first time under
pressure.

**Done in the meantime:** the rehearsal runs everything else and says plainly
that the restore step was skipped and why. It caught both defects the manual
rehearsal found — a dump command that could not authenticate, and document
rows whose files were missing — and it would catch either again.

---

## Screen 02 draws five colours that are not tokens

*Raised 9 September 2026, during the public polish run.*

**Recommendation: adopt them as four dark-surface tokens, because they are
already drawn and the alternative is a band that does not match the screen.
Substituted the nearest existing tokens in the meantime.**

CLAUDE.md says a colour is a decision, not a commit. Screen 02's navy
comparison band at line 272 uses five hex values, and four of them have no
token:

| Drawn | Where | Served for now by |
|---|---|---|
| `#2C3A55` | panel and table rules | `--c-navy-line` — an exact match, no question |
| `#4A5670` | select border on navy | `--c-navy-edge` `#5A6680`, one step lighter |
| `#7A4606` | notice border | `--c-amber-dark` `#5C3404`, one step darker |
| `#F4E3C8` | notice body text | `--c-amber-mid` `#E5BE87`, slightly duller |
| `#FFF2DC` | notice strong text | `--c-white` |

Each substitution clears SC 1.4.3 or 1.4.11 against its ground, so nothing
is blocked. The band reads slightly cooler than drawn.

**What is needed:** a ruling on whether the four are added as tokens. They
are all dark-surface values and the `--d-*` set already exists for exactly
that purpose, so the natural home is there rather than in the public set.

**Done in the meantime:** the substitutions above, each commented in
`public/css/blocks.css` with the value it stands in for, so replacing them
later is a search for the token name and nothing else.

---

## Screen 02's ground sequence disagrees with the reference in three places

*Raised 9 September 2026. Logged, not blocking — the screen was followed.*

The polish brief gave a ground sequence and the standing rule is that the
approved screen wins. It does, in all three:

| Section | Reference asks | Screen 02 draws | Built |
|---|---|---|---|
| Key figures | cream | navy-mid, line 186 | navy-mid |
| Social feed | navy | white between two sand rules, line 535 | white |
| Members | white | sand, line 604 | sand |

The screen is the more contrasty of the two in every case, which is the
direction the brief wanted, so following it costs nothing.

One departure from the screen is ours and is disclosed. Screen 02 puts a
navy statement band at line 376 between the publications and the newsroom.
We have not built that band, so both sections would land on the cream page
ground and fuse into one run — the exact flatness this work removes. The
newsroom takes white instead, preserving the boundary the screen draws. The
band itself is recorded as a gap in `docs/SCREEN_AUDIT.md`.

---
## The chevron on link cards, and the card radius

*Raised 9 September 2026. Resolved against the screens; recorded because
both went against the brief.*

**Chevron on link cards: rejected.** The brief asks for a circular chevron
affordance on cards. Screen 02 draws two chevrons and neither is on a card:
line 168 is the hero action and line 279 is the comparison action, both
inside filled buttons. Its cards — the publication card at line 341, the
news cards from 415 — carry no chevron and no arrow. The affordance there is
the whole card being the target, which the build already does with a
stretched pseudo-element on the title link, plus a border change on hover.
Adding a chevron to every card would also collide with the standing rule
against an arrow appended to link text.

**Card radius: --r-md, not --r-lg.** The brief asks for roughly 12px, which
is --r-lg at 14px, and the foundations summary in CLAUDE.md says "lg on
cards and modals". The drawings say otherwise and they are unanimous: screen
01 line 754 sets the news, publication and event cards to --r-md and gives
--r-lg only to the modal at line 745; screen 02 declares a radius 125 times
and --r-lg is not among them. Cards are now --r-md and --r-lg has no user on
the public site, which is correct — there is no public modal.

The comment in `public/css/tokens.css` has been corrected to match the
drawings rather than the summary. If the summary is the intended rule and
the screens are the error, this reverses in one commit.

---
## The map now draws Africa, which RegionMap.dc.html does not

> **Answered 9 September 2026: reverted.** The continent came out and the box
> went back to `0 0 820 660`. It cost every member state 28 per cent of its
> size, and the four enlarged panels were carrying weight they were not
> designed for. Everything else this entry describes — the licence record,
> the Lesotho label defect, the attributes — was independent of the continent
> and stayed. The entry is kept below as written, because the reasoning that
> led to it is worth being able to read.


*Raised 9 September 2026. A disclosed variation, taken on the brief's
instruction to log rather than to ask.*

**Recommendation: keep it. The alternative is fourteen shapes on graph
paper.**

RegionMap.dc.html fits its box to the fourteen member states and fills the
space around them with a grid of sand rules: `<path d="M0 90h820M0 200h820
..." stroke="#E5E0D5" stroke-width="0.8">`. That grid is what a map draws
when it has no geography. The build never rendered it, so until today the
region floated on nothing.

The box now opens west and north to hold the continent. Thirty-eight
non-member outlines come from the same Natural Earth release, at the same
Mercator fit, generated by the same method as the members and rounded to
whole pixels because they carry no label, no value and no click. No member
coordinate moved; only the frame around them changed. Recorded in
`docs/LICENCES.csv`.

**What it costs, stated plainly.** The box went from 820 to 1136 wide, so
the member states render at 72 per cent of their former size. Lesotho and
Eswatini were already too small to aim at, which is why
`RegionGeometry::ENLARGED` gives them their own panels beside the map; that
mitigation was built before this change and now carries more weight. The
label type was raised from 13px to 18px in user units, which holds the
apparent size exactly where it was: 13 x 1068/820 and 18 x 1068/1136 are the
same number.

**If CRASA prefers the drawn version**, it is four values in
`config/region-map.php` — `min_x` and `min_y` back to 0, `width` and
`height` back to 820 and 660 — plus dropping the `continent` key. The label
sizes go back to 13 and 11. Nothing else refers to the continent.

**One defect found on the way, now fixed.** The exported map drew each
member state's shape and its label together, in alphabetical order. Lesotho
comes before South Africa, and Lesotho's label sits outside its own border
because the country cannot hold it, so South Africa's fill painted over it.
Eswatini lost the front of "ESCCOM" to Mozambique the same way. Every
exported map, including any already pasted into a brief, showed a member
state with no name. Shapes and labels are now two passes, as the page has
always done them.

---
## A search field in the header: rejected

*Raised 9 September 2026. Resolved against the screen, no change made.*

The brief asked whether the header should carry a search field rather than
an icon, and said to check screen 01 and keep the icon if that is what is
drawn. Both screens draw the icon. Screen 02 line 77 is a 40px square button
carrying the glyph and nothing else; screen 01 draws the same control at
44px, which is the size that ships because it is both the later drawing and
the one that clears the target size.

There is a second reason beyond the drawing. The header already carries nine
primary links, the language group, the extranet button and the identity
block. A field wide enough to type into takes the room the ninth and eighth
links need, and the drawer breakpoint would move.

Search is one click away and the page it leads to is a full search surface.

**Nothing changed.** The header, the language group and the type scale were
all checked against the screens in this run and all three already matched.
`tests/Feature/HeaderAndTypeTest.php` holds them there.

---
## The fourteenth block type: statement_band

*Raised and answered 9 September 2026. Recorded because the block set is
fixed and changing it is a decision.*

DATA_MODEL section 8 fixed the set at twelve. `committee_grid` was the
thirteenth, on a ruling. `statement_band` is the fourteenth, on a second
instruction, and unlike the thirteenth it invents nothing: screen 02 draws
it at line 376, between the publications and the newsroom, and it had never
been built.

Its absence had a visible consequence. Without it the publications and the
newsroom both resolve to the cream page ground, `blocks.css` drops the
padding between two matching grounds, and the two fused into one run. That
was patched by giving the newsroom white and disclosing the variation. The
patch is now removed and the newsroom takes the cream the screen gives it.

**What goes in its grid.** The screen draws a four-column grid of
value-and-label cells with a placeholder count of twelve and does not say
what they hold. `App\Support\StatementGrid` fills it with one cell per
member state showing how many active indicators that state has reported:
fourteen cells, which is the membership rather than a round number chosen to
fill a layout. A state that has reported nothing shows a zero, because a
blank cell reads as a rendering fault and a gap in the register is exactly
what this grid should make visible.

Every figure is a query. Nothing in it is written down.

---
## Two more values the screens draw that are not tokens

*Raised 9 September 2026, alongside the four already logged for the navy
comparison band. Same recommendation, same reason.*

| Drawn | Where | Served for now by |
|---|---|---|
| `10px` radius | the install card's 52px app icon, screen 15 line 378 | `--r-md` at 8px, which reads identically at that size |
| `#B9C2D0` | the hero's state note, screen 02 line 187 | `--c-sand`, which reads warm on navy where that grey reads cold |

Neither is a contrast question; both clear their success criterion either
way. They are listed so that the count of undeclared values in the screens
is six rather than four, and so that a decision about the `--d-*` set covers
all of them at once.

---
## E2 and E3: reported on screen, not reproducible from the server

*Raised 9 September 2026, second time of asking.*

**Recommendation: send one computed style from the browser's inspector. I
have exhausted what the server can tell me.**

Both were investigated by the method asked for and both come back clean.

**E2, the Subject label.** The exact served element, in all three locales:

```html
<label class="c-label" for="subject">Subject <span class="c-required" aria-hidden="true">*</span></label>
<label class="c-label" for="subject">Objet <span class="c-required" aria-hidden="true">*</span></label>
<label class="c-label" for="subject">Assunto <span class="c-required" aria-hidden="true">*</span></label>
```

Every rule that can reach that element, across every non-print stylesheet
the page links, in cascade order:

| Sheet | Selector | Declarations |
|---|---|---|
| blocks.css | `.c-field` | `margin-bottom: var(--s-5)` |
| blocks.css | `.c-label` | `display:block; font-size:14px; font-weight:600; color:var(--c-charcoal); margin-bottom:6px` |
| blocks.css | `.c-required` | `color: var(--c-red)` |

Nothing else matches. `bootstrap-grid.min.css` contributes no `label` rule,
which is what the grid-only build is for. The computed colour is
`--c-charcoal` on the cream page ground: 13.6:1. There is no
visually-hidden span, no transparent colour, no covering pseudo-element,
and the string is not resolving to one character in any locale.

**E3, the BOCRA row.** Rows 1, 2 and 3 are structurally identical — same
elements, same classes, same order. There is exactly **one** positional
selector in every stylesheet on the site, `.c-policy > div:nth-child(2)`,
which belongs to the legal pages and cannot reach a directory row. And
Botswana's record is not distinctive: its authority name is 44 characters
against Angola's 37 and **Comoros' 91**. If length were the trigger, Comoros
would fail first and it does not.

**What I have ruled out.** Stale HTML: the page is `cache-control: no-cache,
private` and Cloudflare reports `cf-cache-status: DYNAMIC`, so the markup
reaching the browser is fresh. Malformed markup: all ten public pages parse
strictly well-formed under html5lib. Stale CSS: every href is versioned by
mtime and the served files match the working tree byte for byte.

**What I cannot rule out from here.** A browser extension injecting CSS, a
forced-colours or high-contrast mode, a user stylesheet, a font that failed
to load and fell back to one with no glyphs, or a zoom/reflow state. All of
those are visible in the inspector and none of them are visible to curl.

**What would settle it in one step:** right-click the Subject label, Inspect,
and send the Computed panel — or just the value of `color`, `font-size` and
`visibility`, plus whatever appears under Styles as overriding `.c-label`.
For the directory, the same for `.b-directory__acronym` on the Botswana row.

**Done meanwhile:** E1 was reproducible and is fixed, with its cause named.

---
