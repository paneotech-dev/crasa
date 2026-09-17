# Before the validation workshop

**CRASA Digital Anchor Ecosystem**
Contract ZM-COMESA-CRASA/WEBSITE/25/07/2025-as
Workshop 23 to 25 September 2026, Lilongwe
Rewritten 17 September 2026 against the live host — **six days**

---

## Read this first

Every item has an owner, a date and one of four states: **DONE** (done and
verified today), **NOT DONE** (will be, by the date shown), **BLOCKED**
(waiting on someone else), **WILL NOT BE READY** (plan the workshop around
it).

"Emile" is the platform owner. "Build" is the development work on the
server. "CRASA" is the Secretariat.

### What will not be ready on the 23rd, bluntly

1. **Email, unless a credential arrives.** Nothing the platform sends is
   delivered. The demonstration script now works around it, and says which
   screens cannot be shown.
2. **Authority confirmations.** None of the fourteen regulators has
   confirmed its entry. That is what the confirmation session at the
   workshop is for.
3. **Committee terms of reference, the About page's four sections, and the
   legal pages in final form.** All CRASA's to supply; the pages show named
   gaps or marked drafts.
4. **Photography CRASA owns.** The homepage runs on a supplied boardroom
   photograph whose licence nobody has recorded. The sixty-five photographs
   from crasa.org have no alt text in any language.
5. **Dates for fourteen imported items** (twelve articles, two meetings),
   which now read "Date not stated".
6. **Scripted regression tests (D3).** `qa:crawl` is the interim answer and
   says so.

### What must happen this week or the workshop is at risk

| # | What | Owner | By |
|---|---|---|---|
| A | **The cutover.** `crasadev.paneo.tech` still points at the old server. Delegates will open the old site unless DNS moves | Emile, with the build watching | **21 Sep** |
| B | **Two-factor enrolment of `admin@crasadev.invalid`**, on a phone that travels to Lilongwe. The account opens nothing else until it is done | Emile | 21 Sep |
| C | **Get the code off this server.** The deploy key is read-only, so the build's commits since `ccd8b9e` exist only here and in the nightly encrypted bundle | Emile | 19 Sep |
| D | **One off-machine copy of the backup** (laptop pull, one command) | Emile | 19 Sep |

---

# The items

## 1. Mail — BLOCKED — Emile — decision 21 September

**State:** `MAIL_MAILER=log`. Verified 17 September by a real delivery
attempt: Gmail refuses direct mail from this server (`550 5.7.26`, no SPF or
DKIM for it), so there is no way round a relay.

**Done without you:**

- `docs/MAIL_ACTION.md`, the ten-minute instruction. The recommendation is
  **Brevo**: paneo.tech is already authenticated there (DKIM and DMARC are
  in Cloudflare), the relay is reachable, and no DNS change is needed.
- `sudo crasa-mail-configure brevo you@example.org` asks for the key,
  writes `.env`, sends a real test and **puts `.env` back if the relay
  refuses**. Tested with a wrong key on 17 September: refused, restored.
- `php artisan mail:check` sends one message now and prints the relay's
  answer.
- **`CRASA_ENQUIRY_RECIPIENT` is set to emiletg@gmail.com** on the server,
  so the contact form cannot reach CRASA's real inbox. Change it at
  handover.
- The red mail banner stays, **for administrators only**. Member state
  representatives no longer see it (their screens are on the projector).

**If nothing arrives by 21 September:** the demonstration script's mail
table applies. Screens that cannot be shown: password reset, user
invitation, contact-form delivery, notification emails. The submission and
review steps are still shown; the presenter says the email is waiting on the
mail service. Two-factor sign-in is not affected.

## 2. Backups — DONE on the server; off-machine copy BLOCKED — Emile — 19 September

**Running since 16 September**, nightly at 01:10 UTC: database, all files,
**the code (added 17 September)** and the `.env`, as one encrypted bundle
your SSH keys open, decrypt-verified every night.

