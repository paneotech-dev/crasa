# Before the validation workshop

**CRASA Digital Anchor Ecosystem**
Contract ZM-COMESA-CRASA/WEBSITE/25/07/2025-as
Workshop 23 to 25 September 2026, Lilongwe
Written 9 September 2026 — **fourteen days**

---

## Read this first

Ordered by what blocks the most. Every item has one owner and one of three
states.

| | |
|---|---|
| **READY** | Done and verified. Nothing to do |
| **DO** | Not done, and it will be. Named owner, named date |
| **WILL NOT BE READY** | Not achievable in fourteen days, or blocked on something outside our control. Say so now and plan the workshop around it |

**Three things will not be ready and one of them matters.** They are in
their own section at the end. Read that section before the rest.

---

# Blocking the most

## 1. Mail delivery — DO — CRASA, this week

**State: not working.** `MAIL_MAILER=log` on a production host. Every
notification the platform sends is written to a log file and delivered to
nobody. Password resets, submission notifications and enquiry receipts are
all affected.

**Why it is first.** It fails silently. A delegate at the workshop who asks
for a password reset gets nothing, and the platform tells them it was sent.
It also blocks item 2: a backup system whose only failure signal is an email
cannot report that it has stopped.

**What CRASA must provide.** Either a mailbox on a CRASA domain with its SMTP
credentials, or an API key for a transactional service. Plus DNS access for
SPF, DKIM and DMARC — **that is the part that takes longest**, and without it
mail to fourteen government domains is filed as spam with no bounce.

**Everything else is written.** `docs/MAIL_SETUP.md` has both routes, what
each needs, and a verification procedure that ends at a real external
mailbox. Configuration is under an hour once the credentials arrive.

**If nothing arrives:** the back office already shows a red banner on every
screen saying mail is not delivered, so nobody discovers it at the workshop
by accident. Demonstrate the submission workflow without the notification
step, and say why.

---

## 2. Off-site backups — DO — CRASA decides, we build

**State: none.** No backup package, no off-site destination, and **no cron
entry for this application at all** — verified 9 September, the account's
crontab runs a scheduler for a different site and nothing for this one.

At risk: 40 tables holding the submission history, the audit log and every
instrument version, none of it reconstructible; and 195 MB of uploaded
documents that exist nowhere but this disk. This host had two web shells
dropped on a sibling account in the week before this was written.

**What CRASA must provide.** A decision, then two credentials. S3-compatible
bucket, key ID, secret. Or one of the three alternatives in
`docs/BACKUP_SETUP.md` if a cloud account is not possible — a second server
over SSH, cPanel's remote destination, or local backups downloaded weekly by
a named person.

**What we do regardless, this week:** add the cron entry, install the
package, configure retention against a local disk, and run a restore test.
The destination then becomes two lines in `.env` on the day the credentials
arrive, with everything around it already proven.

**The restore procedure has been rehearsed**, on 9 September, against the
real database. It found three defects, all fixed. See item 6.

---

## 3. Demonstration accounts — DO — us, by 19 September

**State: none. Zero user accounts exist on this platform.**

That is deliberate and it is a hard rule: no seeder on this platform creates
an account, because a seeded credential on a public development URL is how a
demo password reached a circulated PDF on this project. A test enforces it.

**What has to happen.** Four accounts created interactively with
`php artisan user:create`, on the day, with passwords chosen at the keyboard
and not written down anywhere that travels:

| Account | For |
|---|---|
| Administrator | The back office walkthrough |
| Secretariat | The submission review queue |
| Member state representative, Botswana | Filing a return, and the country boundary |
| Member state representative, Namibia | Being refused Botswana's folder |

**The fourth account is the point.** The extranet demonstration turns on
showing a real refusal, and that needs a second member state.

**Two-factor is mandatory for administrators.** Enrol the administrator
account on a device that will be in Lilongwe, not on one that stays behind.

---

## 4. The four documents to CRASA — DO — us, by 12 September

All written, all test-guarded so they cannot drift out of step with the
platform, all ready to send unedited.

| Document | Lines | What it is for |
|---|---|---|
| `VARIATIONS.md` | 426 | The disclosed differences from the signed annexure |
| `CLIENT_REQUESTS.md` | 384 | Everything CRASA must supply, ordered by what blocks most |
| `SSO_REQUIREMENTS.md` | 154 | Exactly what is needed for the identity bridge |
| `SECURITY_REVIEW.md` | 274 | The findings, what was fixed, what is open |

