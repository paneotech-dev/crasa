# Validation workshop: demonstration script

**CRASA Digital Anchor Ecosystem**
Workshop 23 to 25 September 2026
Written for whoever presents. No technical knowledge assumed.

---

## Before the room fills

Twenty minutes, the morning of.

| Step | Command or action |
|---|---|
| 1 | Open the site and click through the homepage, the comparator and the publications library once. The first request after a quiet night is slow; every one after it is not |
| 2 | Sign in as an administrator and open the back office once, for the same reason |
| 3 | Have two browser windows ready: one signed out, one signed in as an administrator |
| 4 | Have a third window signed in as a **member state representative** if you plan to show the submission form from the member state's side. This is the one that needs setting up in advance |

**The demonstration data is already loaded and is labelled as such
everywhere it appears.** If anyone asks whether the figures are real, the
answer is on the screen: every one of them carries a "Demonstration" badge,
and the notice at the top of the comparator says the figures have not been
validated by the member states. That is deliberate and it is worth saying
out loud early, because it is the single most likely question.

---

## The order, and why

Nine steps, roughly fifty minutes with questions. Each one sets up the next,
so try not to reorder them. The comparator lands better after people have
seen where the figures come from.

---

## 1. The homepage — two minutes

**Go to** the site root.

**Show:** the banner, the key figures, the pinned comparison, the latest
publications and news.

**Say:** this is what a member of the public or a ministry official sees.
Everything on it is a record in the platform, not a page a developer wrote,
so the Secretariat changes any of it without a deployment.

**Point at the pinned comparison and stop there for a moment.** Four member
states, one indicator. One of the four is Comoros, and it reads **"Not
reported"** rather than a zero.

> "Not reported and zero mean opposite things. A regulator reading a zero
> for postal outlets concludes something false about a member state. This
> platform never prints a blank or a zero where a figure is simply absent."

That sentence does more work than anything else in the demonstration. It is
the difference between a data platform a regulator will cite and one they
will not.

**Satisfies:** WS-01, WS-02.

---

## 2. The three languages — one minute

**Do:** switch to French, then Portuguese, using the switcher in the header.

**Say:** every page, every label and every piece of content exists in all
three. Where a translation has not been supplied yet, the English is shown
with a notice saying so, in the reader's own language. It is never blank and
it is never machine translated.

**If someone asks why some text is still English:** that is exactly the
behaviour, and it is deliberate. Requiring all three languages before
anything can publish would stop all publishing.

**Satisfies:** the trilingual requirement in the Terms of Reference.

---

## 3. Search — two minutes

**Do:** search for `roaming`.

**Show:** results grouped by what they are — pages, publications, news,
regulatory instruments — with the matched words highlighted.

**Say:** it searches the text of everything, in the language being read.

**If asked about ElasticSearch:** the platform searches over the database
rather than a separate search service, because the hosting cannot run one.
The behaviour is the same; it is written up in the variation note. Do not
volunteer this unless asked.

**Satisfies:** WS-04.

---

## 4. The publications library and supersession — four minutes

**Go to** the publications library. **Open** `sadc-roaming-guidelines-2022`.

**Show:** the document page, the language badge on the file, the download.

**Then point at the lineage strip** showing the 2015, 2019 and 2024
editions.

**Say:** a regulatory document is never edited in place. Each revision is a
new record that supersedes the one before it, the whole chain stays
readable, and a link to the 2015 edition that somebody put in a report three
years ago still works and still says which edition it is.

> "Every download comes from the platform itself. There is no redirect to
> Google Drive or SharePoint anywhere."

**Satisfies:** RP-01, RP-02, RP-03.

---

## 5. The comparator — eight minutes, the centrepiece

**Go to** the comparator. It opens on its empty state.

**Do, in this order:**

1. Click **Compare Botswana and Mozambique**. The comparison appears.
2. Add a third and a fourth member state from the list on the left.
3. Try to add a fifth. It is not offered. Say: up to four, because a
   five-column table stops being readable.
4. **Add Comoros.** Several cells read "Not reported".
5. Point at any figure and read out the source line under it.
6. Switch to **Browse one indicator** and pick one. All fourteen member
   states, with the regional view beside the table.
7. **Copy the address bar and paste it into the chat window or a document.**

**Say at step 7:** the comparison has its own web address. Send that to a
colleague and they see exactly these member states and this indicator. This
is the feature people will actually use, because the thing regulators do
with a comparison is email it.

8. Click **Export PDF**. Open the file.

**Say:** CRASA's mark, the figures, the source and date on every one, the
permalink, and the demonstration notice. That notice stays on the file until
each figure is confirmed by its national regulator. It is not something
anyone has to remember to remove.

**Satisfies:** RP-10, and BUILD_SPEC 6.3.

---

## 6. The member states and the map — two minutes

**Go to** the members page.

**Show:** the region map, then the fourteen entries below it.

**Say:** fourteen member states, each linking to its own entry. Comoros and
Mauritius are shown as separate insets because at this scale they would be
two pixels across.

**If someone asks why the authority logos are missing:** they are on the
list of things we have asked CRASA for. We will not publish an authority's
mark until that authority has confirmed in writing that it agrees to be
listed. That answer usually lands well in a room full of those authorities.

**Satisfies:** WS-03.

---

## 7. Data submission, from the member state's side — six minutes

**Sign in as a member state representative.** Open the submission for the
current period.

**Show:**

1. The four states across the top: draft, submitted, validated, returned.
2. The form itself, and the **source field under every figure**.
3. The progress panel beside it.

