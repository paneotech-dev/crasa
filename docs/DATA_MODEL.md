# CRASA Data Model and Block Library

Build specification for Claude Code. Read alongside CLAUDE.md.
Database: MariaDB 10.11 (InnoDB), utf8mb4_unicode_ci throughout.

---

## 1. Principles

- Every table uses `id` bigint auto-increment, `created_at`, `updated_at`.
- Anything a Member State can see or submit is scoped by `country_id`. Enforce in query scopes, not just in the UI.
- Translatable text lives in a separate `translations` table keyed by entity type and locale. Never add `title_fr`, `title_pt` columns.
- Every table holding content that could be demonstration data carries `is_sample` boolean, default false. One admin action deletes all sample rows before go-live.
- Soft deletes on content tables. Hard deletes only for sample data purging.

---

## 2. Reference tables

### countries

The fourteen CRASA member states. Seeded, not user-editable.

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| iso_code | char(2) | ao, bw, km, cd, sz, ls, mw, mu, mz, na, za, tz, zm, zw |
| name | varchar(120) | English name; translations table holds FR and PT |
| official_languages | varchar(60) | comma-separated locale codes |
| joined_at | date | nullable — seeded null throughout; accession dates are not in a source available to the build, and the Secretariat supplies them |
| flag_path | varchar(255) | nullable |
| hero_image_path | varchar(255) | nullable |

### authorities

The national regulatory authority for each country. One per country, but modelled as a separate table because a country may restructure its regulator.

| Column | Type | Notes |
|---|---|---|
| country_id | bigint FK | |
| name | varchar(200) | |
| acronym | varchar(30) | |
| website | varchar(255) | nullable |
| established_year | smallint | nullable |
| logo_path | varchar(255) | nullable |
| is_sample | boolean | |

### authority_sector (pivot)

Which sectors each authority regulates. Built 2026-09-08, replacing the
`authorities.mandate_sectors` SET column this document originally specified.

| Column | Type |
|---|---|
| authority_id | bigint FK |
| sector_id | bigint FK |

Unique index on `(authority_id, sector_id)`.

Two reasons for the change, both confirmed before build. A SET exists only in MySQL and MariaDB; Laravel's SQLite grammar has no `typeSet`, so the column cannot be created in the test database, and the hosting account has no `CREATE DATABASE` grant for a MariaDB test schema — a SET would leave the authorities table untestable. And a SET enforces nothing against `sectors`, while the comparator and completeness dashboard both need to ask which regulators hold a postal or broadcasting mandate. That is an indexed join here and a string scan with a SET.

### sectors

Fixed set of three: telecommunications, broadcasting, postal. Seeded.

| Column | Type |
|---|---|
| slug | varchar(40) |
| name | varchar(80) |
| icon | varchar(60) |
| accent_token | varchar(40) |

### committees

| Column | Type | Notes |
|---|---|---|
| slug | varchar(60) | |
| name | varchar(200) | |
| sector_id | bigint FK | nullable — some committees are cross-sector |
| mandate | text | |
| chair_authority_id | bigint FK | nullable |
| is_active | boolean | |
| is_sample | boolean | added 2026-09-08, see note below |

`is_sample` is not in this table's original column list. It was added because section 1 requires the flag on every table holding content that could be demonstration data, and seeded committee mandates, chairs and membership are exactly that. Without it the go-live purge cannot reach them.

### committee_country (pivot)

Which member states participate in which committee.

| Column | Type | Notes |
|---|---|---|
| committee_id | bigint FK | |
| country_id | bigint FK | |
| role | enum: member, chair, observer | |
| is_sample | boolean | added 2026-09-08, same reason as committees |

---

## 3. Regulatory core

This is the spine. Everything in the comparator, the e-Forms and the completeness dashboard depends on it.

### instruments

A regulatory instrument: an act, regulation, licence framework, guideline or directive.

