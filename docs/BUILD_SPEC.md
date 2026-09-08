# CRASA Digital Anchor Ecosystem — Consolidated Build Specification

**Working document, internal to PANEOTECH.** Not for client circulation in this form.
Prepared 8 September 2026. Revised contract end date: 3 November 2026 (pending COMESA confirmation of the no-cost extension).

---

## 0. The commercial fence

Three bodies of work now exist. Keeping them visibly separate is a delivery requirement, not an afterthought — D3 acceptance is checked line by line against Annexure B, and the USD 28,800 addendum only survives if it is clearly *not* already on the screen.

| Band | Contents | Status |
|---|---|---|
| **A — Contracted** | Everything in the TOR, Annexure B and the Technical & Functional Specifications | Deliver in full. Never withhold to create an upsell. |
| **B — Free upside** | Section 6 of this document | Build. Costs little, sells nothing away, strengthens acceptance. |
| **C — Reserved** | Conversational AI (all of it); delegate travel *management* and event operations | Do not build. Demo as proposal only, on a separate surface. |

**The line, stated once:** we deliver comprehensive *search*; we reserve conversational *answers*. We deliver a delegate *form*; we reserve travel *management*.

Two corrections required before Lilongwe:

1. **Delete AI proposal bullet 2.2 line 1** ("comprehensive search across every dataset"). That is WS-04, already contracted. One line of overlap discredits the other eleven.
2. **Rename screen 18** from *Travel logistics* to **Delegate Registration**, and remove the "New" callout from `index.html`. See section 5.6.

---

## 1. Foundations

Everything below inherits from the token set already established in `01-design-foundations`. No new tokens without a reason.

**Colour** — green `#1A8C5A` / `#0E5536` / `#E8F4EC`; navy `#0F1B2D` / `#1A2742` / `#2C3A55`; charcoal `#1F2937`; slate `#5C6B7E`; cream `#F8F6F1`; sand `#E5E0D5`; amber `#C2710C`; red `#9B2226` / `#FBEDED`.

**Type** — Lora (headings), IBM Plex Sans (body), Inter (admin surfaces), Source Serif 4 (head alternate).

**Scale** — spacing 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 / 128px. Radii 2 / 4 / 8 / 14 / pill. Three elevation levels, two easing curves.

**Status pills carry fixed meanings only.** The foundations file defines the allowed set. No screen invents a new status colour.

### 1.1 Trilingual model — decide before building

Unresolved in the specs, and it will surface on day one of the workshop because content is incomplete.

| Question | Recommendation |
|---|---|
| Can an item publish in EN only? | Yes. Requiring three languages blocks all publishing. |
| What does a PT reader see when PT is missing? | The EN text, with a discreet inline notice: *"Not yet available in Portuguese — showing English."* Never a blank page, never a hidden record. |
| Which fields are per-language? | Title, summary, body, SEO. Not: dates, numeric indicators, country, file attachments. |
| Attached PDFs? | Stay in their original language. Show a language badge on the document row. |

Fallback is a *display* rule, not a data rule. Store what exists; resolve at render.

### 1.2 Roles

Per Annexure B 1.1, extended for what the platform actually needs:

- **Administrator** — everything, 2FA mandatory
- **Editor** — content and regulation entries; no users, no settings
- **Member State Representative** — submit and track own country's data; member-only content
- **Committee Member** — adds document clearance levels within a committee workspace
- **Viewer / Public** — published content, public regulatory database, comparator

---

## 2. Content architecture — the decision that shapes everything

The client requirement is that everything be addable, editable and removable from the administration. That splits into two very different systems. Building both as one page-builder is the classic failure mode.

### 2.1 Structured records — forms and lists, not layout

These live in the database with fixed fields. Editors fill forms; the platform places the output everywhere it belongs.

Publications · Regulatory instruments · Indicator values · Member states and authorities · Representatives and contacts · Committees · Meetings and agendas · News items · Events · Users, roles, folders · Media assets

Create one publication and it appears in the library, on the member's page, in search, in the related-documents rail, in the newsroom mention list. No editor ever places it by hand.

### 2.2 Composed pages — a constrained block library

