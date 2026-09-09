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
the role matrix, the filterable audit log, and `crasa:user` which prompts for
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

