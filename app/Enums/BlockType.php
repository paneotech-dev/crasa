<?php

namespace App\Enums;

use App\Support\BlockLink;

/**
 * The twelve block types from DATA_MODEL section 8. Fixed set. Adding a
 * thirteenth requires a decision, not a commit.
 *
 * Translatable text is not in settings. It goes in the translations table
 * against the block, so the translation editor works uniformly across
 * instruments, publications and page copy alike.
 */
enum BlockType: string
{
    case Hero = 'hero';
    case KeyFigures = 'key_figures';
    case Pillars = 'pillars';
    case RichText = 'rich_text';
    case FeaturedPublications = 'featured_publications';
    case FeaturedComparison = 'featured_comparison';
    case NewsroomStrip = 'newsroom_strip';
    case EventsStrip = 'events_strip';
    case MemberGrid = 'member_grid';
    /*
     * The thirteenth, added 2026-09-09 on a ruling.
     *
     * DATA_MODEL section 8 fixed the set at twelve and said a thirteenth
     * needed a decision rather than a commit. The decision was taken: the
     * constraint was against inventing layouts, not against completing the
     * set, and the six committees existed as records that no public page
     * could show.
     *
     * No screen draws a public committee list. Screen 04 draws a single
     * committee workspace behind authentication, and its breadcrumb —
     * "Committees / Spectrum and Frequency Management" — implies an index
     * it does not draw. So this is derived from member_grid, which is the
     * platform's own pattern for a set of institutional records, and
     * logged as a disclosed variation in docs/OPEN_ITEMS.md.
     */
    case CommitteeGrid = 'committee_grid';
    case CallToAction = 'call_to_action';
    case SocialFeed = 'social_feed';
    case DocumentList = 'document_list';
    /*
     * The fourteenth, added 2026-09-09 on an instruction.
     *
     * Screen 02 draws it at line 376, between the publications and the
     * newsroom: a navy band carrying one statement and a grid of figures
     * beside it. It had never been built, and its absence was why the
     * publications and the newsroom both landed on cream and fused. That
     * was patched by giving the newsroom white; this removes the patch and
     * builds the section.
     */
    case StatementBand = 'statement_band';

    /**
     * The ground this section sits on, taken from screen 02.
     *
     * Screen 02 does not rotate grounds on a cycle. It assigns each one to
     * what the section is, and the run of navy is the point: the hero at
     * line 93 is navy, the key figures immediately under it at 186 are
     * navy-mid, the featured comparison at 272 is navy again. Between them
     * the pillars and the publications sit on the page ground, which the
     * screen sets to cream at line 27. The social feed at 535 is white
     * between two sand rules and the members at 604 are sand.
     *
     * A positional cycle produced none of that. It put the comparison on
     * sand and the members on white, which is how a page with a navy hero
     * ends up reading as one undifferentiated cream column below the fold.
     *
     * Null means the section paints its own ground.
     */
    public function ground(): ?string
    {
        return match ($this) {
            // "background:var(--c-navy);color:var(--c-cream)" — screen 02 line 93.
            self::Hero => null,
            // "background:var(--c-navy-mid);color:var(--c-cream);border-top:1px solid #2C3A55" — line 186.
            // 29 October 2026, light theme: the figures band and the
            // comparison sit on light grounds; no dark band below the hero.
            self::KeyFigures => 'white',
            // "background:var(--c-navy);color:var(--c-cream);padding:var(--s-9) 0" — line 272.
            self::FeaturedComparison => 'cream',
            // "background:var(--c-white);border-top:1px solid var(--c-sand);border-bottom:1px solid var(--c-sand)" — line 535.
            self::SocialFeed => 'white',
            // "background:var(--c-sand);padding:var(--s-9) 0" — line 604.
            self::MemberGrid => 'sand',
            // The page ground, "background:var(--c-cream)" — screen 02 line 27.
            self::Pillars, self::FeaturedPublications => 'cream',
            // Screen 02 draws the newsroom and the events in one two-column
            // grid on the page ground at line 399, reached after the navy
            // statement band at 376. The band exists now, so the newsroom
            // takes the cream the screen gives it and the white it was
            // standing in with is no longer needed.
            self::NewsroomStrip => 'cream',
            self::EventsStrip => 'white',
            // Not drawn on screen 02. The committee grid is the disclosed
            // variation on member_grid, so it takes the members' ground.
            self::CommitteeGrid => 'sand',
            self::RichText, self::DocumentList => 'cream',
            // "background:var(--c-navy)" -- screen 02 line 376.
            self::StatementBand => 'navy',
            self::CallToAction => 'navy',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::KeyFigures => 'Key figures',
            self::Pillars => 'Pillars',
            self::RichText => 'Rich text',
            self::FeaturedPublications => 'Featured publications',
            self::FeaturedComparison => 'Featured comparison',
            self::NewsroomStrip => 'Newsroom strip',
            self::EventsStrip => 'Events strip',
            self::MemberGrid => 'Member grid',
            self::CommitteeGrid => 'Committee grid',
            self::CallToAction => 'Call to action',
            self::StatementBand => 'Statement band',
            self::SocialFeed => 'Social feed',
            self::DocumentList => 'Document list',
        };
    }

