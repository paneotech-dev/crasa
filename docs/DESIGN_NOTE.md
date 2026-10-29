# CRASA website: design note for Day 2

29 October 2026. For the validation workshop, Lilongwe.

## What the design is for

The site serves national regulators, ministries, the Secretariat and the public. Its job is to make regulatory information easy to find, easy to trust and easy to read on any device. Three principles follow from that.

- **User-centred.** Pages are organised around what readers come to do: find an instrument, compare member states, follow a meeting, contact the Secretariat. Every page states what it holds before the reader scrolls.
- **Findable in two clicks.** Every section is one click from the menu, and every document or figure is one click from its section. Search sits in the header on every page.
- **Inclusive.** The site is built to WCAG 2.1 level AA: it works with a keyboard, with a screen reader, at 200 percent zoom and on a phone, in English, French and Portuguese.

## The benchmark: ITU

We studied the International Telecommunication Union's site as the reference institution in our sector. From that study came a complete homepage concept, now the CRASA home page itself, built with CRASA's own content and colours. Every other page uses the same components, so the whole site reads as one family. We took the ITU's information architecture and its editorial discipline, not its look:

- a layered header: a utility bar (languages, Back Office, Members' area), then the full name of the organisation with a prominent search, then the menu;
- a featured-content area on the home page, with the photograph in a frame beside the text;
- key figures, the lines of work, news and events, publications, and a compact footer, in that order;
- white and pale surfaces, thin rules and square corners, so content carries the page rather than decoration.

## Why these colours

The site now uses the colours of CRASA's own identity: the green of Africa in the logo and the blue of its circle. They are the same family as crasa.org, whose stylesheet uses the green #0F8757. We lightened the palette and use it with much more white:

- **green #0F8757** for buttons, links and the active language, as on crasa.org;
- **deep green #0B5F3E** for headings and for the footer, the one dark surface left;
- **logo blue** for accents, with a deeper blue #2F4F8F where blue carries text;
- **white** page backgrounds, with a pale green and a pale blue to separate sections;
- **dark slate #25384A** for body text, which reads more softly than black.

There are no gradients, no dark overlays on photographs and no decorative shapes. The logo and the way "CRASA" is written in it are unchanged.

## What changed compared with crasa.org, and why

- **The structure is built for an information platform, not a brochure.** The library, the comparator, the register and the members' area each have a fixed place in the menu.
- **Search is visible on every page,** not hidden behind an icon.
- **Photographs are CRASA's own,** from the Annual General Meeting in Lilongwe, the committees and the workshops, shown in a frame with a caption.
- **Text is larger and has more contrast,** so it remains readable on a projector, on a phone in daylight and for older readers.
- **The colour scale is tested.** Every text colour has been measured against every background it sits on.

## One system for every page

**Grid and type.** A 12-column grid in a 1,200 pixel container. One type scale: 14, 16, 18, 22, 28, 36 and 48 pixels, with the serif for display headings only.

**Spacing and components.**
- 8 pixel spacing.
- Cards with 12 pixel corners and a 1 pixel border, no shadow; on hover only the colour changes, nothing lifts.
- Buttons 44 pixels high.
- Photographs always in fixed-ratio frames.

**Every page follows the same shape.**
1. A breadcrumb row.
2. A short hero with its key figures or a framed photograph.
3. Content in two columns, cards, timelines or panels, never a single narrow column of text.
4. A call-to-action band.
5. The sitemap band and the footer.

**Motion is light.** Sections fade in and figures count up once; cards do not move on hover. Nothing moves for more than 400 milliseconds, and everything is still for readers who ask their device for reduced motion or choose "Pause animations" in the display options. The full specification is UI_SYSTEM.md.

## Rules for the public pages

Added 29 October 2026 with the client's item 8f, so the site keeps reading as CRASA's own and not as a generated page. They apply to every public page in English, French and Portuguese.

- **No eyebrow labels outside the hero.** The small label above a heading appears only in a page's hero. Section headings stand alone.
- **No helper sentences unless the reader needs them to act.** A sentence under a heading stays only where it tells the reader what to do: on the comparator ("Pick an indicator and two member states") and under the map ("Smallest SADC states, shown at larger scale"). Product wording about the platform itself is not used.
- **No decorative icons.** No line icon in a pale square on cards, lists or contact panels; the number and the title carry the card. An item without a photograph shows CRASA's own photograph from crasa.org where there is one, otherwise the CRASA mark on white, never a labelled placeholder.
- **A badge only when it states a status.** Upcoming and Past on meetings, Reported and Validated in the comparator, Current and Completed on projects, Members only on a restricted meeting. No badge for a default such as Public, or for a type the section already names, such as News.
- **One primary button per section.** Other actions are secondary buttons or text links: "Download (PDF, 440 KB)", "Add to calendar".
- **Headings in CRASA's words.** "Our work", "Member regulators", "CRASA on social media", the names CRASA uses on crasa.org and in its documents. No "X, one Y" slogans. Card texts quote CRASA's own wording, at the length it has, so cards in one section are not the same length.
- **Cards are quiet.** Same radius as before, a 1 pixel border, no shadow and no lift on hover. Section bands alternate white and pale green, without rules between the figures.

## Standards followed

- **WCAG 2.1 level AA.** Body text contrast is at least 4.5 to 1, and large text and controls at least 3 to 1. For example, body text on white measures 12.05 to 1, links on white 5.37 to 1, and white on the green buttons 4.54 to 1.
- **Semantic HTML.** One heading outline per page, labelled landmarks and a skip link.
- **Language attributes on every page** in English, French and Portuguese, with one URL per language.
- **Mobile first,** tested at 390 and 768 pixels wide, including iPhone Safari.
- **Performance.** Images are served in WebP at the size the layout uses, and only the first hero photograph loads with the page.
