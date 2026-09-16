# Disclosed variations

**CRASA Digital Anchor Ecosystem**
Contract ZM-COMESA-CRASA/WEBSITE/25/07/2025-as
Prepared 9 September 2026

---

## Purpose of this note

This document lists every point at which the platform as built differs from
the Terms of Reference, Annexure B or the Technical and Functional
Specifications, together with the reason and the options open to CRASA.

There are eleven. Ten are technical or presentational and none affects a
contracted capability. The eleventh concerns two contractual documents that
disagree with each other, and CRASA's decision is invited.

Nothing here is a reduction in scope. Each item is disclosed because a
variation identified by the client during acceptance is a dispute, and the
same variation identified by the supplier beforehand is a decision. We would
rather you had the list.

The items are grouped by the kind of decision behind them. Within each group
the more consequential come first.

---

# A. Where the hosting environment decided

These three follow from the server CRASA's platform runs on. None was a
preference and none is reversible by us alone.

---

## A1. Laravel 13.30.1 in place of Laravel 11

**What the specification says.** Annexure B names Laravel 11 as the
application framework.

**What was delivered.** Laravel 13.30.1.

**Why.** The base installation predates this contract and was already on a
later version. Laravel 11 has since left active support, which means it no
longer receives routine security patches. Moving the platform backwards to an
older major version would have meant taking a supported framework and
replacing it with an unsupported one, at cost, for no functional gain.

**What this affects.** Nothing a user sees. Laravel 13 is a direct successor
of Laravel 11 and every capability in the specification is available in it.
The practical difference is that the platform will continue to receive
security updates through the contract period and beyond it.

**CRASA's options.**

1. Accept the variation. **Recommended.** It leaves the platform on a
   supported framework.
2. Require Laravel 11. This is possible but would be a downgrade to an
   unsupported version, and we would want that instruction in writing.

---

## A2. MariaDB 10.11 in place of MySQL 8.0

**What the specification says.** Annexure B names MySQL 8.0.

**What was delivered.** MariaDB 10.11, with all tables using the InnoDB
storage engine and the `utf8mb4_unicode_ci` collation.

**Why.** The hosting account provides MariaDB. It is not an option we
selected; it is what the server runs.

**What this affects.** MariaDB began as a fork of MySQL and remains
compatible for everything this platform does. One difference had a practical
consequence, described at C1 below, and it was resolved in a way that
improved the design.

The data itself is portable. If CRASA later moves the platform to a host
running MySQL 8.0, moving the data is a standard export and import.

**CRASA's options.**

1. Accept the variation. **Recommended.** No capability is affected.
2. Require MySQL 8.0. This is a hosting change rather than a software change,
   and would need to be arranged with the hosting provider before it could be
   actioned.

---

## A3. Full-text search on MariaDB in place of ElasticSearch

**What the specification says.** Annexure B names ElasticSearch for full-text
search.

**What was delivered.** Laravel Scout running against MariaDB's built-in
full-text indexing, built behind an interface that allows ElasticSearch to be
substituted later without rewriting the search feature.

**Why.** ElasticSearch cannot be installed on this host. This was verified
rather than assumed: on 8 September 2026 we confirmed that no search service
is running, that no search binary is present, and, decisively, that **there
is no Java runtime on the server**. Both ElasticSearch and OpenSearch require
one. On shared hosting a Java runtime is not something that can be added.

**What this affects.** Searching works, in all three languages, across pages,
publications, news and regulatory instruments, with the results grouped by
type and the matched words highlighted. That is the contracted behaviour and
it is delivered.

The honest limitation is cost rather than capability. Counting how many
results fall into each country, sector, type and year is more expensive to
compute in a database than in a dedicated search index. At CRASA's volume of
fourteen member states and a few hundred documents, this is not noticeable. It
would become noticeable at a scale this platform is not expected to reach.

**CRASA's options.**

1. Accept the variation. **Recommended for now.** The behaviour is delivered
   and the limitation is not reachable at present volumes.
2. Fund a managed ElasticSearch or OpenSearch service. Because the search
   code sits behind an interface, connecting one is a configuration change
   and one new class, not a rebuild. We would estimate this at a small
   number of days plus the ongoing service cost.
3. Move to a host that permits a Java runtime, and install ElasticSearch
   there. This is the most expensive option and we do not recommend it for
   this reason alone.