    public function view(): string
    {
        return 'blocks.'.str_replace('_', '-', $this->value);
    }

    /**
     * Translatable fields for this block type. These live in the
     * translations table, never in settings.
     *
     * @return list<string>
     */
    public function translatableFields(): array
    {
        return match ($this) {
            self::Hero => ['eyebrow', 'headline', 'subhead', 'cta_label'],
            self::KeyFigures => ['heading'],
            self::Pillars => ['heading', 'intro'],
            self::RichText => ['heading', 'body'],
            self::FeaturedPublications => ['heading', 'intro'],
            self::FeaturedComparison => ['heading', 'intro'],
            self::NewsroomStrip => ['heading'],
            self::EventsStrip => ['heading'],
            self::MemberGrid, self::CommitteeGrid => ['heading', 'intro'],
            self::CallToAction => ['headline', 'body', 'cta_label'],
            // The statement, the label over its figures, the note under
            // them, and the link out. Screen 02 line 376.
            self::StatementBand => ['eyebrow', 'statement', 'cta_label', 'grid_label', 'grid_note'],
            self::SocialFeed => ['heading'],
            self::DocumentList => ['heading'],
        };
    }

    /**
     * The field whose absence means this block has nothing to show.
     *
     * Not the same as the first translatable field. A hero with an eyebrow
     * and no headline is an empty hero, and the renderer treats it that
     * way; the eyebrow sits first in the editor because that is where it
     * sits on the page.
     */
    public function primaryField(): ?string
    {
        return match ($this) {
            self::Hero, self::CallToAction => 'headline',
            self::StatementBand => 'statement',
            self::RichText => 'body',
            default => $this->translatableFields()[0] ?? null,
        };
    }

    /**
     * The field this block type renders as its body copy.
     *
     * Used by the placeholder flag: writing "awaiting content" into a field
     * the block does not render leaves the flag invisible on the page, which
     * is the exact failure the flag exists to prevent.
     */
    public function bodyField(): ?string
    {
        return match ($this) {
            self::RichText, self::CallToAction => 'body',
            self::Hero => 'subhead',
            self::Pillars, self::FeaturedPublications,
            self::FeaturedComparison, self::MemberGrid, self::CommitteeGrid => 'intro',
            default => null,
        };
    }