Homepage, About CRASA, mandate, structure, committees, landing and legal pages.

**Recommendation: a fixed block library, not a free-form builder.** Ten block types, each pre-styled from the design system, each already responsive and already trilingual. Editors reorder, duplicate, hide and fill. They cannot invent a layout that breaks the design you sold, and they cannot break the homepage on a Friday afternoon.

The risk register already names limited CRASA IT capacity as a live concern. Guardrails answer that risk directly and are cheaper to build than a real builder.

**Proposed block set:**

1. Hero — rotating, with image, headline, CTA
2. Key figures — animated counters
3. Pillars — 2–4 column feature grid
4. Rich text — heading, body, inline media
5. Featured publications — filtered, auto-populating
6. Featured comparison — pinned comparator view
7. Newsroom strip — latest N items
8. Events strip — upcoming N items
9. Member grid — with or without map
10. Call to action — banner with link
11. Social feed — Facebook, LinkedIn, YouTube, X
12. Document list — filtered by tag or committee

Every block: per-language content, show/hide, reorder, delete. Every block renders correctly at 375px without editor intervention.

---

## 3. Screen inventory

### 3.1 Built (16 screens + RegionMap component)

| # | Screen | Notes |
|---|---|---|
| 01 | Design foundations | Token and component reference |
| 02 | Homepage | Rotating hero, key figures, pillars, featured comparator and publications |
| 03 | Regulatory comparator | Country and indicator selection, charts, 375px table treatment |
| 04 | Committee workspace | Documents with clearance levels, meetings, agenda, workplan |
| 05 | Translation editor | Side-by-side EN/FR/PT, workflow, per-language SEO, fallback preview |
| 06 | Data submission | e-Form, four workflow states, version history, review queue |
| 07 | Publications library | Faceted filters, supersession lineage, in-page preview |
| 08 | Members | Directory, mandate, committee participation, indicators, contacts |
| 09 | Admin dashboard | Analytics, popular pages and search terms, submission activity, dark variant |
| 10 | Trilingual and architecture | Language switcher states, URL scheme, surface map |
| 11 | Extranet login | Credentials, 2FA, error states, member dashboard |
| 12 | Calendar and folders | Meetings calendar, secure country folders, folder permissions |
| 13 | Users, roles, audit | Role matrix, 2FA enrolment, notification preferences, audit log |
| 14 | Media and editing | Media library, image detail, navigation builder, page content |
| 15 | Responsive and app | Four surfaces at three widths, PWA install, offline, reduced motion |
| 18 | Delegate registration | *Renamed from "Travel logistics" — see 5.6* |

**Screens 16 and 17 do not exist in the archive.** Establish whether they were dropped or never made before assuming the set is complete.

### 3.2 Contracted but not yet designed

| Screen | Spec ref | Why it matters |
|---|---|---|
| Global search results | WS-04 | Faceted, predictive, keyword-highlighted snippets. Currently no screen at all. |
| Newsroom listing | WS-06 | Categories, tags, media |
| News article | WS-06 | Embedded video, social sharing |
| Contact page | WS-10 | Spam-protected enquiry form, Secretariat details, map |
| About CRASA | WS-03 | Composed page |
| Committees (public) | WS-03 | Composed page, links to workspaces |
| Membership (public) | WS-03 | Composed page, links to member directory |
| Public events section | WS-07 | Upcoming and past, registration links, EMP-synced |
| Report builder and output | RP-12 | Generate, view, store, print |
| SSO handoff and error states | IN-01 | Session propagation, failure paths |
| Legal pages | TOR | Privacy, terms, data protection |

### 3.3 New — free upside (section 6)

Data completeness dashboard · Bulk import wizard · Import validation report · Regulation version diff · Open data / API page · Saved searches and alerts · Comparator export · Block library editor · CMS onboarding overlay

---

## 4. Public website (WS-01 → WS-12)