**Proven 17 September:** a full restore from the bundle into the rehearsal
database and a scratch copy of the application. Row counts matched
production table for table; all 48 documents and 105 images resolved and
downloaded; two-factor secrets decrypted; the code cloned from the bundle.

**Still true:** every copy is on this one disk until you do one of the
following. `docs/BACKUP_ACTION.md` has each spelled out:

- today: `ssh emile@102.68.86.17 sudo crasa-backup-export > crasa-latest.tar.age`;
- for go-live: an S3 or R2 bucket and `sudo crasa-backup-s3-configure`. The
  push and its verification were proven against a stand-in bucket.

## 3. The cutover — NOT DONE — Emile, build watching — 21 September

`docs/CUTOVER.md`: Origin Certificate, **Full (strict) through a
Configuration Rule for this hostname only**, the DNS change, verification
and rollback. Not run: DNS is not pointed here and no certificate is
installed, as instructed. The self-signed certificate stays for testing and
expires 16 October.

**Why it is on the critical path:** the workshop uses
`crasadev.paneo.tech`, and that name still reaches the old server (the one
in `INCIDENT_2026-09-15.md`).

## 4. Demonstration accounts — DONE; admin 2FA NOT DONE — Emile — 21 September

Eight accounts, all flagged as demonstration data. The two added on
17 September are the Namibia and Zambia representatives, so the review queue
has a return waiting (see item 6). Passwords are in
`/home/emile/crasa-demo-accounts.txt` (mode 600). Nothing about them is in
git or in these documents.

`admin@crasadev.invalid` has **not** enrolled two-factor. Do it before
travelling.

## 5. The import — DONE, one article missing — build

Run 16 September 23:00 to 23:11, every step exit 0.

| Type | Imported |
|---|---|
| Pages parsed | 98 (91 pages fetched, 0 failed to parse) |
| Publications | 35, with 38 documents on disk (39 downloaded) |
| News | 38 (33 current, 5 archived) |
| Meetings | 46 (3 from articles, 43 from the events site) |
| Projects | 4 (2 current, 2 past) |
| Photographs | 65 catalogued, 24 institutional; **69** kept by the importer since 17 September (4 article pictures added) |
| Authority logos | 14 of 14 |
| About page blocks | 4 filled |
| Redirects (legacy records) | 141, including 18 listing pages |

**One failure:** the article "2nd meeting of the Finance and Audit Committee"
did not answer when fetched. Retry with `php artisan legacy:import` after the
cutover. Owner: build, 22 September.

## 6. The demonstration walk — DONE — build — re-walk 22 September

Walked 17 September with the demonstration accounts. It found and fixed:

- the comparator could not take a fourth member state, and after the first
  click its list and exports kept the previous selection (step 5);
- the exported PDF's mark printed black, and its sources were missing;
- the media library answered 500 for every imported photograph;
- twelve articles and two meetings showed the import date as their date;
- the review queue was empty (no Namibia or Zambia returns);
- the mail banner showed on member state screens.

`docs/DEMO_SCRIPT.md` is rewritten step by step. **Re-walk after the
cutover**, through Cloudflare.

## 7. Tests — DONE — build

Full suite green on 17 September. The failures earlier in the run were a
session setting the rebuild procedure had weakened (fixed; `REBUILD.md`
corrected) and a PHP 8.3.6 stat-cache behaviour in a test helper (fixed).

## 8. Security — DONE 17 September — recheck the morning of the 23rd — build

Planted PHP in the uploads is refused and never runs; `.env`, `.git`,
`vendor`, `storage`, `docs`, the scraped archive and the held-for-review
photographs are unreachable; `APP_DEBUG` is off and an error shows no trace;
the country-scope and markup guards are green; search and autocomplete leak
nothing across countries (tested with planted restricted records); CRASA is
isolated from PSC Hub (FPM, CLI, database); PSC Hub answered every
30-second check with 200 throughout.