**Send them before the workshop, not at it.** Every one contains something
CRASA has to act on, and a document handed over in a plenary is a document
read on the plane home.

Three more should go with them: `BACKUP_SETUP.md`, `MAIL_SETUP.md` and
`SCREEN_AUDIT.md`. The first two are decisions; the third is the conformance
record against the approved screens and is the honest basis for the D2
conversation.

---

## 5. Imagery — WILL NOT BE READY — CRASA

**State: the platform carries two photographs.** A test bench on
telecommunications news cards and a parcel handover on postal ones. Both are
correct for where they sit. Every other public page — homepage, members,
publications, committees, about, events — carries no photograph at all.

**The homepage banner runs on navy and typography.** That is a decision, not
an omission: the supplied library has no institutional subject, and a market
scene or a laboratory bench on the first screen is a false claim about what
CRASA is. It looks composed rather than unfinished, and it is screen 02's own
photograph-free composition.

**This will not change by 23 September** unless CRASA's own archive turns up.
Commissioning is out of time and buying stock reintroduces the problem.

**The single highest-value thing CRASA could send this week** is its own AGM
and committee photography. Even phone-camera pictures from a delegate. A
reader who sat in that room recognises it, and a record of what CRASA did
cannot be wrong about what CRASA is. `CLIENT_REQUESTS.md` section 1 says this
in the terms to forward.

**Plan the walkthrough around it.** Do not apologise for the banner. It is a
deliberate composition and it will read as one unless somebody says otherwise.

---

## 6. Sample data, badged and strippable — READY

**State: verified 9 September against the real database.**

Every demonstration figure carries `is_sample`, and the badge is on screen:
the comparator shows "Demonstration data" and the notice stays in every
export until each value is confirmed by its national regulator. Screen 03
requires exactly that.

The purge was rehearsed end to end — dump, census, dry run, purge, verify,
restore. It removes 196 flagged rows and 76 more behind foreign keys, and
leaves the fourteen member states, three sectors, fourteen indicators and
seven composed pages. Every public route returned 200 against the stripped
database in all three languages.

It found three defects, all now fixed and all now under test: 337 orphaned
translation rows the purge left behind, a database-only restore that broke
every document download, and a documented restore command that could not
authenticate. See the commit for 42.

**Nothing to do before the workshop.** Do not run the purge before it: the
demonstration data is what there is to demonstrate.

---

## 7. Security hardening on the deployed environment — READY, recheck on the day

**State: verified 9 September.**

| Check | State |
|---|---|
| `APP_DEBUG` | `false` |
| `APP_ENV` | `production` |
| Uploads cannot execute PHP | Verified by requesting a planted file, not by reading the config |
| `security:harden-uploads --check` | "The uploads directory is protected" |
| Session cookies | `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` |
| Rate limiting on public write paths | Every route walked by test |

**Do on the morning of 23 September**, because a deploy can undo any of it:
run `php artisan security:harden-uploads --check`, confirm `APP_DEBUG=false`,
and request a planted `.php` file from the uploads directory to confirm it is
refused.

---

## 8. Authority details confirmed — WILL NOT BE READY — CRASA

**State: all fourteen unverified.** Every authority record on the platform is
flagged `is_sample`, which means no member state has confirmed its own
regulator's name, acronym or mandate in writing.

`CLIENT_REQUESTS.md` section 4 carries the table for CRASA to correct, and
section 3 asks for written consent from each authority to be listed with its
name and mark.

**Fourteen confirmations from fourteen national regulators will not arrive in
fourteen days.** They are worth starting now for delivery, not for the
workshop.

**For the workshop this is an asset, not a problem, and it is now built.**

`/{locale}/admin/confirmations` presents the fourteen member states with
their regulator's name, acronym, website, year, mandated sectors and
committee seats. Every field is editable in place, and a confirm action
records who vouched for it and when.

**Run it as a session.** Put it on the projector, work down the list, and let
each delegate correct and confirm their own member state. The count of
confirmed against outstanding is on screen so the room can see progress: ten
of fourteen at the coffee break is a different conversation from a list
somebody promises to return by email.

Confirming clears `is_sample`, so a confirmed authority survives the go-live
purge and an unconfirmed one does not. Every confirmation writes an audit row
naming both the account that made it and the delegate who vouched — those are
rarely the same person, and a confirmation recording only the operator
confirms nothing.

