# Validation workshop: demonstration script

**CRASA Digital Anchor Ecosystem**
Workshop 23 to 25 September 2026
Written for whoever presents. No technical knowledge assumed.

*Walked end to end on the live host on 17 September 2026, after the import of
CRASA's own site. Each step below says what was found: **pass**, **fixed**
(a fault found by the walk and corrected the same day) or **rewritten** (the
step now says what the platform actually shows). Mail is treated as not
working throughout; see the table at the end of the steps.*

---

## Say this once, out loud, before you show anything

> "The indicator values, the four data returns and the roaming-guidelines
> series you will see are demonstration data. They have not been
> confirmed by any national regulator. The news, the meetings, the projects, most of
> the library and the regulators' logos are CRASA's own, brought across from
> crasa.org."

**Rewritten.** The old sentence said "the documents are real". Twelve of the
forty-seven publications are demonstration records, and the one step 4 opens
is one of them. It says so on its own page, so say it first.

Say it once and do not repeat it. The rows are flagged in the database, the
strip command deletes every one of them, the admin dashboard counts what is
left, and every export carries the notice in writing.

---

## Before the room fills

Twenty minutes, the morning of.

| Step | Command or action |
|---|---|
| 1 | Open the site and click through the homepage, the comparator and the publications library once. Measured on 17 September: under half a second cold, a quarter of a second warm |
| 2 | **Sign in as the administrator and complete two-factor enrolment if it has not been done.** `admin@crasadev.invalid` is not enrolled: its first sign-in goes to the enrolment page and nothing else opens until it is done. Do this days before, on a phone that will be in Lilongwe |
| 3 | Have three browser windows ready: one signed out, one signed in as the administrator, one signed in as **rep.malawi** (step 7) |
| 4 | Optional fourth window: **rep.namibia**, to show a half-filled draft |

The accounts and their passwords are in `/home/emile/crasa-demo-accounts.txt`
on the server (mode 600). Eight accounts, all flagged as demonstration data:
administrator, editor, committee member, viewer, and representatives for
Botswana, Malawi, Namibia and Zambia.

**Links into the back office from email or chat open signed out.** Session
cookies are `SameSite=Strict` (a security finding). If you click a link to an
admin page from outside the site and land on the sign-in page, reload once or
type the address. Public pages are unaffected.

---

## The order, and why

Nine steps, roughly fifty minutes with questions. Each one sets up the next.

---

## 1. The homepage — two minutes — rewritten

**Go to** the site root.

**Show:** the banner (the boardroom photograph CRASA supplied on 9 September;
its licence is not yet recorded, see PRE_WORKSHOP), the
key figures, the comparison panel, the latest publications and news.

**Point at the comparison panel.** It compares **two** member states on one
indicator, with its own selectors, and opens on Botswana (25, validated,
2026) and **Comoros, "Not reported"**. The old script said four states; the
panel was changed to be something a reader can drive, and "Open these in the
comparator" takes the selection across.

> "Not reported and zero mean opposite things. A regulator reading a zero
> for postal outlets concludes something false about a member state. This
> platform never prints a blank or a zero where a figure is simply absent."

**Know before you start:** the "latest guidelines" row leads with two
demonstration records (they carry 2025 and 2026 dates, newer than anything
imported). Each says "Demonstration record" on its card.

*If asked:* "Those two are the demonstration series we use to show
versioning. The rest of the library is CRASA's own."

The news strip shows three real articles from crasa.org.

**Satisfies:** WS-01, WS-02.

---

## 2. The three languages — one minute — pass

**Do:** switch to French, then Portuguese, with the switcher in the header.

**Then open any news article in French.** The imported articles exist in
English only, and the page says so in French: *"Cette page n'est pas encore
disponible en français. L'interface est en français. Le texte ci-dessous est
affiché en anglais, sa langue d'origine."*

**Say:** every label exists in all three languages. Where a translation has
not been supplied, the English is shown with a notice in the reader's own
language. It is never blank and never machine translated.

