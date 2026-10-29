<?php

namespace App\Admin;

use App\Enums\InstrumentStatus;
use App\Enums\InstrumentType;
use App\Enums\NewsCategory;
use App\Enums\PublicationType;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Enquiry;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\Sector;
use App\Models\Tag;
use App\Rules\SupersedesRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Every model the back office administers, and how.
 *
 * Two groups, and the difference matters. Reference data -- member states,
 * authorities, sectors, committees, the indicator taxonomy -- is editable
 * and never deletable from the interface. Deleting the row for Namibia does
 * not remove Namibia from SADC. It orphans every instrument, indicator
 * value, publication and user account attached to it, and the person who
 * did it finds out when a page 500s.
 *
 * Content -- instruments, publications, news -- soft deletes and has a
 * restore path, because taking something off the site and destroying it are
 * different decisions.
 */
final class ResourceRegistry
{
    /** @return array<string, resource> */
    public static function all(): array
    {
        static $resources = null;

        return $resources ??= [
            /* Reference data ------------------------------------------------ */

            'countries' => new Resource(
                key: 'countries',
                model: Country::class,
                columns: ['name', 'iso_code', 'joined_at'],
                fields: [
                    Field::text('name', required: true),
                    Field::text('iso_code', required: true),
                    Field::text('official_languages'),
                    Field::date('joined_at'),
                ],
                rules: [
                    'name' => ['required', 'string', 'max:120'],
                    'iso_code' => ['required', 'string', 'size:2'],
                    'official_languages' => ['nullable', 'string', 'max:120'],
                    'joined_at' => ['nullable', 'date'],
                ],
                searchable: ['name', 'iso_code'],
                orderBy: 'name',
                creatable: false,
                deletable: false,
                isReferenceData: true,
            ),

            'authorities' => new Resource(
                key: 'authorities',
                model: Authority::class,
                columns: ['name', 'acronym', 'country', 'established_year'],
                fields: [
                    Field::text('name', required: true),
                    // Required: the column is NOT NULL, so an empty acronym was a 500
                    // rather than a message (found by the live audit, 15 October 2026).
                    Field::text('acronym', required: true),
                    Field::belongsTo('country_id', 'country', required: true),
                    Field::url('website'),
                    Field::number('established_year'),
                ],
                rules: [
                    'name' => ['required', 'string', 'max:200'],
                    'acronym' => ['required', 'string', 'max:20'],
                    'country_id' => ['required', 'exists:countries,id'],
                    'website' => ['nullable', 'url', 'max:255'],
                    'established_year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
                ],
                searchable: ['name', 'acronym'],
                with: ['country'],
                orderBy: 'name',
                deletable: false,
                isReferenceData: true,
            ),

            'sectors' => new Resource(
                key: 'sectors',
                model: Sector::class,
                columns: ['name', 'slug'],
                fields: [Field::text('name', required: true)],
                rules: ['name' => ['required', 'string', 'max:80']],
                searchable: ['name', 'slug'],
                orderBy: 'name',
                creatable: false,
                deletable: false,
                isReferenceData: true,
            ),

            'committees' => new Resource(
                key: 'committees',
                model: Committee::class,
                columns: ['name', 'slug', 'sector'],
                fields: [
                    Field::text('name', required: true),
                    Field::belongsTo('sector_id', 'sector'),
                    Field::belongsTo('chair_authority_id', 'chairAuthority'),
                ],
                rules: [
                    'name' => ['required', 'string', 'max:160'],
                    'sector_id' => ['nullable', 'exists:sectors,id'],
                    'chair_authority_id' => ['nullable', 'exists:authorities,id'],
                ],
                translatable: ['mandate'],
                searchable: ['name', 'slug'],
                with: ['sector'],
                orderBy: 'name',
                deletable: false,
                isReferenceData: true,
            ),

            'indicators' => new Resource(
                key: 'indicators',
                model: Indicator::class,
                columns: ['name', 'category', 'unit', 'periodicity'],
                fields: [
                    // Every NOT NULL column is a required field with the values the
                    // column accepts. Category and data type were missing, and
                    // periodicity offered "monthly", which the column rejects: no
                    // indicator could be created (live audit, 15 October 2026).
                    Field::text('name', required: true),
                    Field::select('category', ['licensing', 'spectrum', 'interconnection', 'market', 'quality', 'universal_access'], required: true),
                    Field::select('data_type', ['numeric', 'currency', 'percentage', 'text', 'boolean', 'date'], required: true),
                    Field::textarea('definition', required: true),
                    Field::text('unit', required: true),
                    Field::select('periodicity', ['annual', 'biannual', 'quarterly', 'ad_hoc'], required: true),
                    Field::belongsTo('sector_id', 'sector'),
                    Field::text('source_standard', required: true),
                    Field::url('source_url'),
                    Field::boolean('is_active'),
                ],
                rules: [
                    'name' => ['required', 'string', 'max:200'],
                    'category' => ['required', 'in:licensing,spectrum,interconnection,market,quality,universal_access'],
                    'data_type' => ['required', 'in:numeric,currency,percentage,text,boolean,date'],
                    'definition' => ['required', 'string', 'max:2000'],
                    'unit' => ['required', 'string', 'max:40'],
                    'periodicity' => ['required', 'in:annual,biannual,quarterly,ad_hoc'],
                    'sector_id' => ['nullable', 'exists:sectors,id'],
                    // An indicator without a source is a number nobody can
                    // defend in a ministerial brief.
                    'source_standard' => ['required', 'string', 'max:200'],
                    'source_url' => ['nullable', 'url', 'max:255'],
                    'is_active' => ['nullable', 'boolean'],
                ],
                searchable: ['name', 'slug'],
                with: ['sector'],
                orderBy: 'display_order',
                deletable: false,
                isReferenceData: true,
            ),

            /* Content -------------------------------------------------------- */

            'instruments' => new Resource(
                key: 'instruments',
                model: Instrument::class,
                columns: ['reference_number', 'country', 'sector', 'status', 'enacted_on'],
                fields: [
                    Field::text('reference_number', required: true),
                    Field::belongsTo('country_id', 'country', required: true),
                    Field::belongsTo('sector_id', 'sector', required: true),
                    Field::select('instrument_type', array_column(InstrumentType::cases(), 'value'), required: true),
                    Field::select('status', array_column(InstrumentStatus::cases(), 'value'), required: true),
                    Field::date('enacted_on'),
                    Field::date('effective_from'),
                ],
                rules: [
                    'reference_number' => ['required', 'string', 'max:120'],
                    'country_id' => ['required', 'exists:countries,id'],
                    'sector_id' => ['required', 'exists:sectors,id'],
                    // The enum's own values: "string, max 60" let any other value through to
                    // the model's enum cast, which answered 500 (live audit, 15 October 2026).
                    'instrument_type' => ['required', 'in:'.implode(',', array_column(InstrumentType::cases(), 'value'))],
                    'status' => ['required', 'in:'.implode(',', array_column(InstrumentStatus::cases(), 'value'))],
                    'enacted_on' => ['nullable', 'date'],
                    'effective_from' => ['nullable', 'date'],
                ],
                translatable: ['title', 'summary', 'body'],
                searchable: ['reference_number', 'slug'],
                with: ['country', 'sector'],
                orderBy: 'reference_number',
            ),

            'publications' => new Resource(
                key: 'publications',
                model: Publication::class,
                columns: ['title', 'publication_type', 'published_on', 'is_public', 'languages', 'has_file', 'updated_at'],
                fields: [
                    Field::select('publication_type', array_column(PublicationType::cases(), 'value'), required: true),
                    Field::belongsTo('sector_id', 'sector'),
                    Field::belongsTo('committee_id', 'committee'),
                    Field::belongsTo('country_id', 'country'),
                    // Empty when the document does not state its date: the
                    // site then says "Date not recorded" (INC-03).
                    Field::date('published_on'),
                    // A publication that lives on another site. Without a
                    // file of its own it can only be published with this.
                    Field::url('external_url'),
                    Field::boolean('is_public'),

                    /*
                     * SUPERSESSION. Screen 07 draws the lineage on the
                     * document page and the model has carried it since the
                     * schema was written; there was no way to set it.
                     *
                     * Only valid candidates are offered: the same
                     * publication type, an earlier date, never itself. A
                     * guideline replacing an annual report is a data error
                     * rather than a lineage, and supersession runs one way
                     * in time.
                     */
                    Field::scoped('supersedes_id', function (?Model $record) {
                        if ($record === null || $record->publication_type === null) {
                            return [];
                        }

                        return Publication::query()
                            ->with('translations')
                            ->where('publication_type', $record->publication_type)
                            ->when($record->exists, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->when(
                                $record->published_on !== null,
                                fn ($q) => $q->whereDate('published_on', '<', $record->published_on)
                            )
                            ->orderByDesc('published_on')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn (Publication $p) => [
                                $p->getKey() => $p->translated('title', $p->slug)
                                    .($p->published_on ? ', '.$p->published_on->format('Y') : ''),
                            ])
                            ->all();
                    }),

                    /*
                     * The reverse, shown and not editable. It is set by
                     * editing the later document, and a second writable
                     * control for the same fact is how the two ends stop
                     * agreeing.
                     */
                    Field::readOnly('superseded_by', function (?Model $record) {
                        if ($record === null || ! $record->exists) {
                            return '';
                        }

                        return $record->supersededBy()
                            ->with('translations')
                            ->get()
                            ->map(fn (Publication $p) => $p->translated('title', $p->slug)
                                .($p->published_on ? ', '.$p->published_on->format('Y') : ''))
                            ->implode('; ');
                    }),
                ],
                rules: [
                    'publication_type' => ['required', 'in:'.implode(',', array_column(PublicationType::cases(), 'value'))],
                    'sector_id' => ['nullable', 'exists:sectors,id'],
                    'committee_id' => ['nullable', 'exists:committees,id'],
                    'country_id' => ['nullable', 'exists:countries,id'],
                    // Empty since 29 October 2026: five legacy guidelines
                    // carried 1 January of their year as a placeholder, and
                    // a made-up date is worse than none (INC-03).
                    'published_on' => ['nullable', 'date'],
                    'external_url' => ['nullable', 'url:http,https', 'max:2048'],
                    'is_public' => ['nullable', 'boolean'],
                ],
                rulesUsing: fn (?Model $record, Request $request) => [
                    // INC-04, UX-14, UC-T07: published needs a file, or a link
                    // to where the publication lives.
                    'is_public' => [
                        'nullable', 'boolean',
                        function (string $attribute, mixed $value, \Closure $fail) use ($record, $request) {
                            if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                                return;
                            }
                            $hasFile = $record instanceof Publication && $record->exists && $record->hasFile();
                            if (! $hasFile && trim((string) $request->input('external_url')) === '') {
                                $fail(__('office.publication_needs_file'));
                            }
                        },
                    ],
                    'supersedes_id' => [
                        'nullable',
                        new SupersedesRule(
                            $record instanceof Publication && $record->exists ? $record : null,
                            $request->string('publication_type')->value() ?: null,
                            $request->string('published_on')->value() ?: null,
                        ),
                    ],
                ],
                translatable: ['title', 'summary'],
                searchable: ['slug'],
                with: ['sector'],
                orderBy: 'published_on',
                orderDirection: 'desc',
                filters: [
                    ['key' => 'type', 'column' => 'publication_type', 'options' => collect(PublicationType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->labelKey()])->all()],
                    ['key' => 'year', 'column' => 'published_on', 'type' => 'year'],
                    ['key' => 'visibility', 'column' => 'is_public', 'options' => ['1' => 'office.visibility_public', '0' => 'office.visibility_hidden']],
                ],
            ),