---

# B. Where a contractual standard overrode a drawn value

WCAG 2.1 Level AA is a contractual acceptance criterion in its own right. In
two places the approved design foundations specify a colour that fails it. In
each case the standard was applied and the delivered colour differs.

---

## B1. Two colours differ from the approved design foundations

**What the specification says.** The design foundations CRASA approved
specify a border colour of `#C9C2B2` and an edge colour used on dark
surfaces of `#3E4A63`.

**What was delivered.** `#908977` and `#5A6680` respectively.

**Why.** WCAG 2.1 Level AA, success criterion 1.4.11, requires that the
visible boundary of a form field or button be distinguishable from its
background by a contrast ratio of at least 3 to 1. The approved values reach
1.77 to 1 and 2.11 to 1. A reader with reduced contrast sensitivity, which
includes most readers over sixty in ordinary office lighting, cannot reliably
see where a form field begins.

The border colour was corrected twice. The first correction cleared white and
cream, the two page grounds on the public site. When the back office was
built, its page ground turned out to be slightly darker than cream, and the
corrected value reached only 2.93 to 1 against it. It was darkened once more
to a value that clears all three grounds.

**What this affects.** The delivered colours are perceptibly darker than the
approved ones but remain within the same family; they read as the same design
decision, made slightly more strongly. Every colour pair in use is now
measured by an automated test that runs on every change, so this class of
problem cannot recur unnoticed.

A third value, the colour of disabled text, sits below the body-text
threshold and was deliberately left unchanged, because the standard exempts
text that forms part of an inactive control.

**CRASA's options.**

1. Accept the variation. **Recommended.** WCAG 2.1 AA is a contractual
   acceptance criterion, and the approved values would fail an accessibility
   audit at acceptance.
2. Require the original values. The platform would then not meet the
   accessibility criterion, and we would need that instruction in writing.
3. Have the designer supply replacement values that both satisfy the standard
   and better match the intended appearance. We would implement them and
   re-run the measurements. This is the best outcome if the visual difference
   matters to CRASA, and it is a small piece of work.

---

## B2. Small capitals used in one place rather than several

**What the specification says.** The approved screens set several labels in
spaced capital letters: the introductory line above the homepage headline,
the column headings in back-office tables, and one or two similar labels.

**What was delivered.** Those labels are set in ordinary sentence case. The
four column headings in the site footer keep the spaced capitals, exactly as
drawn.

**Why.** CRASA's own written direction during the build was that spaced
capital letters should be used for the footer headings alone. That direction
is later than the screens and unambiguous. It was applied consistently rather
than in some places and not others, since applying it selectively would have
been a harder position to defend than either alternative.

The direction also has a readability basis. Spaced capitals are measurably
slower to read than sentence case, and the effect is larger for readers with
dyslexia and for readers working in a second or third language. On a
trilingual platform serving fourteen member states, that is most readers.

**What this affects.** Appearance only. Everything else those labels carry is
exactly as the screens draw it: size, weight, colour, spacing, the rule
beneath a table heading. Only the letterform changes.

**CRASA's options.**

1. Accept the variation. **Recommended**, as it follows CRASA's own written
   direction.
2. Restore the spaced capitals to match the screens. This is a change to two
   rules in one stylesheet and can be done in under an hour.

---

## B3. The back office's main button, in the dark theme

**What the specification says.** Screen 09 draws the back office's main
action button in dark green, `#0E5536`, with white text, and says the dark
theme is the same design with different surface and ink values. It does not
draw the button in the dark theme.

**What was delivered.** In the light theme, exactly what screen 09 draws. In
the dark theme, the brighter green of the dark surface set, `#2FA76F`, with
the dark ground's colour, `#0B1220`, for its text. Hovering the button in the
dark theme fills it with the palette's light green, `#9CCFB2`. All four
values are already in the approved palette; none is new.

They are carried by four named values scoped to the back office, so the two
themes can differ without a second set of rules. Adding named values of this
kind is a design decision the contract places with CRASA.

**Why.** Measured on the rendered pages on 16 September 2026, the drawn
button failed twice in the dark theme:

