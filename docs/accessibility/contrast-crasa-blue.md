# CRASA blue: contrast pairs

The CRASA blue is the navy of the crasa.org buttons (Learn More, Extranet, Contact). It was sampled on 29 October 2026 from the site's stylesheet (`crasa.shared.cc66dcb2a.css`): `.button { background-color: #052d53 }`, the same value as that sheet's `--primary-1`. The brief gave about `#0F2A5C`; the sampled value is used.

Token: `--c-crasa-blue: #052D53` in `public/css/tokens.css`, the one place the hex is written. Aliases: `--u-crasa-blue` (ui.css), `--k-crasa-blue` (k.css), `--a-blue` (admin-tokens.css). Rules use the aliases, never the hex.

## Where it is used

| Surface | Element | Rule |
|---|---|---|
| Member area, back office | Primary buttons (`a-btn--primary`, and `u-btn--primary`, `c-btn--primary` inside the shell) | `--a-btn: var(--a-blue)`, hover `--c-navy` |
| Member area, back office | Current sidebar item: text only, no box; hover keeps the 1px underline | `--a-side-current-text: var(--a-blue)` |
| Member area, back office | Second chart series (dashboard bars and tracks), event date chips | `--a-series-2`, `--a-date-ink` |
| Public | Sitemap band and footer | `.k-smap`, `.k-foot` on `--k-crasa-blue` |
| Public | Closing call-to-action band and the call-to-action block in its dark tone | `.u-cta`, `.b-cta__panel[data-tone="navy"]` |
| Public and signed in | Event date blocks | `.u-date` on `--u-crasa-blue`, white text |
| Public | Comparator chart, the highlighted bar | `.c-cmp-chart__bar--second` |

Green stays the public identity: public primary buttons, links, the menu and mega menu, the hero and badges are unchanged. The back office keeps its count badges green (`--a-badge`).

## Measured pairs (WCAG 2.1 relative luminance)

| Pair | Foreground | Background | Ratio | Needed | Result |
|---|---|---|---|---|---|
| Button text, date block text, band headings | #FFFFFF | #052D53 | 13.94 | 4.5 | Pass |
| Button hover text | #FFFFFF | #0F1B2D (`--c-navy`, shell) | 17.28 | 4.5 | Pass |
| Current sidebar item | #052D53 | #FFFFFF | 13.94 | 4.5 | Pass |
| Blue text on back office ground | #052D53 | #F4F8F6 | 13.01 | 4.5 | Pass |
| Blue text on green tint | #052D53 | #EAF4EF | 12.40 | 4.5 | Pass |
| Blue text on blue tint | #052D53 | #EEF2FA | 12.42 | 4.5 | Pass |
| Footer and sitemap text | #E3EEE8 (`--k-on-deep`) | #052D53 | 11.72 | 4.5 | Pass |
| Footer card label | #D4DDF2 (`--k-blue-soft`) | #052D53 | 10.24 | 4.5 | Pass |
| Closing band text | #E7EFEA | #052D53 | 11.90 | 4.5 | Pass |
| Closing band secondary button edge | #B9D9C8 | #052D53 | 9.17 | 3.0 | Pass |
| Focus ring on the blue (white) | #FFFFFF | #052D53 | 13.94 | 3.0 | Pass |
| Green button edge on the blue panel | #0F8757 | #052D53 | 3.07 | 3.0 | Pass |
| Green series against blue series | #0F8757 | #052D53 | 3.07 | 3.0 | Pass |
| Blue bar on white | #052D53 | #FFFFFF | 13.94 | 3.0 | Pass |
| Blue bar on the sand track | #052D53 | #E5E0D5 | 10.59 | 3.0 | Pass |
| Green series on white | #0F8757 | #FFFFFF | 4.54 | 3.0 | Pass |
| Green series on the sand track | #0F8757 | #E5E0D5 | 3.45 | 3.0 | Pass |

## Pairs that fail, and are therefore not used

| Pair | Ratio | Decision |
|---|---|---|
| Focus ring #2F4F8F on the blue | 1.75 | Inside the blue bands the focus ring is white (`--focus-ring`, `--u-focus`). |
| Blue on the dark theme ground #0B1220 | 1.34 | The dark set keeps its green button (`--d-green`, dark ink), cream current item and green-step chart series. |
| Blue against the old dark green #0E5536 | 1.57 | The first chart series moved to the green #0F8757 (3.07 against the blue). |
| Blue against deep green #0B5F3E | 1.81 | Not placed next to each other. |

Tests: `tests/Feature/ContrastTest.php` (token pairs from tokens.css) and `tests/Feature/CrasaBlueTest.php` (the light-surface values, the single definition and the rules).