**Budget an hour.** Fourteen states at four minutes each, and it converts the
platform's largest content gap into signed-off data. It also fills
`committee_country`, which is empty today and is the reason every committee
card reads "0 member states".

---

## 9. Content placeholders — WILL NOT BE READY in full — CRASA

**State: ten blocks carry placeholder copy**, each naming exactly what is
missing and who has it.

| Page | Waiting on |
|---|---|
| About | Governance, Executive Committee, Secretariat, Partnerships |
| Committees | Terms of reference for the six committees |
| Members | Accession dates, and written consent to be listed |
| Privacy | The privacy notice, from CRASA's counsel |
| Terms | Terms of use, from CRASA's counsel |
| Home | Banner photography |

The legal pages now carry full drafts rather than holding lines, marked as
drafts CRASA has not reviewed. What is left in them is only what the build
must not invent: retention periods, governing law, jurisdiction, the
supervisory authority, the reuse licence. Each is a named blank saying who
must supply it.

That changes what happens if counsel does not come back in a fortnight. A
delegate opening the privacy page meets a complete document that says it is a
draft, rather than a sentence saying content is being prepared.

**Every placeholder states what it is waiting for and does not pretend.** A
delegate who opens the privacy page sees a designed holding line naming what
CRASA has to supply, not a blank page and not invented legal text.

`php artisan content:placeholders` lists all ten at any time.

---

## 10. Logos and partner marks — WILL NOT BE READY — CRASA

**State: the space is empty and stays empty.**

Partner and funder attribution is supplied by the client, never drafted,
never approximated. The EU emblem in particular has binding rules on
proportion, clear space and accompanying wording, and a wrong one on a
donor-funded platform is raised formally rather than corrected quietly.

**We will not guess at this and it should not be improvised for the
workshop.** If donor visibility must appear on the day, CRASA supplies the
marks and the exact funding statement, and we place them. Otherwise the space
stays empty and that is the correct state.

The fourteen national flags are on the platform and correct — sourced from a
public-domain set, each verified against its authoritative description, with
licences recorded. Those are not the same thing as authority logos, which are
item 8.

---

# What will not be ready, in one place

**1. Imagery.** The banner will run on navy and typography. It is a
composition rather than a gap, and it will read as one unless somebody
apologises for it. The only thing that changes it is CRASA's own archive.

**2. Authority confirmations.** Fourteen written confirmations from fourteen
regulators inside fourteen days is not realistic, and the platform no longer
asks for them by email: the confirmation session at
`/{locale}/admin/confirmations` is built for the room. Whether it is *done*
depends on an hour of the agenda, which is CRASA's to give.

**3. Legal pages, in their final form.** Privacy and terms carry full drafts
and are marked as drafts. Only counsel can close the bracketed items —
retention, governing law, jurisdiction, the supervisory authority — and
counsel does not work to a fortnight. What a delegate meets in the meantime is
a complete document rather than a holding line.

**And two that are not ours to finish:** mail and backups are both one
credential away, and both are listed first because a fortnight is enough time
for CRASA to send a credential and not enough to chase one.

---

# The fortnight, as a plan

| By | Who | What |
|---|---|---|
| 12 September | Us | Send all seven documents |
| 12 September | Us | Cron entry, backup package, retention, restore test against local disk |
| 15 September | CRASA | Mail credentials, or the decision to demonstrate without notifications |
| 15 September | CRASA | Backup destination decision |
| 17 September | CRASA | Anything from the AGM photography archive, if it exists |
| 19 September | Us | Four demonstration accounts created interactively, 2FA enrolled |
| 19 September | Us | Walk the demonstration script end to end on the live host |
| 22 September | Us | Final security recheck, and a fresh database dump kept off the machine |
| 23 September | Us | Morning-of hardening check before the room fills |

---

# Two things worth deciding before the room

**Question 8 is answered and built** — the committees are listed. **Question
9 is open**: whether the meetings calendar should offer a subscribe feed as
well as a download. It is a security decision about a per-user token, and it
is in `docs/QUESTIONS.md` with a recommendation.

**The screen audit is the D2 conversation.** `docs/SCREEN_AUDIT.md` records
all sixteen screens against the build: what conforms, what is missing, and
what was built that no screen draws. Thirty-one gaps were found and several
are now closed. Going into the workshop with that document is a better
position than going in without it, because every gap in it is one we found
rather than one a delegate found.