| | Drawn value | Delivered | Required |
|---|---|---|---|
| The button's edge against a dark card | 1.88 to 1 | 5.47 to 1 | 3 to 1 |
| The button's edge against the dark page | 2.11 to 1 | 6.13 to 1 | 3 to 1 |
| Its text on hover | 1.00 to 1, white on white | 10.68 to 1 | 4.5 to 1 |
| Its text at rest | 6.13 to 1 | 6.13 to 1 | 4.5 to 1 |

White text on the brighter green would have kept the drawn look more
closely, but it measures 3.05 to 1, which fails. Dark text is the only
combination in the palette that passes.

The same measurement found a related fault in **both** themes: where the main
button is a link rather than a form button, the platform's general link
colour was overriding it, so the button's label was green on green and could
not be read at all. That is corrected for every button, chip and avatar that
is also a link.

**What this affects.** The light theme is unchanged. In the dark theme the
main button reads brighter than the drawing suggests and carries dark rather
than white text.

**CRASA's options.**

1. Accept the variation. **Recommended.** The drawn value fails WCAG 2.1 AA
   in the dark theme, which is a contractual acceptance criterion.
2. Have the designer supply dark-theme button values. We would substitute
   them and re-run the measurements.

---

# C. Where two contractual documents disagreed

---

## C1. Chart.js and Leaflet

**This is the one item on which we invite a decision.**

**What the specifications say.** Annexure B names Chart.js for graphs and
Leaflet for maps, both under requirement RP-10 and in the technology stack.

**What the approved screens draw.** Screen 03, the regulatory comparator,
uses neither. Its regional comparison is drawn as horizontal bars in ordinary
page markup, each stating its own figure in words beside it. Its regional map
is the same hand-built map component already used on the member states page.

**What was delivered.** What the accepted screen draws.

**Why.** The two documents conflict, and we followed the one that produces a
platform meeting the other contractual criteria. Three reasons, of which the
first two are themselves contractual requirements:

**Accessibility.** The specification requires that every chart have a table
equivalent, so that the comparator is usable by someone who cannot see the
graph. A chart drawn on a canvas is invisible to a screen reader and needs a
duplicate table built and maintained beside it. Bars that state their own
figures need no equivalent, because they already are one. This is the
stronger form of the same requirement.

**Printing.** The comparator is specified to print, and screen 03 states in
its own annotation that the screen "reads correctly as a printed page and
under a screen reader". A canvas chart and an interactive sliding map both
print poorly or not at all.

**Self-hosting.** A Leaflet map requires map tiles from a third-party
service, which means a regulator's browser making requests to an outside
provider every time the page opens. The platform's stated position, and one
of its selling points, is that nothing is loaded from anywhere else.

**Half of the stack requirement is satisfied as written.** Leaflet is
installed, served from this platform, and in use on the contact page to show
the Secretariat's location in Gaborone. That is a geographic map and Leaflet
is the right tool for it.

Chart.js is not currently used anywhere. If CRASA wishes it to be, the
natural place is the analytics dashboards, which show internal traffic
figures rather than published regulatory data and therefore do not need a
table equivalent.

**CRASA's options.**

1. Accept the variation as delivered. **Recommended.** It satisfies RP-10 and
   the accessibility and print requirements together, and it keeps Leaflet in
   the stack where Leaflet is the right answer.
2. Require Chart.js in the comparator. We would add it and build the table
   equivalent beside it. The comparator's data already comes from a separate
   component, so this is a change to one template. Roughly two days including
   the accessibility work.
3. Require Leaflet in the comparator's regional view as well. We would advise
   against this because of the third-party tile requests, but it is
   achievable.

---

# D. Where the specification's own structure needed correcting

---

## D1. Sector mandates recorded in a linking table

**What the specification says.** The Technical and Functional Specifications
describe a single column on the authorities table holding the list of sectors
each authority regulates, using a database feature called a SET.

**What was delivered.** A separate linking table connecting authorities to
sectors.

**Why.** Two reasons, both confirmed before the work was done.

The SET feature exists only in MySQL and MariaDB. The automated test suite
runs against a lightweight database that has no equivalent, and the hosting
account does not permit creating a second MariaDB database for testing. A SET
column would therefore have left the authorities table permanently untested,
on a platform where the same table drives the comparator, the member
directory and the completeness dashboard.

A SET column also enforces nothing. It stores text that happens to resemble
sector names, with no guarantee that the names match the sectors table. The
comparator and the completeness dashboard both need to ask which regulators
hold a postal or broadcasting mandate; against a linking table that is an
indexed lookup, and against a SET it is a text search that returns wrong
answers when a name is misspelled.

