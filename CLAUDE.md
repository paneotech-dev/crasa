# CRASA Digital Anchor Ecosystem

Place this file at the repository root. Claude Code reads it automatically at the start of every session.

---

## What this is

The corporate website, Regulatory Information-Sharing Platform and administration back office for the Communications Regulators' Association of Southern Africa (CRASA), a specialised SADC agency harmonising telecommunications, broadcasting and postal regulation across fourteen member states.

Audience: national regulators, Secretariat staff, ministry officials, and the public. Not consumers, not a SaaS product. The tone is institutional, calm and precise. This platform will be cited in ministerial briefs.

Contract: ZM-COMESA-CRASA/WEBSITE/25/07/2025-as. Client: COMESA on behalf of CRASA. Delivery date 3 November 2026.

---

## Stack

- Laravel 13.30.1, PHP 8.3+
- MariaDB 10.11 (InnoDB), utf8mb4_unicode_ci, normalised schema, referential integrity enforced
- Blade templates + Bootstrap 5.3, mobile-first. Only Bootstrap's grid-only build is served (`public/css/vendor/bootstrap-grid.min.css`), bound to the tokens by `public/css/grid.css`. It carries no Reboot, no components, no colour and no type. Bootstrap's own `.m-*` and `.p-*` utilities are not part of this system; spacing comes from the `--s-*` tokens.
- React 18 for the Comparator Tool and analytics dashboards only — not for the whole site
- Chart.js for graphs, Leaflet for maps
- ElasticSearch for full-text search
- Laravel localisation for EN / FR / PT

Do not introduce Tailwind, a new component library, or a second CSS methodology. Bootstrap 5.3 with the custom token layer is the system.

---

## Design tokens — the single source of truth

These come from the approved design foundations. Do not invent values. If a value you need is not here, ask before adding it.

```css
:root {
  --c-green:#1A8C5A; --c-green-dark:#0E5536; --c-green-light:#E8F4EC;
  --c-navy:#0F1B2D; --c-navy-mid:#1A2742; --c-navy-line:#2C3A55;
  --c-charcoal:#1F2937; --c-slate:#5C6B7E;
  --c-cream:#F8F6F1; --c-sand:#E5E0D5;
  --c-amber:#C2710C; --c-white:#FFFFFF;
  --c-red:#9B2226; --c-red-light:#FBEDED;

  --c-border:#C9C2B2; --c-border-input:#B9B2A2; --c-disabled:#6E7A85;
  --c-navy-deep:#050D18; --c-navy-edge:#4A5670; --c-navy-soft:#6B7691;

  --c-amber-light:#FDF0DC; --c-amber-mid:#E5BE87; --c-amber-dark:#5C3404;
  --c-green-mid:#B6DCC5;
  --c-red-mid:#E3B7B8; --c-red-dark:#6E1A1D;

  --f-head:'Lora',Georgia,'Times New Roman',serif;
  --f-body:'IBM Plex Sans',system-ui,sans-serif;
  --f-admin:'Inter',system-ui,sans-serif;

  --s-1:4px; --s-2:8px; --s-3:12px; --s-4:16px; --s-5:24px;
  --s-6:32px; --s-7:48px; --s-8:64px; --s-9:96px; --s-10:128px;

  --r-xs:2px; --r-sm:4px; --r-md:8px; --r-lg:14px; --r-pill:999px;

  --e-1:0 1px 2px rgba(15,27,45,.06), 0 1px 3px rgba(15,27,45,.08);
  --e-2:0 2px 4px rgba(15,27,45,.06), 0 6px 16px rgba(15,27,45,.08);
  --e-3:0 12px 32px rgba(15,27,45,.16);

  --ease-in:cubic-bezier(.2,.8,.2,1);
  --ease-out:cubic-bezier(.4,0,1,1);
}
```

**Typeface roles are fixed.** Lora for headings on public surfaces. IBM Plex Sans for body. Inter for administration and dense data tables only — its neutrality is the point there. There is no heading alternate. Three families, three roles.

**Radii carry meaning.** `--r-xs` on inputs and table cells, `--r-sm` on buttons, `--r-md` on panels, `--r-lg` on cards and modals, `--r-pill` on status badges only. Applying one radius everywhere flattens the hierarchy and is one of the clearest signs of a generated interface.

**Status pills carry fixed meanings.** Green for validated and in force. Amber for draft, pending and returned. Red for repealed, rejected and errors. Slate for archived and superseded. No screen invents a new status colour.

---

## Not-generic rules

The reference screens were designed deliberately. Code written against them must not drift toward statistical defaults. Concretely:

**Never use these.** They are the recognised fingerprints of generated UI:

- Indigo, violet or purple anywhere. `#615fff`, `#8e51ff`, `#0f172b` and any indigo-to-violet gradient are forbidden. The palette above is the palette.
- A single border-radius applied uniformly to every element.
- The same soft grey shadow under every card. Use the three defined elevations to encode hierarchy.
- Emoji standing in for icons. Use Lucide, one library, consistently. Emoji do not inherit colour, break across operating systems and do not scale.
- A thin coloured gradient bar down the left edge of a card.
- Tracked-out ALL-CAPS eyebrow labels above headings. The one exception is the footer column headings, which are set in tracked caps as drawn in the approved screens — see the carve-out under the writing rules.
- Meta strings joined with middle dots (`A · B · C`).
- An arrow character appended to button or link text.
- Numbered markers `01 / 02 / 03` unless the content genuinely is a sequence. The submission workflow is a sequence. A feature grid is not.
- Fade-and-slide-up entrance animation on every section, hover lift on every card. Motion answers a user action; it does not decorate scrolling.
- Uniform `py-24`-style padding on every section. Whitespace should reflect content density, not the difficulty of making a layout responsive.
- Three identical cards with identical two-sentence descriptions of identical length. Content varies naturally; the layout must absorb that.