| Column | Type | Notes |
|---|---|---|
| country_id | bigint FK | |
| sector_id | bigint FK | |
| instrument_type | enum | act, regulation, licence_framework, guideline, directive, policy |
| reference_number | varchar(80) | nullable — official citation |
| enacted_on | date | nullable |
| effective_from | date | nullable |
| status | enum | in_force, amended, repealed, draft, proposed |
| superseded_by_id | bigint FK self | nullable |
| current_version_id | bigint FK | points at instrument_versions |
| slug | varchar(200) | unique, stable — this is the permalink |
| is_sample | boolean | |

Title, summary and body are translatable — see section 5.

### instrument_versions

Every edit creates a version. Never update an instrument's text in place.

| Column | Type | Notes |
|---|---|---|
| instrument_id | bigint FK | |
| version_number | smallint | increments per instrument |
| changed_by | bigint FK users | |
| change_summary | varchar(500) | what changed and why |
| payload | json | full snapshot of translatable fields at this version |
| created_at | timestamp | |

The diff view (section 6.6 of the build spec) reads two payloads and renders the difference.

### documents

Files attached to instruments, publications or committees.

| Column | Type | Notes |
|---|---|---|
| documentable_type | varchar | polymorphic |
| documentable_id | bigint | |
| file_path | varchar(255) | stored outside the web root, served through a controller |
| original_filename | varchar(255) | |
| mime_type | varchar(100) | pdf, docx, jpg |
| size_bytes | bigint | |
| locale | char(5) | the language the document itself is in |
| thumbnail_path | varchar(255) | generated from page 1 for PDFs |
| is_public | boolean | false means member-only |
| is_sample | boolean | |

**Never serve documents by direct filesystem URL.** Route through a controller that checks role and country scope. This is what makes the Extranet zero-trust requirement real.

---

## 4. Indicators and submissions

### indicators

The harmonised taxonomy — RP-11. Definitions, not values.

| Column | Type | Notes |
|---|---|---|
| category | enum | licensing, spectrum, interconnection, market, quality, universal_access |
| slug | varchar(80) | unique |
| name | varchar(200) | |
| definition | text | precise enough that fourteen regulators report the same thing |
| unit | varchar(40) | e.g. USD, MHz, percent, subscribers_per_100 |
| data_type | enum | numeric, currency, percentage, text, boolean, date |
| periodicity | enum | annual, biannual, quarterly, ad_hoc |
| sector_id | bigint FK | nullable if cross-sector |
| source_standard | varchar(120) | e.g. ITU, SADC guideline reference |
| display_order | smallint | |
| is_active | boolean | |

Seed this from public sources before the workshop. See the build spec, section 5.1 — do not wait for the questionnaires.

### indicator_values

| Column | Type | Notes |
|---|---|---|
| indicator_id | bigint FK | |
| country_id | bigint FK | |
| period | varchar(20) | e.g. 2025, 2025-Q3 |
| value_numeric | decimal(20,4) | nullable |
| value_text | text | nullable |
| currency | char(3) | nullable, for currency types |
| source_note | varchar(500) | where the figure came from |
| submission_id | bigint FK | nullable — null means entered directly by Secretariat |
| confidence | enum | reported, estimated, provisional |
| is_sample | boolean | |

Unique index on `(indicator_id, country_id, period)`.

### submissions

The e-Form workflow — RP-09.

| Column | Type | Notes |
|---|---|---|
| country_id | bigint FK | |
| submitted_by | bigint FK users | |
| period | varchar(20) | |
| status | enum | draft, submitted, validated, returned |
| reviewed_by | bigint FK users | nullable |
| reviewed_at | timestamp | nullable |
| review_note | text | nullable — required when status is returned |

### submission_field_comments

Returned submissions carry comments against specific fields, per screen 06.

| Column | Type |
|---|---|
| submission_id | bigint FK |
| indicator_id | bigint FK |
| comment | text |
| resolved | boolean |