| Ref | Requirement | Build note |
|---|---|---|
| WS-01 | Trilingual EN/FR/PT, CMS-managed, per-language URLs | Switcher states already designed in screen 10 |
| WS-02 | Mobile-first responsive; PWA installable, Android and iOS | Screen 15 covers the widths and the install prompt |
| WS-03 | IA: Home, About, Committees, Membership, Publications, Newsroom, Events, Regulatory Platform, Contact | Four of nine still need screens |
| WS-04 | Site-wide full-text search, faceted, predictive, highlighted snippets | **No screen exists. Highest-priority gap.** ElasticSearch backed. |
| WS-05 | Publications library, PDF/DOCX/JPG, filter by type, country, committee, year | Built |
| WS-06 | Newsroom: categories, tags, media, social sharing | No screen |
| WS-07 | Events synced to EMP, upcoming and past, registration links | No screen. Read-only feed — the *management* side stays reserved. |
| WS-08 | Social integration: Facebook, LinkedIn, YouTube, X | Block 12 |
| WS-09 | Password-protected member entry via SSO | Screen 11 |
| WS-10 | Contact with spam-protected form and map | No screen |
| WS-11 | Traffic statistics and popular search items | Screen 09 |
| WS-12 | Legacy crasa.org migrated, archived, indexed, no loss | Needs a content dump — Form 3 item still outstanding |

---

## 5. Regulatory Information-Sharing Platform (RP-01 → RP-12)

The core of the assignment and the part most exposed by missing data.

| Ref | Requirement | Status |
|---|---|---|
| RP-01 | Dashboard: updates, activity, submission status, statistics | Screen 09 |
| RP-02 | Advanced search and filter by country, date, type, keyword | Screen 07 pattern |
| RP-03 | Regulation entry form | Screen 06 |
| RP-04 | Regulation detail: metadata, version history, documents | Partial |
| RP-05 | Notifications on updates by role and country | Design needed. **Regulatory notifications only — event and travel notifications are reserved.** |
| RP-06 | Role-restricted CRUD | Screen 13 defines the matrix |
| RP-07 | Version control, author and timestamp | Screen 06; diff viewer proposed in 6.6 |
| RP-08 | CSV and JSON import/export | Import wizard proposed in 6.2 |
| RP-09 | e-Forms, server-side validation, draft/submitted/validated/returned | Screen 06 |
| RP-10 | Comparator, ≥2 countries and ≥1 indicator, Chart.js and Leaflet | Screen 03 |
| RP-11 | Harmonised taxonomy across 14 states — licensing and fees, spectrum bands, mobile termination rates, subscriber and penetration, QoS | **Blocked. See below.** |
| RP-12 | Report generation, view, store, print | No screen |

### 5.1 The taxonomy problem

RP-11 is the spine of the platform, and Form 2 came back blank from all fourteen NRAs. Nothing else in the platform is worth much without it.

**Do not wait for the questionnaires.** Define the taxonomy yourself from public sources — ITU indicator definitions, existing SADC guidelines, published NRA annual reports — and present it at the workshop as a draft for confirmation rather than a blank form. Regulators correct a draft far faster than they complete a questionnaire. This turns a blocking dependency into a workshop agenda item.

### 5.2 Sample data policy

Real data where it exists. Everywhere else, clearly-badged sample data with a single admin switch that strips every sample record before go-live.

D3 sign-off happens at the workshop. CRASA must never be in a position to approve invented regulatory figures, or to lose confidence when a delegate spots one. The badge protects both.

### 5.3 Empty states are a feature

Screens 03 and 07 already carry good ones ("Nothing selected yet", "No documents match"). Extend that discipline everywhere. With partial content, delegates will hit empty states constantly, and a well-drawn one reads as design rather than absence.

### 5.4 Submission workflow

Draft → Submitted → Validated → Returned. Returned carries comments against specific fields, as designed in screen 06. Every transition writes to the audit log with actor and timestamp.

### 5.5 Reports (RP-12)

One-click regional brief on a country or an indicator: cover, CRASA branding, data table, chart, source and date, permalink. Print stylesheet, PDF export, stored in the platform. This is the artefact that ends up in ministerial briefings.

### 5.6 Delegate registration — the renamed screen 18

What exists is a **form**, not a logistics system: delegates file arrival and departure details against a meeting, and the Secretariat sees the resulting list.

**Keep it. Reframe it.**