            'news' => new Resource(
                key: 'news',
                model: NewsItem::class,
                columns: ['title', 'category', 'status', 'published_at', 'languages'],
                fields: [
                    Field::select('category', array_column(NewsCategory::cases(), 'value'), required: true),
                    Field::belongsTo('sector_id', 'sector'),
                    Field::select('status', ['draft', 'published'], required: true),
                    Field::date('published_at'),
                    Field::text('author_name'),

                    // The photograph, chosen from the media library. Empty
                    // means the placeholder of the item's type.
                    Field::scoped('hero_image_path', fn () => \App\Models\MediaAsset::query()
                        ->where('disk', 'public')
                        ->where('mime_type', 'like', 'image/%')
                        ->orderByDesc('id')
                        ->limit(200)
                        ->pluck('original_name', 'path')
                        ->all()),
                ],
                rules: [
                    'hero_image_path' => ['nullable', 'string', Rule::exists('media_assets', 'path')->whereNull('deleted_at')],
                    'category' => ['required', 'in:'.implode(',', array_column(NewsCategory::cases(), 'value'))],
                    'sector_id' => ['nullable', 'exists:sectors,id'],
                    'status' => ['required', 'in:draft,published'],
                    'published_at' => ['nullable', 'date'],
                    'author_name' => ['nullable', 'string', 'max:120'],
                ],
                translatable: ['title', 'summary', 'body'],
                searchable: ['slug', 'author_name'],
                with: ['sector'],
                // Newest first, by the date the item was published, not the
                // day the legacy import created the row (INC-02, UX-07).
                orderBy: 'published_at',
                orderDirection: 'desc',
                filters: [
                    ['key' => 'category', 'column' => 'category', 'options' => collect(NewsCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->labelKey()])->all()],
                    ['key' => 'year', 'column' => 'published_at', 'type' => 'year'],
                    ['key' => 'status', 'column' => 'status', 'options' => ['draft' => 'admin.status_draft', 'review' => 'office.status_review', 'published' => 'admin.status_published']],
                ],
            ),

            'indicator-values' => new Resource(
                key: 'indicator-values',
                model: IndicatorValue::class,
                columns: ['indicator', 'country', 'period', 'value_numeric'],
                fields: [
                    Field::belongsTo('indicator_id', 'indicator', required: true),
                    Field::belongsTo('country_id', 'country', required: true),
                    Field::text('period', required: true),
                    Field::number('value_numeric'),
                    Field::text('value_text'),
                    Field::text('currency'),
                    Field::textarea('source_note'),
                ],
                rules: [
                    'indicator_id' => ['required', 'exists:indicators,id'],
                    'country_id' => ['required', 'exists:countries,id'],
                    'period' => ['required', 'string', 'max:20'],
                    'value_numeric' => ['nullable', 'numeric'],
                    'value_text' => ['nullable', 'string', 'max:200'],
                    'currency' => ['nullable', 'string', 'size:3'],
                    // A figure with no source note cannot be defended when a
                    // member state queries it.
                    'source_note' => ['nullable', 'string', 'max:500'],
                ],
                // One figure per indicator, member state and period: the
                // schema's unique key turned a second one into a 500 (live
                // audit, 15 October 2026). Now it is a message on the field.
                rulesUsing: fn (?Model $record, Request $request) => [
                    'period' => [
                        'required', 'string', 'max:20',
                        Rule::unique('indicator_values', 'period')
                            ->where('indicator_id', $request->integer('indicator_id'))
                            ->where('country_id', $request->integer('country_id'))
                            ->ignore($record instanceof IndicatorValue && $record->exists ? $record->id : null),
                    ],
                ],
                searchable: ['period'],
                with: ['indicator', 'country'],
                orderBy: 'period',
            ),

            'tags' => new Resource(
                key: 'tags',
                model: Tag::class,
                columns: ['slug'],
                fields: [],
                rules: [],
                translatable: ['name'],
                searchable: ['slug'],
                orderBy: 'slug',
            ),

            'enquiries' => new Resource(
                key: 'enquiries',
                model: Enquiry::class,
                columns: ['name', 'email', 'subject', 'created_at'],
                fields: [],
                rules: [],
                searchable: ['name', 'email', 'subject'],
                with: ['country'],
                orderBy: 'created_at',
                // An enquiry is a message somebody sent. It is read and
                // answered, never edited: rewriting what a regulator wrote
                // and keeping their name on it is not a feature.
                creatable: false,
            ),
        ];
    }

    public static function find(string $key): Resource
    {
        $resources = self::all();

        if (! array_key_exists($key, $resources)) {
            throw new InvalidArgumentException('Unknown resource: '.$key);
        }

        return $resources[$key];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }
}