### submission_events

Audit trail. Every transition writes a row. Never delete.

| Column | Type |
|---|---|
| submission_id | bigint FK |
| from_status | varchar(20) |
| to_status | varchar(20) |
| actor_id | bigint FK users |
| note | varchar(500) |
| created_at | timestamp |

---

## 5. Translations

One table, polymorphic, three locales.

### translations

| Column | Type | Notes |
|---|---|---|
| translatable_type | varchar | e.g. App\Models\Instrument |
| translatable_id | bigint | |
| locale | char(2) | en, fr, pt |
| field | varchar(60) | title, summary, body, meta_title, meta_description |
| value | longtext | |
| translated_by | bigint FK users | nullable |
| is_machine_draft | boolean | reserved — leave false, AI translation is out of scope |

Unique index on `(translatable_type, translatable_id, locale, field)`.

**Resolution rule:** request locale → fall back to English → if English is also missing, hide the field and log. Never render an empty heading.

When a record is displayed in a fallback language, the view must show the inline notice defined in CLAUDE.md. That notice is itself translatable.

---

## 6. Content

### publications

| Column | Type | Notes |
|---|---|---|
| publication_type | enum | annual_report, guideline, study, position_paper, communique, minutes |
| sector_id | bigint FK | nullable |
| committee_id | bigint FK | nullable |
| country_id | bigint FK | nullable — null means regional |
| published_on | date | |
| slug | varchar(200) | unique, permanent |
| supersedes_id | bigint FK self | nullable |
| is_public | boolean | |
| is_sample | boolean | |

### news_items

| Column | Type |
|---|---|
| slug | varchar(200) |
| published_at | timestamp |
| sector_id | bigint FK nullable |
| hero_image_path | varchar(255) |
| author_name | varchar(120) |
| status | enum: draft, review, published |
| is_sample | boolean |

### events

Read-only mirror of the Event Management Platform — WS-07. The website displays; it does not manage.

| Column | Type | Notes |
|---|---|---|
| external_id | varchar(80) | the EMP's identifier |
| title | varchar(255) | |
| starts_at, ends_at | timestamp | |
| location | varchar(200) | |
| registration_url | varchar(255) | links out to the EMP |
| synced_at | timestamp | |

**Do not build event creation or management here.** That is reserved scope.

### delegate_registrations

Screen 18, renamed. A form, not a logistics system.

| Column | Type | Notes |
|---|---|---|
| event_id | bigint FK | |
| country_id | bigint FK | |
| user_id | bigint FK | nullable |
| full_name | varchar(160) | |
| email | varchar(160) | |
| arrival_at | timestamp | nullable |
| departure_at | timestamp | nullable |
| arrival_flight | varchar(60) | nullable |
| departure_flight | varchar(60) | nullable |
| notes | text | nullable |

Secretariat gets a sortable list and a CSV export. **No reminder scheduling, no transfer grouping, no accommodation fields.** Those are reserved.

---

## 7. Users, roles, access

### users

Standard Laravel table plus:

| Column | Type | Notes |
|---|---|---|
| country_id | bigint FK | nullable — null for Secretariat and admins |
| authority_id | bigint FK | nullable |
| role | enum | administrator, editor, member_state_rep, committee_member, viewer |
| two_factor_secret | text | nullable, encrypted |
| two_factor_confirmed_at | timestamp | nullable |
| preferred_locale | char(2) | default en |
| last_seen_at | timestamp | |

2FA is mandatory for `administrator`. Enforce at login, not just in settings.

### audit_log

| Column | Type |
|---|---|
| user_id | bigint FK nullable |
| action | varchar(80) |
| auditable_type | varchar |
| auditable_id | bigint |
| changes | json |
| ip_address | varchar(45) |
| created_at | timestamp |

Write on: content create/update/delete, submission transitions, role changes, login and failed login, document downloads of non-public files.