**Say:** a figure with no source cannot be sent. When a member state
queries a published figure two years from now, the source is the answer, and
there is no reconstructing it afterwards.

**Then open the returned submission** (Malawi in the demonstration data).

**Show:** the comments attached to specific fields rather than to the
submission as a whole.

**Say:** the representative goes straight to what needs changing instead of
re-reading twelve indicators.

**Satisfies:** RP-09.

---

## 8. The Secretariat's side — four minutes

**Switch to the administrator window.** Open the review queue.

**Show:** submissions waiting on a decision come first. Open one. The
figures are read only.

**Say:** the Secretariat reviews a return; it does not rewrite one. If it
could, the member state's name would end up on figures they did not enter.

**Show** the validate and return buttons, and say that validating publishes
those figures to the regional dataset, which is the moment they appear in
the comparator.

**Satisfies:** RP-09, RP-04.

---

## 9. The back office — six minutes

**Show, in this order:**

1. **The dashboard.** Real counts, real charts from the publications, real
   recent activity from the audit log.
2. **Content.** Open a page, show the block list, move a block, show that a
   new block arrives hidden. Say: adding a block to a live page does not put
   an empty panel in front of fourteen regulators.
3. **Translations.** Open a record. English, French and Portuguese side by
   side, with a coverage figure for each. Show the preview: this is exactly
   what a Portuguese reader sees when a translation is missing.
4. **Media.** Show that an image without alt text in a language does not
   appear on that language's pages at all.
5. **The audit log.** Every change, who made it, when. Say: it cannot be
   edited or deleted by anyone, including an administrator. That is what
   makes it worth reading.

**Satisfies:** CM-01, CM-03, CM-04, and the audit requirement.

---

## What to say when something is empty

Several sections are deliberately empty. Each has a one-sentence answer, and
the answer is always better than improvising.

| What is empty | What to say |
|---|---|
| The dashboard's visitors, popular pages and search terms panels | "There is no analytics collection on this platform yet. That panel names the source it needs. We would rather show a gap than a number nobody can defend." |
| Submission activity on the dashboard | "It fills as member states file returns. You are about to see the form that fills it." |
| Committee mandates | "The terms of reference for the six committees are on the list we have sent the Secretariat. We have not drafted them, because a plausible sentence about a committee's mandate is worse than a visible gap." |
| Partnerships on the About page | "Partner and funder attribution has binding rules on wording and on the proportions of an emblem. We will not draft it. It comes from CRASA." |
| The meetings calendar | "Two demonstration entries only. We have not invented an Annual General Meeting date, because a fabricated date on a regulator's calendar is worse than an empty calendar." |
| The privacy notice and terms of use | "Legal text, from CRASA and its counsel. The pages exist and say so, which is why nothing in the footer is broken." |
| Authority logos | "We will not publish an authority's mark before that authority confirms in writing that it agrees to be listed." |

---

## Three things that will look weak, and what to do about them

**These are my honest assessment. Decide before the day whether to fix or
explain.**

### 1. The homepage photograph — the most visible weakness

It is a technician at an equipment testing bench. It is the closest thing in
the supplied image library to an institutional subject, and it is still not
right: CRASA is a treaty body that fourteen national regulators answer to,
and the homepage should open on delegates in session, a signing, a plenary
hall, infrastructure at scale, or the Secretariat building.

Fourteen regulators looking at their own platform's front page will notice.

**To fix:** three photographs, landscape, 3000 pixels on the long edge,
subject in the right-hand two thirds. Half a day once the images exist. The
brief is in `docs/CLIENT_REQUESTS.md`.

**To explain:** "The banner is a holding image. The brief for what replaces
it is with the Secretariat and it asks for CRASA's own photography, which is
worth more than anything a stock library holds."

**My recommendation: fix it if any usable photograph exists**, even one.
This is the first thing anyone sees.

### 2. Fourteen authorities with no logos

The member states page is names and text where it should be names and marks.

**To fix:** the fourteen marks, which need the authorities to send them.
Not achievable before 23 September unless CRASA already holds them.

**To explain:** the consent answer above. It reframes an absence as care,
and in that room it is also true.

**My recommendation: explain it.** Asking fourteen regulators for their
logos during the workshop is a good use of the workshop.

### 3. Committee pages that name six committees and say nothing else

Anyone who clicks Committees gets six names and a holding line.

**To fix:** six paragraphs of terms of reference. CRASA has them; it is a
matter of sending them.

**To explain:** as in the table above.

**My recommendation: ask for them the week before.** It is the cheapest
improvement on this list and it fills a whole page.

### And one that is not weak but will be asked about

Somebody will ask why the figures are demonstration data rather than real.
The answer is that the platform is ready and the data is the member states'
to supply, which is what the submission form exists for. **The workshop is
the right place to walk them through filing their first return.** If time
allows, doing that live with one willing member state is the strongest
possible close.

---

## If something goes wrong

| Problem | What to do |
|---|---|
| A page is slow on first load | Click it again. Say nothing; it is a cold cache |
| A page shows an error | Move on to the next step and note which page. Do not reload repeatedly in front of the room |
| The comparator shows nothing | Check the address bar has `countries=` in it. Click a preset button rather than typing |
| You are signed out unexpectedly | Sessions last two hours. Sign in again |
| Someone asks a question you cannot answer | "I will get you a written answer this week." Write it down. Nobody minds that answer; everybody minds a guess |

---

## Setting up the member state account beforehand

Only needed for step 7. On the server:

```
php artisan user:create --role=member_state_rep --country=bw
```

It prompts for a name, an address and a password. Nothing is ever seeded
with a password, which is why this step exists.