    /**
     * How long one translatable field may be, in characters.
     *
     * Four thousand is right for a headline, an intro or a call to action,
     * and wrong for the only block type that carries a whole document. The
     * privacy notice and the terms of use are rich text blocks running past
     * four thousand characters in French before a word is added, so the cap
     * that protects the short fields made the legal pages the one thing on
     * the platform the page editor could not save.
     */
    public function maxLengthFor(string $field): int
    {
        return $this === self::RichText && $field === 'body' ? 60000 : 4000;
    }

    /**
     * Server-side validation for the settings JSON. An editor cannot give a
     * pillars block five columns, whatever the request body says.
     *
     * @return array<string, mixed>
     */
    public function settingsRules(): array
    {
        return match ($this) {
            self::Hero => [
                'image' => ['nullable', 'string', 'max:255'],
                'cta_url' => ['nullable', 'string', 'max:255'],
                'rotation_items' => ['nullable', 'array', 'max:5'],
                'rotation_items.*.image' => ['nullable', 'string', 'max:255'],
            ],
            self::KeyFigures => [
                // The figure itself only. A numeric indicator is not
                // per-language, per CLAUDE.md. Its label and its source are
                // text, so they live in the translations table as
                // item_N_label and item_N_source.
                'items' => ['nullable', 'array', 'max:8'],
                'items.*.value' => ['required', 'string', 'max:20'],
                // Where the figure goes. A count of fourteen member states
                // that a reader cannot click is a decoration of the thing it
                // counts. Named destinations rather than free text, so the
                // link resolves in the reader's language and cannot 404.
                'items.*.link' => ['nullable', 'string', 'in:'.implode(',', BlockLink::names())],
            ],
            self::Pillars => [
                // Two to four. Not five, whatever the request says.
                'columns' => ['nullable', 'integer', 'min:2', 'max:4'],
                'items' => ['nullable', 'array', 'max:8'],
                'items.*.icon' => ['nullable', 'string', 'max:60'],
                'items.*.link' => ['nullable', 'string', 'max:255'],
            ],
            self::RichText => [
                'alignment' => ['nullable', 'string', 'in:start,center'],
                // Prose by default. Columns sets headed sections side by
                // side, people sets "Name, title" lines as a grid; both
                // read the plain text, see App\Support\StructuredText.
                'layout' => ['nullable', 'string', 'in:prose,columns,people'],
                // A second id for the section, so an old link still lands.
                'also_anchor' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            ],
            self::FeaturedPublications, self::DocumentList => [
                'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
                'filter' => ['nullable', 'array'],
                'filter.sector' => ['nullable', 'string', 'max:40'],
                'filter.committee' => ['nullable', 'integer'],
                'filter.country' => ['nullable', 'integer'],
                'filter.type' => ['nullable', 'string', 'max:40'],
            ],
            self::FeaturedComparison => [
                'country_ids' => ['nullable', 'array', 'max:4'],
                'country_ids.*' => ['integer'],
                'indicator_id' => ['nullable', 'integer'],
                'chart_type' => ['nullable', 'string', 'in:bar,line,table'],
            ],
            self::NewsroomStrip => [
                'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
                'filter' => ['nullable', 'array'],
                'filter.sector' => ['nullable', 'string', 'max:40'],
            ],
            self::EventsStrip => [
                'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
                'upcoming_only' => ['nullable', 'boolean'],
            ],
            self::MemberGrid => [
                'show_map' => ['nullable', 'boolean'],
                'directory' => ['nullable', 'boolean'],
                'summary' => ['nullable', 'boolean'],
                'filter' => ['nullable', 'array'],
                'filter.sector' => ['nullable', 'string', 'max:40'],
            ],
            self::CommitteeGrid => [
                // Inactive committees are history rather than membership,
                // and a page about who does the work should not list a
                // committee that has been wound up unless it is asked to.
                'include_inactive' => ['nullable', 'boolean'],
                'filter' => ['nullable', 'array'],
                'filter.sector' => ['nullable', 'string', 'max:40'],
            ],
            self::StatementBand => [
                'cta_url' => ['nullable', 'string', 'max:255'],
            ],
            self::CallToAction => [
                'cta_url' => ['nullable', 'string', 'max:255'],
                // Tone from the defined palette only. An editor cannot set
                // an arbitrary colour.
                'tone' => ['nullable', 'string', 'in:navy,green,cream'],
            ],
            self::SocialFeed => [
                'networks' => ['nullable', 'array', 'max:4'],
                'networks.*' => ['string', 'in:facebook,linkedin,youtube,x'],
            ],
        };
    }