**What this affects.** Nothing a user sees. The data is the same and the
platform behaves identically. It is recorded in a way the database can
enforce and the tests can cover.

**CRASA's options.**

1. Accept the variation. **Recommended.** It is a straightforward improvement
   on the specification.
2. No alternative is proposed. Reverting would make part of the platform
   untestable.

---

# E. Where the approved screens were silent

These are not departures. The screens are complete for what they draw, and
each of the following is a case they do not cover. They are listed for
completeness so that nothing surprises CRASA at acceptance.

---

## E1. Where the footer links lead

Screen 02 draws the site footer in full, every link in its column and in its
order, with each link's destination left unspecified. Seven of them had no
page to lead to.

We built the missing pages: the privacy notice, the terms of use and the
accessibility statement, and gave the four "About" links their own sections
on the About page. No link was added, removed or reordered.

The accessibility statement is written in full, because it is a statement
about this platform and this platform knows the answer. It names the
conformance target and the two colour variations at B1 above, rather than
claiming an unqualified pass. The privacy notice and terms of use await
CRASA's legal text.

---

## E2. Content editing arranges blocks with buttons, not by dragging

Screen 14 draws the content editor as a list of rows. It does not draw a
drag-and-drop canvas, so we did not build one: blocks are reordered with a
"Move up" and a "Move down" button on each row.

This is also the only form of reordering that works for someone using a
keyboard rather than a mouse, which the accessibility criterion requires. If
CRASA later wants dragging as well, it is an addition on top of these
buttons rather than a replacement, and the buttons stay as the keyboard path.

---

## E3. Delegate registration collects, and does not manage

Screen 18 draws a delegate form including accommodation, nights required,
dietary requirements and a transfer schedule.

The platform collects arrival and departure details and gives the Secretariat
a sortable list with a spreadsheet export. It does not record accommodation
or dietary requirements, and it does not schedule transfers or allocate
vehicles.

This follows the agreed division: the website collects the information, the
Event Management Platform manages the operation. The boundary is enforced in
the software itself, by automated tests that fail if a field or a column on
the managing side is ever added.

---

## E4. Onboarding is a panel, not a guided tour

No approved screen covers how a new Secretariat user is introduced to the
back office. We built an introduction as a panel on the dashboard, dismissible
per person and permanently readable afterwards on a help page, rather than as
a pop-up tour.

A pop-up tour cannot be re-read once dismissed, is difficult to operate with
a screen reader, and breaks when the page layout changes. The panel avoids
all three and can be left open while working.

---

## E5. Two smaller silences

**The header at 1024 pixels.** Screen 01 draws the navigation collapsing in
two stages. The exact width of the second stage is not stated; we set it at
1024 pixels, which is the standard tablet-landscape boundary and the point at
which the full navigation stops fitting.

**Pages are addressed by their name.** Every public page originally had its
web address written into the software individually, which meant a page
created by the Secretariat had no address until a developer added one. Pages
are now served by name automatically. This makes the content editor genuinely
useful rather than limited to the pages that already existed.

---

# Summary

| # | Item | Kind | Affects a contracted capability? |
|---|---|---|---|
| A1 | Laravel 13.30.1 | Hosting reality | No |
| A2 | MariaDB 10.11 | Hosting reality | No |
| A3 | Search on MariaDB | Hosting reality | No. Higher query cost at scale |
| B1 | Two colours darkened | Standard overrides drawing | No. Improves compliance |
| B2 | Sentence case for most labels | Client direction | No |
| B3 | Dark-theme main button | Standard overrides drawing; **new named values** | No. **Sign-off invited** |
| C1 | Chart.js and Leaflet | **Two documents conflict** | No. **Decision invited** |
| D1 | Sector mandates in a linking table | Specification correction | No |
| E1 | Footer destinations | Screen silent | No |
| E2 | Buttons rather than dragging | Screen silent | No |
| E3 | Delegate registration scope | Agreed boundary | No |
| E4 | Onboarding as a panel | Screen silent | No |

**C1** requires a decision from CRASA, and **B3** asks for sign-off on four new named values. The rest are disclosed for the
record and, unless CRASA says otherwise, will be treated as accepted.

We are happy to talk any of these through.
