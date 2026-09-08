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

## 6. Tracked caps in the hero conflict with your own carve-out

**Question:** does the hero eyebrow keep its tracked caps?

Screen 02 sets the hero eyebrow in tracked uppercase. Your ruling says the
footer column headings are the only exception, and names section eyebrows
explicitly as not exempt.

**Recommendation:** the ruling should win, as it did for contrast. It is
later than the screen and it is unambiguous.

**Done in the meantime:** the hero renders no eyebrow at all rather than
choosing between them. The headline carries the slide, which is what the
approved copy does anyway.

---