**Do this instead:**

- Vary card heights and column widths with the content. A member with four committees and a member with one should not produce identical boxes.
- Spend visual boldness in one place per screen and keep everything else quiet.
- Use borders, rules and spacing to encode real structure — sector, status, hierarchy — not to decorate.
- Respect `prefers-reduced-motion`. A variant is already designed in screen 15.

---

## Writing rules

The copy is where generated pages give themselves away fastest, and it is the part most likely to be read by a regulator deciding whether to trust the data.

- **Sentence case everywhere.** No title case headings, no ALL CAPS labels.
- **One carve-out for caps: footer column headings.** The four link-group headings in the site footer are set in tracked caps, 12px, weight 700, `letter-spacing: .1em`, as drawn in screen 02. Nothing else on any surface takes this treatment — not section eyebrows, not table headers, not card labels, not navigation. If you find yourself reaching for tracked caps anywhere but the footer, the answer is sentence case.
- **Straight quotes in code and content fields.** Curly quotes pasted from a generator are a tell.
- **Em dashes: at most one per page of copy.** Use full stops. Short declarative sentences are the house voice.
- **Active voice, and buttons say what happens.** "Submit data", not "Submit". The button that says "Publish" produces a message that says "Published".
- **Never write marketing adjectives.** No seamless, innovative, robust, cutting-edge, leverage, empower, unlock, transform. This is a regulatory platform.
- **Errors state what went wrong and what to do.** They do not apologise and they are never vague. "Row 14: mobile termination rate must be a number in local currency. Found 'n/a'."
- **Empty states invite an action.** The existing screens do this well ("Nothing selected yet", "No documents match"). Match that voice.

**The substitution test.** Take any paragraph and replace "CRASA" with "EACO" or "ARICEA" — the two comparable regional associations. If it still reads perfectly, the copy is too generic. Rewrite it with something only true of CRASA: fourteen member states, SADC, the 1997 protocol, the three sectors, the harmonisation mandate.

Existing copy that gets the voice right, from the preview: *"Every download comes from the platform itself. No redirect to Google Drive or SharePoint anywhere."* Specific, declarative, no adjectives. Write like that.

---

## Multilingual rules

Three languages: English (default), French, Portuguese.

- Per-language fields: title, summary, body, SEO metadata, menu labels.
- Not per-language: dates, numeric indicators, country, ISO codes, file attachments.
- An item may publish in English only. Requiring all three blocks all publishing.
- When a translation is missing, render the English with an inline notice — never a blank page, never a hidden record.
- Attached PDFs stay in their original language; show a language badge on the document row.
- **Design for text expansion.** French and Portuguese run 15–30% longer than English. Never fix a button or nav width to English text. The foundations screen has proven layouts in all three; use them.

---

## Data and domain rules

- Fourteen member states: Angola, Botswana, Comoros, DR Congo, Eswatini, Lesotho, Malawi, Mauritius, Mozambique, Namibia, South Africa, Tanzania, Zambia, Zimbabwe.
- Three sectors: telecommunications, broadcasting, postal.
- Regulation entries are versioned. Every change records author and timestamp. Never update in place without writing history.
- Submission workflow states: draft → submitted → validated → returned. Every transition writes to the audit log.
- Sample and demonstration data must be flagged with a boolean and strippable in one admin action before go-live. Never seed unmarked fake regulatory figures.

---

## Security requirements

- 2FA mandatory for administrator accounts.
- Role-based access enforced server-side on every route. Never rely on hiding UI elements.
- TLS 1.3 in transit, AES-256 for sensitive fields at rest.
- Uploads directory must not execute PHP. Enforce in `.htaccess`.
- Never commit `.env`, credentials, keys or database dumps.
- All Critical and High OWASP ZAP findings resolved before go-live.
- Extranet access is country-scoped: a member state user reaches only their own authorised folders.

---

## Accessibility

WCAG 2.1 Level AA, strictly. Not aspirational — it is a contractual acceptance criterion.

- Visible keyboard focus on every interactive element.
- Contrast checked against the actual token pairs, not assumed.
- Every form field has a real label, not a placeholder standing in for one.
- Charts have a table equivalent. The comparator must be usable without seeing the graph.
- `prefers-reduced-motion` honoured.

---

## Working conventions

- Branch per feature, conventional commit messages.
- Migrations for every schema change. Never edit a shipped migration.
- Seeders produce realistic Southern African data, always flagged as sample.
- Tests for anything touching submission workflow, role permissions or version history.
- Run `php artisan test` before proposing a change is complete.

---

## Ask before doing

- Adding a dependency
- Adding a design token or a colour
- Changing the submission workflow states
- Anything touching the SSO bridge (integration details still outstanding from CRASA)
- Anything that would build conversational AI features or event and travel management — both are reserved for a separate commercial addendum and must not appear in this codebase