| Keep in base delivery | Reserve for EMP Tier 1 / 2 |
|---|---|
| Delegate self-service form (arrival, departure, flight, meeting) | Automated reminders and chase emails |
| Read-only roster list for the Secretariat | Grouped transfer scheduling and driver allocation |
| Simple CSV export of the roster | Hotel and accommodation booking |
| Link from the meeting record | Interpretation and dietary tracking |
| | Document repository with notifications |
| | Post-event feedback and reporting |

Three concrete actions:

1. Rename the screen and every label from *Travel logistics* to **Delegate Registration**.
2. Remove the "Travel logistics — New" entry from the `index.html` walkthrough intro, or restate it as *"delegate registration form; full travel management proposed separately."*
3. If the grouped transfer schedule is already drawn, reduce it to a plain sortable list. The grouping *is* the Tier 1 product.

The distinction to hold in the room: **the website collects the information; the EMP manages the operation.**

---

## 6. Free upside — build these

Non-AI, non-event, high perceived value. Ranked by impact.

### 6.1 Data completeness dashboard *(highest recommendation)*

A matrix of the 14 member states against indicator categories. Percentage complete per country, freshness badge per indicator, last-updated date, gap highlighting. Visible to the Secretariat, and — this is the point — to the members themselves.

Pure SQL over a schema you are already building. Days of work.

It also converts your worst project risk into leverage. All four collection forms came back empty. Nothing moves fourteen national regulators like a table showing that eleven have filed and three have not. Peer pressure does the work that email chasing has not.

### 6.2 Spreadsheet bulk import with validation report

Downloadable Excel template per indicator category → upload → server-side validation → error report naming row, column and reason → confirm and commit.

The TOR explicitly asked for Kobo/ODK/CommCare/SurveyCTO-class data collection capability, so this reads as fulfilment rather than generosity. And realistically NRAs will send spreadsheets whatever you build; this makes that path official instead of manual.

### 6.3 Exportable, citable comparisons

One click from the comparator to a branded PDF or PNG carrying source, date and permalink. Regulators paste these into board papers and ministerial briefs, and CRASA's logo travels with them. Cheapest driver of repeat use on this list.

### 6.4 Stable permalinks and version citation

Every instrument and every publication version gets a permanent URL and a suggested citation line. Combined with RP-07 version control, this makes CRASA the canonical reference for SADC regulatory text rather than one copy among several. Institutionally the most valuable item here.

### 6.5 Open data page

A human-facing page over the RESTful API already contracted: endpoint list, CSV and JSON downloads, licence statement, rate limits. A day of work. For an EU-funded harmonisation body, open-by-default is the posture donors reward, and it lands in COMESA's own programme reporting.

### 6.6 Regulation version diff

Side-by-side of what changed between versions, with author and date. RP-07 already generates the data; this is presentation. Regulators care about this more than almost anything else on the platform.

### 6.7 Saved searches and email digests

"Notify me when any member publishes a new spectrum instrument." Rules-based, deterministic, no AI.

**Boundary discipline:** regulatory and publication alerts are RP-05 and ours to deliver. Event, meeting and travel notifications are EMP Tier 1. Keep the two visibly separate in the interface so the line stays legible to anyone reading the addendum.

### 6.8 CMS onboarding overlay

First-run tour, contextual tooltips at the point of the task, two-minute screencasts embedded where they are needed.

The risk register flags limited CRASA IT capacity; D3 acceptance requires evidence of training. This turns a risk mitigation into a visible feature and makes the Secretariat self-sufficient once PANEOTECH is no longer in the room.

---

## 7. Integrations (IN-01 → IN-05)

| Ref | Requirement | Blocker |
|---|---|---|
| IN-01 | SAML 2.0 / OAuth 2.0 SSO bridge to Extranet and EMP | Form 3 returned blank — no platform details, no test credentials, no identity source |
| IN-02 | Extranet zero-trust: country-scoped folders, encrypted transfer | Screen 12 designed |
| IN-03 | EMP re-skin and event feed sync | Contracted. Deliver in full — it is the credibility base for the Tier 1 pitch. |
| IN-04 | M&E interoperability assessment | Assessment only where APIs exist |
| IN-05 | Database sync with CRASA systems | Joint with CRASA administrators |

