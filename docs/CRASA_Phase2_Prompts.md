# CRASA — Claude Code work plan, phase 2

Eight tasks taking the platform from reference tables to a working trilingual site with real content models. Roughly half the build.

Paste one task at a time. Not because the prompts are fragile, but because a single mega-prompt gives Claude Code no point at which you can say "stop, that's wrong" — and with bypass permissions on, git is your only undo. Each task below ends at a natural checkpoint.

Commit between every task:

```bash
cd /home/ptechweb/crasadev && git add . && git commit -m "task N: <what it did>"
```

---

## Task 1 — Users, roles, permissions, audit

```
Read docs/DATA_MODEL.md section 7 and .design/screens/13-users-roles-audit.dc.html.

Build the user and access layer:

1. Extend the users table per section 7: country_id, authority_id, role enum
   (administrator, editor, member_state_rep, committee_member, viewer),
   two_factor_secret, two_factor_confirmed_at, preferred_locale, last_seen_at.

2. Build the audit_log table and an Auditable trait that writes on create,
   update and delete. Record user_id, action, auditable_type, auditable_id,
   changes as JSON, ip_address.

3. Build authorisation as Laravel policies, one per model, plus a Gate for
   the country scope. The rule that matters: a member_state_rep sees and
   edits only rows where country_id matches their own. Enforce this in a
   global query scope, not in controllers, so it cannot be forgotten.

4. Write feature tests proving each role can and cannot do what section 7
   and CLAUDE.md specify. Include a test that a member_state_rep gets 403
   on another country's data, and one that an editor cannot reach user
   management.

Do not build login screens yet. Models, policies, migrations and tests only.
```

---

## Task 2 — Translations and the fallback resolver in the database

```
Read docs/DATA_MODEL.md section 5 and .design/screens/05-translation-editor.dc.html.

You already built TranslationResolver against arrays in the foundation layer.
Now back it with the database.

1. Build the translations table exactly as section 5 specifies: polymorphic,
   locale, field, value, translated_by, is_machine_draft. Unique index on
   (translatable_type, translatable_id, locale, field).

2. Build a HasTranslations trait: $model->translate('title') resolves through
   the section 5 rule (requested locale, then English, then null with a log).
   It must return the ResolvedText object you already have, so the fallback
   notice component keeps working unchanged.

3. Eager-load translations to avoid N+1. A list of 50 publications must not
   fire 150 queries. Write a test asserting the query count.

4. is_machine_draft stays in the schema and stays false. It is reserved for a
   separate commercial scope. Do not build anything that sets it.

5. Tests: fallback chain, empty-string treated as missing, whitespace-only
   treated as missing, query count, and that a missing English value logs
   rather than rendering an empty heading.
```

---

## Task 3 — Regulatory instruments, versions, documents

```
Read docs/DATA_MODEL.md sections 3 and 6, and .design/screens/07-publications-library.dc.html.

This is the spine of the platform.

1. instruments, instrument_versions and documents per section 3. Instruments
   are translatable (title, summary, body). Slugs are permanent — once
   published, a slug never changes, because it is the citation permalink.

2. Versioning: editing an instrument writes a new instrument_versions row with
   a full JSON payload snapshot and bumps current_version_id. Never update
   translatable text in place. Write a test that ten edits produce ten
   versions and the original text is still retrievable.

3. Documents are polymorphic and served through a controller that checks role
   and country scope before streaming the file. Never expose a direct
   filesystem URL. Files live in storage/app/private, not public. Test that
   an unauthenticated request for a member-only document gets 403.

4. Generate a thumbnail from page 1 of each uploaded PDF. If no PDF library
   is available on this server, stub the method, log clearly, and tell me
   what to install rather than silently skipping it.

5. Model factories and a seeder producing 30 instruments across all fourteen
   countries and three sectors, every row is_sample = true, with realistic
   titles in English and a handful translated to French and Portuguese so the
   fallback path is visible in the UI.
```

---

## Task 4 — The block library

```
Read docs/DATA_MODEL.md section 8 and .design/screens/14-media-and-editing.dc.html.

1. pages and page_blocks tables per section 8. Block text is translatable
   through the translations table, not stored in the settings JSON — this
   keeps the translation editor uniform.

2. Build all twelve block types as Blade components. Each one:
   - renders correctly at 375px with no editor intervention
   - has a defined empty state that invites an action, never blank space
   - validates its settings server-side (a pillars block cannot take five
     columns)
   - uses only the tokens in CLAUDE.md

3. Card heights and column widths vary with content. A member with four
   committees and a member with one must not produce identical boxes.
   This is explicit in CLAUDE.md and it is the rule most likely to be lost.

4. No entrance animation on scroll. Motion answers a user action only.

5. Write a test per block type asserting it renders with empty settings, with
   full settings, and in all three locales.
```

---

## Task 5 — The homepage

```
Read .design/screens/02-homepage.dc.html closely and rebuild it as a real page
composed from the block library.

Match the approved screen: rotating hero, key figures, pillars, featured
comparator, featured publications, newsroom strip, social feed.

Rules:
- Every string comes from lang files, not hardcoded. All three locales.
- Images come from storage/app/public/images. Where a category has no image
  yet, render the defined empty state rather than a broken img tag or a grey
  placeholder box.
- The hero must be legible at 375px. Check the headline does not collide with
  the language switcher in French, which runs longer than English.
- Seed the homepage as a page record with its blocks, so it is editable from
  the admin rather than hardcoded in a Blade template.

When it renders, tell me and I will look at it before you continue.
```