**Satisfies:** the trilingual requirement.

---

## 3. Search — two minutes — rewritten

**Do:** search for `roaming`.

**Show:** nine results, grouped by kind: one regulatory instrument, seven
publications, one newsroom item, with a member state filter beside them. The
matched words are highlighted.

(The old script listed "pages" as a group. Search covers instruments,
publications, news and member states; pages are not a result kind.)

**If asked about ElasticSearch:** the platform searches the database rather
than a separate service, because the hosting cannot run one. It is written up
in the variation note. Do not volunteer this.

**Satisfies:** WS-04.

---

## 4. The publications library and supersession — four minutes — rewritten

**Go to** the publications library. **Open** `sadc-roaming-guidelines-2022`.

**Show:** the page says **"Demonstration record. The document itself has not
been supplied and nothing here should be cited."** Say so before anyone reads
it for you. Then the language availability (English, 425 bytes; French and
Portuguese "Not available"), the download (a one-page PDF that says
"Demonstration document"), and the citation block.

**Then the version lineage:** 2015, 2019, **2022 (you are reading this
one)**, then 2025, in force. The old script said 2015, 2019 and 2024.

**Say:** a regulatory document is never edited in place. Each revision is a
new record that supersedes the one before it, the whole chain stays readable,
and a link to the 2015 edition somebody put in a report years ago still works
and says which edition it is.

> "Every download comes from the platform itself. There is no redirect to
> Google Drive or SharePoint anywhere."

**Then open one real annual report** from the library to show a CRASA
document on the same template.

**Satisfies:** RP-01, RP-02, RP-03.

---

## 5. The comparator — eight minutes, the centrepiece — fixed

**Two faults were found here on 17 September and fixed the same day.** With
three states chosen a fourth could not be added; and after the first click
the state list and the export buttons kept the previous selection, so adding
a state replaced the others and Export PDF exported the old comparison. Both
now pass in a browser: the address bar, the "n of 4" count, the export link
and the table agree after every click, and Back works.

**Go to** the comparator. It opens on its empty state.

**Do, in this order:**

1. Click **Compare Botswana and Mozambique**. "2 of 4".
2. Add **South Africa**, then **Comoros**. "4 of 4". Comoros and Mozambique
   read "Not reported" on the licensing indicator.
3. Point at the other states in the list: they are greyed out. Say: up to
   four, because a five-column table stops being readable. Remove one and
   they come back.
4. Point at any figure and the provenance pill under it.
5. Switch to **Browse one indicator**. All fourteen member states, with the
   regional view beside the table.
6. **Copy the address bar and paste it into the chat window.**

**Say at step 6:** the comparison has its own web address. Send it to a
colleague and they see exactly these member states and this indicator.

7. Click **Export PDF** and open the file.

**Say:** CRASA's mark, the figures, the period and a numbered source for each
one, the date, the permalink, and the demonstration notice. The notice stays
until each figure is confirmed by its national regulator.

(Also fixed on 17 September: the exported mark printed as a black disc, and
the file said each figure carried its source while showing only the period.)

**Satisfies:** RP-10, BUILD_SPEC 6.3.

---

## 6. The member states and the map — two minutes — rewritten

**Go to** the members page.

**Show:** the region map, then the fourteen entries, **each with its
regulator's logo.** The old script said the logos were missing. They were
brought across from crasa.org, where CRASA already publishes them.

**Say:** fourteen member states, each linking to its own entry. Comoros and
Mauritius are insets because at this scale they would be two pixels across.

**If someone asks about consent for the logos:** "These are the marks CRASA
already shows on its current site. Each authority confirms its own entry on
the confirmation screen, and we would like to do that with you in this room."

**Then, if the agenda allows, run the confirmation session**
(`/en/admin/confirmations`, administrator window). One screen per member
state, every field editable, and a confirm action that records the delegate
who vouched. Confirming is what keeps a regulator's entry through the
go-live clean-up.