### folders and folder_permissions

Extranet secure storage — screen 12, IN-02.

| Column | Type | Notes |
|---|---|---|
| parent_id | bigint FK self | nullable |
| name | varchar(160) | |
| country_id | bigint FK | nullable — non-null means country-scoped |
| committee_id | bigint FK | nullable |
| clearance | enum | public, member, committee, restricted |

Access check is server-side on every request. A Member State user reaches only their own country's folders. Hiding the UI is not access control.

---

## 8. Block library

Composed pages — homepage, About, Committees, Membership, landing and legal pages.

### pages

| Column | Type |
|---|---|
| slug | varchar(200) unique |
| template | enum: standard, landing, legal |
| status | enum: draft, review, published |
| published_at | timestamp nullable |

### page_blocks

| Column | Type | Notes |
|---|---|---|
| page_id | bigint FK | |
| block_type | varchar(40) | from the fixed list below |
| position | smallint | ordering |
| is_visible | boolean | |
| settings | json | block-specific configuration |

Translatable text within a block goes in the `translations` table against the block, not into the JSON. Keeps the translation editor working uniformly.

### The twelve block types

Fixed set. Adding a thirteenth requires a decision, not a commit.

| Type | Settings | Notes |
|---|---|---|
| `hero` | image, headline, subhead, cta_label, cta_url, rotation_items | Homepage hero, per screen 02 |
| `key_figures` | items[] of {value, label, source} | Counters. No animation on scroll. |
| `pillars` | columns (2–4), items[] of {icon, heading, body, link} | Card heights vary with content |
| `rich_text` | body, alignment | Standard prose block |
| `featured_publications` | filter{sector, committee, country, type}, limit | Auto-populates from publications |
| `featured_comparison` | country_ids[], indicator_id, chart_type | Pinned comparator view |
| `newsroom_strip` | limit, sector filter | Latest N items |
| `events_strip` | limit, upcoming_only | Reads the synced events mirror |
| `member_grid` | show_map (bool), filter | Uses the existing RegionMap geometry |
| `call_to_action` | headline, body, cta_label, cta_url, tone | Tone from the defined palette only |
| `social_feed` | networks[] | Facebook, LinkedIn, YouTube, X |
| `document_list` | filter{tag, committee, type}, limit | Filtered publication list |

**Constraints that must hold:**

- Every block renders correctly at 375px with no editor intervention.
- Every block has a defined empty state. `featured_publications` with no matches shows the invitation, never a blank space.
- Editors can reorder, duplicate, hide and delete. They cannot create custom layouts or set arbitrary colours.
- Block settings validate server-side. A `pillars` block cannot be given five columns.

---

## 9. Build order

1. Reference tables and seeders — countries, sectors, authorities, committees
2. Users, roles, audit log
3. Translations table and the resolution helper
4. Instruments, versions, documents
5. Indicators and indicator values, seeded from public sources
6. Submissions and the workflow with its audit events
7. Pages and page_blocks, then the twelve block renderers
8. Publications, news, events mirror
9. Folders and permissions
10. Delegate registrations

Each step: migration, model with relationships, factory, seeder with `is_sample` set true, feature test for anything touching roles or workflow state. Commit between steps.

---

## 10. Open questions before building

1. ~~**MariaDB vs MySQL**~~ — **resolved 2026-09-08. MariaDB 10.11 stays.** CLAUDE.md and this document both name it. All tables are InnoDB, utf8mb4_unicode_ci.
2. **Indicator taxonomy** — the list in section 4 needs its actual contents drafted from ITU and SADC sources before the workshop.
3. **Period format** — annual only, or quarterly for some indicators? Affects the unique index and the completeness dashboard.
4. **Committee document clearance** — is `restricted` per-user or per-committee-role?
5. **Instrument body** — full text stored in the platform, or metadata plus an attached PDF? Affects search indexing substantially.