**Morning of the 23rd:**
`curl -sI https://crasadev.paneo.tech/.env` (403) and
`sudo -u crasa php /var/www/crasa/artisan about --only=environment` (Debug
Mode OFF).

## 9. Go-live clean-up — rehearsed — DECISION needed — Emile and CRASA — before go-live

Rehearsed twice on a restored copy, 17 September:

- **As things stand:** 201 demonstration rows go, every imported record
  stays, no orphans, no errors, 900 pages crawled clean. **All fourteen
  authorities go too**, with their logos, because none is confirmed.
- **After the fourteen confirmations:** the authorities stay. **The six
  committees still go**: no screen confirms a committee.

**Decide before go-live:** add a confirm action for committees, or run the
clean-up without committees
(`content:strip-samples --only=` listing every other type). The eight
demonstration accounts are flagged and would be removed. The command
refuses to run until a real administrator exists.

## 10. Authority confirmations — WILL NOT BE READY before; session at the workshop — CRASA

`/en/admin/confirmations`, one screen per member state, on the projector.
Budget an hour. Confirming is what keeps a regulator through the clean-up.

## 11. Library order — DECISION — Emile — 21 September

The twelve demonstration publications are dated newer than anything
imported, so they lead the library and the homepage's "latest" row, each
marked "Demonstration record". Keep them (step 4 needs the series) or strip
them. The script assumes keep.

## 12. Build housekeeping — build — today

Remove the temporary audit administrator and its credential files, the
scratch copy, the rehearsal database contents and the test accounts file.
The libc6 reboot is scheduled for **17 September 19:30 UTC**; it restarts
PSC Hub as well.

---

# What CRASA still owes

Numbered as in `CLIENT_REQUESTS.md`. State as of 17 September.

| # | Item | State | Blocks |
|---|---|---|---|
| 1 | Photography of its own; **the licence for the boardroom photograph on the homepage** (supplied 9 September, "likely Rawpixel", not recorded) | Owed | Homepage banner, About, Committees and Events bands |
| 2 | Logos: CRASA's own mark in vector | Owed | Exports use the app icon |
| 2 | The fourteen authority logos | **Now on the site**, taken from crasa.org; licence "not stated" | Consent, item 3 |
| 3 | Written consent from each authority to be listed with name and mark | Owed | Go-live |
| 4 | Confirmation of each authority's details | Owed; **workshop session** | Clean-up keeps only confirmed authorities |
| 5 | About page: Governance, Executive Committee, Secretariat, Partnerships | Owed | Visible gaps on About |
| 6 | Terms of reference for the six committees, chairs, seats | Owed | Committee pages; "0 member state seats" |
| 7 | Accession dates | Owed | Members page |
| 8 | Thirteen decisions for the privacy notice and terms of use | Owed | Legal pages remain marked drafts |
| 9 | The strategic plan | Owed | About |
| 10 | Single sign-on details | Owed | Not visible |
| 11 | Upcoming meeting dates | Owed | Events shows "Nothing scheduled publicly" |
| 12 | Indicator values | Owed; the submission form is how | Comparator runs on demonstration figures |
| — | The annual reports themselves | Owed | Library |
| — | Twelve publications whose title and old category disagree | Owed | Sector labels |
| — | **Dates for the fourteen items shown as "Date not stated"** (twelve articles, two meetings) | Owed | Newsroom and events |
| — | Twenty-eight re-cased headlines to check | Owed | Newsroom |
| — | Twenty-four photographs to clear, plus **alt text for all sixty-five** | Owed | Imported photographs cannot be placed in page blocks until described in each language |
| — | Confirmation that the partner logo strip in the Cullen scholarship article may still be shown | **New** | One article |
| — | A mail domain for go-live (SPF and DKIM on crasa.org) and the address enquiries should reach | **New** | Mail at go-live |