    /**
     * How the back office draws each settings key.
     *
     * Derived from settingsRules() rather than written out a second time,
     * so a rule and its control can never disagree. The rules stay the
     * authority: this only decides which input to show, and the server
     * validates whatever comes back against settingsRules() regardless.
     *
     * @return array<string, array{control: string, options?: list<string>, repeats?: string, fields?: array<string, array{control: string, options?: list<string>}>}>
     */
    public function settingsFields(): array
    {
        $fields = [];

        foreach ($this->settingsRules() as $key => $rules) {
            // Sub-keys are drawn by the repeating field they belong to.
            if (str_contains($key, '.')) {
                [$parent, $child] = explode('.', $key, 2);

                if (str_starts_with($child, '*')) {
                    $child = ltrim(substr($child, 1), '.');

                    // "items.*" with no field after it is a list of scalars,
                    // which the repeater draws as one column.
                    $fields[$parent]['fields'][$child === '' ? 'value' : $child] = self::controlFor($rules);

                    continue;
                }

                // "filter.sector" and its siblings are a fixed group of
                // scalars, not a repeating row.
                $fields[$parent]['control'] = 'group';
                $fields[$parent]['fields'][$child] = self::controlFor($rules);

                continue;
            }

            if (in_array('array', $rules, true)) {
                $fields[$key] ??= ['control' => 'repeater', 'fields' => []];
                // A group already declared by a sub-key stays a group.
                $fields[$key]['control'] = ($fields[$key]['control'] ?? '') === 'group' ? 'group' : 'repeater';

                continue;
            }

            $fields[$key] = self::controlFor($rules);
        }

        // A declared array with nothing inside it has nothing to draw.
        foreach ($fields as $key => $field) {
            if (in_array($field['control'], ['repeater', 'group'], true) && ($field['fields'] ?? []) === []) {
                unset($fields[$key]);
            }
        }

        return $fields;
    }

    /**
     * @param  list<mixed>  $rules
     * @return array{control: string, options?: list<string>}
     */
    private static function controlFor(array $rules): array
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                return ['control' => 'select', 'options' => explode(',', substr($rule, 3))];
            }
        }

        if (in_array('boolean', $rules, true)) {
            return ['control' => 'checkbox'];
        }

        if (in_array('integer', $rules, true)) {
            return ['control' => 'number'];
        }

        return ['control' => 'text'];
    }

    /** @return array<string, mixed> */
    public function defaultSettings(): array
    {
        return match ($this) {
            self::Pillars => ['columns' => 3, 'items' => []],
            self::KeyFigures => ['items' => []],
            self::FeaturedPublications, self::DocumentList, self::NewsroomStrip => ['limit' => 3, 'filter' => []],
            self::EventsStrip => ['limit' => 3, 'upcoming_only' => true],
            self::MemberGrid => ['show_map' => true, 'filter' => [], 'directory' => false, 'summary' => false],
            self::CommitteeGrid => ['include_inactive' => false, 'filter' => []],
            self::CallToAction => ['tone' => 'navy'],
            self::StatementBand => ['cta_url' => null],
            self::SocialFeed => ['networks' => ['facebook', 'linkedin', 'youtube', 'x']],
            self::FeaturedComparison => ['country_ids' => [], 'chart_type' => 'table'],
            self::RichText => ['alignment' => 'start'],
            self::Hero => ['rotation_items' => []],
        };
    }
}