**Satisfies:** WS-03.

---

## 7. Data submission, from the member state's side — six minutes — pass

**Use the rep.malawi window.** Open **Data submission**, then the 2026
return.

**Show:**

1. The four states across the top: Draft, Submitted, Validated, Returned.
2. This return is **Returned**, with a comment against a specific field, not
   against the return as a whole.
3. The form, and the **source field under every figure**.

**Say:** the representative goes straight to what needs changing. A figure
with no source cannot be sent, because when a member state queries a
published figure two years from now, the source is the answer.

**Optional, rep.namibia window:** the 2026 return is a **draft**, half
filled, with the progress panel showing what is left.

**Do not press Send in front of the room unless you mean to.** Sending
emails the Secretariat. See the mail table below.

**Satisfies:** RP-09.

---

## 8. The Secretariat's side — four minutes — fixed (data)

**Fixed:** the demonstration data had no return from Namibia or Zambia, so the
queue had nothing waiting on a decision. Representatives for both were added
on 17 September and the returns seeded: Botswana validated, Malawi returned,
Namibia draft, **Zambia submitted**.

**Switch to the administrator window.** Open **Review queue**.

**Show:** "One return is waiting on you." **Zambia, 2026** is first. Open it.
The figures are read only; the boxes beside them are for comments against a
field, not for editing the figure.

**Say:** the Secretariat reviews a return; it does not rewrite one. If it
could, the member state's name would end up on figures they did not enter.

**Show** the two buttons, **Validate and publish** and **Return with
comments**. Validating publishes the figures to the regional dataset, which
is the moment they appear in the comparator.

**Pressing either one changes the demonstration for the rest of the
workshop** and sends a notification email (see below). If you press one, the
four returns can be put back afterwards with:

```
sudo -u crasa php /var/www/crasa/artisan tinker --execute='$s=new Database\Seeders\DemonstrationSeeder; $m=new ReflectionMethod($s,"submissionsInEveryState"); $m->setAccessible(true); $m->invoke($s);'
```

**Satisfies:** RP-09, RP-04.

---

## 9. The back office — six minutes — pass

**Show, in this order:**

1. **The dashboard.** Real counts, charts from the publications, recent
   activity from the audit log. It also shows two notices only
   administrators see: mail is not delivered, and "205 rows are still
   demonstration data".
   *If asked:* "That is the platform telling the Secretariat what has to
   happen before go-live. It is meant to be impossible to miss."
2. **Content.** Open the About page. Show the block list and the Move up and
   Move down controls, then **Add a block**: a new block arrives hidden. Say:
   adding a block to a live page does not put an empty panel in front of
   fourteen regulators. (Delete the block afterwards; it is audited either
   way.)
3. **Translations.** English, French and Portuguese side by side, with a
   coverage figure for each.
4. **Media.** Sixty-five photographs from crasa.org, each marked **"Alt text
   missing"**. Say: an image without a description in a language does not
   appear on that language's pages at all, and the library says which ones
   need writing.
5. **The audit log.** Every change, who made it, when. No edit and no delete
   control anywhere on it. Say: it cannot be changed by anyone, including an
   administrator.

**Satisfies:** CM-01, CM-03, CM-04, and the audit requirement.

---

## Mail is not working. What that removes from the day

Checked 17 September: `MAIL_MAILER=log`. Every message is written to the
server log and delivered to nobody. `docs/MAIL_ACTION.md` is the ten-minute
fix once a credential exists. **If no credential arrives, these cannot be
shown:**

