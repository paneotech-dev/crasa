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

## 4. Indicator taxonomy: what could not be sourced

See stage D notes below. Every seeded indicator carries a `source_standard`
citing a published definition. Anything without one was left out rather than
guessed, and is listed there.

---