---

## Task 6 — Search (WS-04)

```
Read docs/BUILD_SPEC.md section 4 (WS-04) and section 3.2.

Site-wide full-text search across instruments, publications, news and member
pages. Faceted by country, sector, type and year. Predictive suggestions.
Result snippets with the searched terms highlighted.

Important constraint: the specifications name ElasticSearch, but this is
shared cPanel hosting and ElasticSearch is not installed. Before building,
check what is actually available and tell me. The realistic options are a
managed ElasticSearch service over HTTP, or Laravel Scout with a MySQL
full-text driver accepting reduced faceting.

Do not pick one silently. Report what you find, recommend one, and wait.
Once I choose, build it behind a driver interface so swapping later is a
config change, not a rewrite.

There is no design screen for search — it is a contracted gap. Design the
results page from the foundations screen components: use the publications
library filter pattern for facets, and the document row component for results.
```

---

## Task 7 — Data completeness dashboard

```
Read docs/BUILD_SPEC.md section 6.1 and docs/DATA_MODEL.md section 4.

Build the indicators and indicator_values tables, then the completeness matrix.

1. indicators and indicator_values per section 4, with the unique index on
   (indicator_id, country_id, period).

2. Seed the indicator taxonomy from public sources — ITU indicator
   definitions, published SADC guidelines, NRA annual reports. Do not invent
   definitions. For each indicator record source_standard citing where the
   definition came from. If you cannot find a defensible definition for a
   category, leave it out and tell me rather than guessing. This taxonomy
   goes to fourteen regulators for confirmation, so a wrong definition is
   worse than a missing one.

3. The completeness dashboard: fourteen member states against indicator
   categories, percentage complete per country, freshness badge per
   indicator, last-updated date, gaps highlighted. Visible to Secretariat
   and to members.

4. This is pure SQL over the schema. No AI, no external service.

5. Seed sparse sample values — some countries complete, some partial, some
   empty — so the dashboard demonstrates the gap-highlighting it exists for.
   All is_sample = true.
```

---

## Task 8 — Newsroom and contact

```
Read docs/BUILD_SPEC.md section 4 (WS-06, WS-10).

Two contracted pages with no design screen. Build them from the foundations
components rather than inventing new patterns.

1. Newsroom listing: categories, tags, sector filter, pagination. Article
   page: hero image, body, embedded media, social sharing, related items.
   news_items table per DATA_MODEL section 6.

2. Contact page: enquiry form with honeypot and rate limiting (not a CAPTCHA
   — CLAUDE.md and the accessibility requirement both point away from it),
   Secretariat contact details, and a map. Use Leaflet, which is already in
   the stack, not a third-party embed that leaks visitor data to another
   company.

3. Form submissions are stored and emailed. Store first, then queue the mail,
   so a mail failure never loses an enquiry.

4. All three locales, including the validation error messages.
```

---

## The logos task — read this before running it

Claude Code can fetch files, but institutional logos are trademarks with licensing rules, and it cannot verify that a logo it found is the current official mark rather than a 2014 version on a stale page.

Two things must not be automated:

**The EU emblem.** EGEE-ICT is EU-funded, and EU visibility rules specify the emblem's exact proportions, clear space, permitted colours and the accompanying funding statement. Get it from the European Commission's own visual identity page, and get the funding statement wording from Sarah Kabahuma. A wrong EU emblem on a donor-funded platform is the kind of thing a programme officer raises formally.

**CRASA's own logo.** Request the official vector from the Secretariat via Form 1. The `crasa-logo.svg` in the design archive is someone's trace.

What Claude Code can usefully do is the fourteen national regulators — fetch, catalogue, and flag what it could not verify:

```
Fetch the logos of the fourteen CRASA member national regulatory authorities
for storage/app/public/logos/members/.

INACOM Angola, BOCRA Botswana, ANRTIC Comoros, ARPTC DR Congo, ESCCOM
Eswatini, LCA Lesotho, MACRA Malawi, ICTA Mauritius, INCM Mozambique, CRAN
Namibia, ICASA South Africa, TCRA Tanzania, ZICTA Zambia, POTRAZ Zimbabwe.

Rules:
- Only from each authority's own official website. Never from a logo
  aggregator, a news article or an image search result.
- Name by ISO code and acronym: bw-bocra.svg, za-icasa.png.
- SVG preferred; PNG at 1000px+ on the long edge otherwise.
- Record every one in logos/LICENCES.csv: filename, authority, source URL,
  date fetched, format, dimensions, and a verified column left blank.
- Where you cannot find an official source, or the only version available is
  below 200px, or the site is unreachable — do not substitute anything.
  Write the row with source_url blank and a note saying what happened.
- Mozambique needs care: INCM and ARECOM may both appear depending on the
  restructuring status. Report what you find rather than choosing.
- Do not resize, recolour or trace anything. Originals only.

Then give me a list of what you got, what you could not get, and anything you
are unsure about.
```

Then look at every single one yourself before it goes near a page. Membership of CRASA generally implies consent to be listed, but the Secretariat should confirm that in writing before go-live — it belongs in the D5 documentation.

---

## After task 8

Remaining for phase 3: submissions and the e-Form workflow, the comparator tool, the extranet and folders, the committee workspace, the admin dashboard, reports, the About/Committees/Membership pages, events, delegate registration, and the SSO bridge.

The SSO bridge is still blocked on Form 3. If the Extranet platform details and test credentials have not arrived by the end of phase 2, build the documented fallback instead so IN-01 has a deliverable path.