| What | Where | Can the screen be shown? | Say this |
|---|---|---|---|
| Submission notification to the Secretariat | Step 7, Send | Yes. The state changes; no email leaves | "Sending also emails the Secretariat. Email delivery switches on when CRASA's mail service is connected; the screen you see is the one that sends it." |
| Decision notification to the member state | Step 8, Validate or Return | Yes, same | "The representative is notified by email and inside the platform. The in-platform notice is what you see; the email is waiting on the mail service." |
| Password reset | Not in the script; `/en/password/forgotten` | Only the form. The confirmation appears; no email arrives | "Resets are emailed, and the mail service is being connected. Nobody, including an administrator, can type a password for someone else; that is deliberate." |
| Inviting a new user | Users screen, Invite | **No.** An invited user sets their password from an emailed link, so the invitation goes nowhere | Do not demonstrate. If asked: "The invitation is an email; it switches on with the mail service." |
| Contact form | `/en/contact` | Only the form | "Enquiries go to the Secretariat by email. On this demonstration host they are routed to the project team, not to CRASA's inbox." |
| Notification digests | Not shown | No | Nothing to say unless asked |
| Two-factor sign-in | Step 9 | **Yes, unaffected.** It uses an authenticator app, not email | |

**Demonstration accounts cannot receive email even when mail works**: their
addresses end in `.invalid`. To show a real email arriving, create an account
with your own address the day before (`php artisan user:create --sample`).

---

## What to say when something is empty

| What is empty | What to say |
|---|---|
| The dashboard's visitors, popular pages and search terms panels | "There is no analytics collection on this platform yet. That panel names the source it needs. We would rather show a gap than a number nobody can defend." |
| "Nothing scheduled publicly" on the events page | "The forty-six meetings from CRASA's current site are all in the past. The next ones appear when the Secretariat publishes them." |
| "Date not stated" on some news and two meetings | "CRASA's current site gives no date for those. We would rather say so than print a date we made up." |
| Committee pages: no terms of reference, "0 member state seats" | "The terms of reference are with the Secretariat. Seats fill as each member state confirms its entry, which is the session we can run today." |
| Partnerships on the About page | "Partner and funder attribution has binding rules on wording and emblems. It comes from CRASA; we will not draft it." |
| Privacy notice and terms of use | "Full drafts, marked as drafts. The bracketed items are for CRASA's counsel." |
| Alt text missing on every imported photograph | "Descriptions are written by a person who knows what the picture shows. The library lists every one that needs it." |

---

## Things that will look weak, and what to do about them

### 1. The library and the homepage lead with demonstration records

The twelve demonstration publications are dated 2024 to 2026, newer than
anything imported, so they sort first. **To fix before the day:** strip the
demonstration publications and their files
(`content:strip-samples --only=Document,Publication`),
which also removes step 4's lineage example. **To explain:** the sentence at
the top of this script. **Recommendation: explain.** Step 4 needs the series.

### 2. Twelve articles and two meetings say "Date not stated"

CRASA's site gives no date for them. The list is in `CLIENT_REQUESTS.md`
("Eighteen items with no date anywhere"). A date from the Secretariat fixes
each one in the back office in a minute.

### 3. Committee pages name six committees and say little else

Six paragraphs of terms of reference fill them. It is the cheapest
improvement on this list.

### And one that is not weak but will be asked about

Why the figures are demonstration data: the platform is ready and the data
is the member states' to supply. **Filing a first return live with one
willing member state is the strongest possible close.** It needs that member
state's representative account created the day before.

---

## If something goes wrong

| Problem | What to do |
|---|---|
| A page is slow on first load | Click it again. Say nothing |
| A page shows an error | Move on and note which page. Do not reload repeatedly in front of the room |
| The comparator shows nothing | Check the address bar has `countries=` in it. Click a preset rather than typing |
| Signed out unexpectedly, or a back-office link opens the sign-in page | Sessions last two hours; and links from outside the site do not carry the session. Sign in again, or reload |
| The administrator lands on "Set up two-factor" | The account was never enrolled. It needs the phone. Do this before the day |
| Someone asks a question you cannot answer | "I will get you a written answer this week." Write it down |

---

## Accounts

Eight demonstration accounts exist (see "Before the room fills"). All are
flagged as demonstration data and are removed by the go-live clean-up, which
refuses to run until a real administrator exists. Rotate their passwords, or
remove them, before the platform is shown outside the project.
