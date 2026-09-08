<?php

namespace App\Enums;

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
    case CallToAction = 'call_to_action';
    case SocialFeed = 'social_feed';
    case DocumentList = 'document_list';

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
            self::CallToAction => 'Call to action',
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
            self::Hero => ['headline', 'subhead', 'cta_label'],
            self::KeyFigures => ['heading'],
            self::Pillars => ['heading', 'intro'],
            self::RichText => ['heading', 'body'],
            self::FeaturedPublications => ['heading', 'intro'],
            self::FeaturedComparison => ['heading', 'intro'],
            self::NewsroomStrip => ['heading'],
            self::EventsStrip => ['heading'],
            self::MemberGrid => ['heading', 'intro'],
            self::CallToAction => ['headline', 'body', 'cta_label'],
            self::SocialFeed => ['heading'],
            self::DocumentList => ['heading'],
        };
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
                'items' => ['nullable', 'array', 'max:8'],
                'items.*.value' => ['required', 'string', 'max:20'],
                'items.*.label' => ['required', 'string', 'max:80'],
                'items.*.source' => ['nullable', 'string', 'max:200'],
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
                'filter' => ['nullable', 'array'],
                'filter.sector' => ['nullable', 'string', 'max:40'],
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

    /** @return array<string, mixed> */
    public function defaultSettings(): array
    {
        return match ($this) {
            self::Pillars => ['columns' => 3, 'items' => []],
            self::KeyFigures => ['items' => []],
            self::FeaturedPublications, self::DocumentList, self::NewsroomStrip => ['limit' => 3, 'filter' => []],
            self::EventsStrip => ['limit' => 3, 'upcoming_only' => true],
            self::MemberGrid => ['show_map' => true, 'filter' => []],
            self::CallToAction => ['tone' => 'navy'],
            self::SocialFeed => ['networks' => ['facebook', 'linkedin', 'youtube', 'x']],
            self::FeaturedComparison => ['country_ids' => [], 'chart_type' => 'table'],
            self::RichText => ['alignment' => 'start'],
            self::Hero => ['rotation_items' => []],
        };
    }
}