**Form 3 is the critical path item.** Without Extranet platform details and an identity source, the SSO bridge cannot be designed, let alone built. Escalate through Sarah Kabahuma this week, not at the workshop. Design a documented fallback — separate login with shared credentials store — so IN-01 has a deliverable path even if access never arrives.

---

## 8. Non-functional

| Area | Requirement |
|---|---|
| Stack | Laravel 11 (PHP 8.2+), MySQL 8.0 InnoDB, Blade + Bootstrap 5.3, React for comparator and dashboards, Chart.js, Leaflet, ElasticSearch |
| Accessibility | WCAG 2.1 AA, strict. Reduced-motion variant already designed in screen 15. |
| Browsers | Current Chrome, Firefox, Edge, Safari |
| Devices | Desktop, tablet, phone; PWA installable both platforms |
| Performance | Verified on at least two SADC-region connections; thresholds in the Master Test Plan |
| Security | 2FA mandatory for admins, RBAC across CMS/platform/API, TLS 1.3, AES-256 at rest, OWASP ZAP with all Critical and High resolved |
| Backup | Hourly to S3, documented DR plan, recovery verified during D3 |
| IPv6 | Full support |
| Audit | Administrative actions, submissions and content changes, all logged |

**Separate action, outside this build:** the preview host shows signs of compromise (two identical randomly-named PHP files containing `eval`, added 2 September; ~9,400 empty nested directories under `well-known/acme-challenge/`). Production must not be provisioned from that server or that archive. Rotate all credentials including the demo login published in the Inception Report.

---

## 9. Sequencing to 3 November

Assumes the no-cost extension is confirmed. Workshop 23–25 September, Lilongwe.

**Now → 22 September — workshop-ready**

Priority is a coherent walkthrough, not completeness.

- Rename screen 18 and fix `index.html`; delete the overlapping AI bullet
- Draft the RP-11 taxonomy from public sources — take it to Lilongwe as a draft to confirm
- Global search results screen (WS-04) — the most visible contracted gap
- Newsroom listing and article; contact page
- Data completeness dashboard (6.1) — it *is* the answer to "where is the member data"
- Sample-data badging and the strip-before-go-live switch
- Block library, first pass — enough to demonstrate editability live
- Escalate Form 3 for the SSO path

**23–25 September — Lilongwe**

- Day 1–2: training and hands-on, per the agenda
- Day 3: acceptance sign-off, then — and only then — the addendum conversation
- AI and full EMP demoed on a separate surface, explicitly labelled as proposals
- Capture the taxonomy corrections live; that is the highest-value output of the week

**26 September → 20 October — build out**

- Remaining contracted screens: About, Committees, Membership, events, reports, legal
- SSO bridge or documented fallback
- Bulk import and validation (6.2); comparator export (6.3); permalinks (6.4); diff view (6.6); saved searches (6.7)
- Open data page (6.5); onboarding overlay (6.8)
- Legacy migration (WS-12), pending the content dump
- Full trilingual pass with fallback rules applied

**21 October → 3 November — harden and hand over**

- OWASP ZAP, all Critical and High closed
- Selenium regression suite
- Load testing against Master Test Plan thresholds
- DR recovery rehearsal
- D5 documentation set: user manual, maintenance guide, system design with ERD, API docs
- Source code, licences and credentials handover
- Go-live and DNS switch

---

## 10. Open items requiring a decision

1. **Screens 16 and 17** — dropped, or never built?
2. **Block library scope** — is twelve block types the right ceiling, or does CRASA need genuine layout freedom?
3. **Fallback display** — confirm the English-with-notice rule before the trilingual pass
4. **Taxonomy sign-off** — is the workshop the right forum, and who has authority to confirm on behalf of fourteen regulators?
5. **Form 3 / SSO** — hard date, or commit to the fallback now
6. **Sample data** — how much, and who signs off that it is clearly marked
7. **AI recurring inference cost** — priced into maintenance with a ceiling, or quoted separately per year? Currently absent from the addendum
8. **EMP tiers** — scope with Shukia Kiroga before confirming the two identical USD 9,000 figures
