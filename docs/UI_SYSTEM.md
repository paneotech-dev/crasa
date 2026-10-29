# CRASA UI system

29 October 2026. One system for every public page and every back-office screen. The code is `public/css/ui.css`, where component classes are prefixed `u-`. It loads after the light theme. The home page, header, breadcrumb row, sitemap band and footer are ported from Concept 3, the ITU-inspired homepage. The other pages are built from the components below, so the whole site reads as one family.

## Tokens

| Token | Value | Use |
|---|---|---|
| Green | #0F8757 | Buttons, active states, selected chips (white text on it: 4.54) |
| Deep green | #0B5F3E | Headings, footer, utility bar, call-to-action band (white on it: 7.72) |
| Logo blue | #7A90C8 | Accents, progress bars, decorative rules |
| Blue ink | #2F4F8F | Blue text on white, eyebrows, focus ring (7.97 on white) |
| Tint green | #EAF4EF | Hero and section grounds |
| Pale | #F4F8F6 | Zebra rows, document frames |
| Tint blue | #EEF2FA | Alternating sections, table heads, panels |
| Ink | #25384A | Body text (12.05 on white) |
| Muted | #3F5264 | Secondary text (8.07 on white, 7.18 on the tints) |
| Line | #D9E3EC | Borders and dividers |
| Control border | #7B8794 | Inputs and selects (3.66 on white) |

Every text pair passes 4.5:1 on white and on both tints. There are no gradients and no overlays on photographs.

| Area | Values |
|---|---|
| Type scale | 14, 16, 18, 22, 28, 36, 48 px. The serif (Lora) is for display headings only (h1, h2, figures); the sans (IBM Plex Sans) is for everything else. Line height 1.5. Body text is 16 px or more. |
| Spacing | 4, 8, 16, 24, 32, 40, 48, 64 px. Sections are 64 px top and bottom on desktop and 40 px on phones. |
| Layout | 12-column grid, 1,200 px container, 24 px gutters (16 px on phones). |
| Shape | Cards 12 px radius, buttons and inputs 6 px, pills fully round. Cards have a 1 px line border and lift 2 px on hover and focus. |
| Buttons | 44 px high; icon on the left where it helps; primary green, secondary outlined, ghost. |
| Icons | One line set at 20 px, stroke currentColor, `aria-hidden`. |
| Motion | 150 ms colour transitions; reveal is a 12 px rise and fade in 400 ms with a 60 ms stagger. Nothing moves for more than 400 ms, and everything is off under prefers-reduced-motion. |
| Focus | 2 px #2F4F8F ring, offset 2 px, on every interactive element. |

## Page anatomy

1. **Breadcrumb and share row.**
2. **Short hero** (`u-hero`): eyebrow, title, one line, and one or two figures (`u-figures`) or a framed photo (`u-hero__photo`).
3. **Sections that never leave a single narrow column of text.** Use two columns (`u-cols-2`, `u-main-side`), cards (`u-cols-3` of `u-card`), a timeline (`u-timeline`), an accordion (`u-acc`), people grids (`u-people`) or a side panel (`u-panel`, `u-facts`). On a 1,366 px screen, no section leaves more than a third of its width empty.
4. **Call-to-action band** (`u-cta`).
5. **Sitemap band and footer.**

## Components

- **Card** (`u-card`): media in a fixed frame (`u-card__media`, 16:10; documents 4:3), label, title, one line, metadata, optional actions. The whole card is clickable through the title link, with a visible focus ring on the card.
- **Pills** (`u-pill`): a word plus an icon, so colour is never the only signal. Variants `--ok`, `--notice` and `--muted`.
- **Chips** (`u-chip`): use buttons with `aria-pressed`, or links with `aria-current`.
- **Date block** (`u-date`): day and month.
- **Tables** (`u-table` in a `u-table-frame`): for data only, with sticky headers and zebra rows. On phones they become cards or scroll inside their frame.
- **Forms** (`u-field`):
  - labels above the field, with required fields marked in words;
  - 16 px inputs;
  - helper text (`u-help`) tied to its field with `aria-describedby`;
  - inline errors (`u-error`) next to the field and announced;
  - a success state (`u-success`) that stays on the page.
- **Photos:** always in fixed-ratio frames, with `object-fit: cover` and a focal point set through `object-position`.

## Phones

Check 360, 390, 414 and 768 px:
- no horizontal scroll;
- tap targets at least 44 px;
- the hero photo above the text;
- selects stacked;
- maps full width;
- a sticky header no taller than 64 px, with search in the drawer.
