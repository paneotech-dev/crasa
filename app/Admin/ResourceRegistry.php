<?php

namespace App\Admin;

use App\Enums\InstrumentStatus;
use App\Enums\InstrumentType;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Enquiry;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\NewsCategory;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\PublicationType;
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
 * authorities, sectors, committees, the indicator taxonomy -- is what other
 * records point at. Deleting the row for Namibia does not remove Namibia
 * from SADC. It orphans every instrument, indicator value, publication and
 * user account attached to it, and the person who did it finds out when a
 * page 500s. So since Day 3 a reference row can be deleted only while
 * nothing points at it, and the refusal names what does (see references).
 * Sectors stay fixed.
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
                    // NOT NULL: the platform languages it is served in, as
                    // codes ("en", "pt", "en,fr").
                    Field::text('official_languages', required: true),
                    Field::date('joined_at'),
                ],
                rules: [
                    'name' => ['required', 'string', 'max:120'],
                    'iso_code' => ['required', 'string', 'size:2'],
                    'official_languages' => ['required', 'string', 'max:120'],
                    'joined_at' => ['nullable', 'date'],
                ],
                // Day 3: the ISO code is the member state's address on the
                // site and the key of its lang file line, so it is two
                // lower-case letters and nobody else's.
                rulesUsing: fn (?Model $record, Request $request) => [
                    'iso_code' => [
                        'required', 'string', 'size:2', 'regex:/^[a-z]{2}$/',
                        Rule::unique('countries', 'iso_code')->ignore($record?->exists ? $record->getKey() : null),
                    ],
                ],
                searchable: ['name', 'iso_code'],
                orderBy: 'name',
                isReferenceData: true,
                localised: ['name'],
                references: fn (Model $country) => self::count([
                    'authorities' => ['authorities', 'country_id'],
                    'instruments' => ['instruments', 'country_id'],
                    'indicator_values' => ['indicator_values', 'country_id'],
                    'submissions' => ['submissions', 'country_id'],
                    'committee_seats' => ['committee_country', 'country_id'],
                    'users' => ['users', 'country_id'],
                    'folders' => ['folders', 'country_id'],
                    'documents' => ['documents', 'country_id'],
                    'publications' => ['publications', 'country_id'],
                    'delegate_registrations' => ['delegate_registrations', 'country_id'],
                    'enquiries' => ['enquiries', 'country_id'],
                ], $country->getKey()),
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
                    // Confirming is its own action on the confirmation
                    // screen, which records who vouched and when. The form
                    // shows the result and never writes it.
                    Field::readOnly('confirmation', fn (?Model $record) => ! $record?->exists ? '' : ($record instanceof Authority && $record->isConfirmed()
                        ? __('d3_crud.confirmed_by', [
                            'name' => $record->confirmed_by_name ?? $record->confirmedBy?->name ?? '',
                            'date' => $record->confirmed_at->isoFormat('D MMMM YYYY'),
                        ])
                        : __('d3_crud.not_confirmed'))),
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
                isReferenceData: true,
                localised: ['name'],
                references: fn (Model $authority) => self::count([
                    'chaired_committees' => ['committees', 'chair_authority_id'],
                    'users' => ['users', 'authority_id'],
                    'contacts' => ['authority_contacts', 'authority_id'],
                    'proposed_changes' => ['authority_changes', 'authority_id'],
                ], $authority->getKey()) + [
                    'documents' => \Illuminate\Support\Facades\DB::table('documents')
                        ->where('documentable_type', $authority->getMorphClass())
                        ->where('documentable_id', $authority->getKey())
                        ->count(),
                ],
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
                isReferenceData: true,
                localised: ['name'],
                references: fn (Model $committee) => self::count([
                    'meetings' => ['meetings', 'committee_id'],
                    'folders' => ['folders', 'committee_id'],
                    'documents' => ['documents', 'committee_id'],
                    'publications' => ['publications', 'committee_id'],
                    'announcements' => ['announcements', 'committee_id'],
                    'projects' => ['projects', 'lead_committee_id'],
                    'committee_seats' => ['committee_country', 'committee_id'],
                    'people_seats' => ['committee_user', 'committee_id'],
                ], $committee->getKey()),
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
                    // What the comparator sorts by and the reading note it
                    // carries for figures above 100.
                    Field::number('display_order', required: true),
                    Field::boolean('may_exceed_hundred'),
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
                    'display_order' => ['required', 'integer', 'min:0', 'max:65535'],
                    'may_exceed_hundred' => ['nullable', 'boolean'],
                    'is_active' => ['nullable', 'boolean'],
                ],
                searchable: ['name', 'slug'],
                with: ['sector'],
                orderBy: 'display_order',
                isReferenceData: true,
                localised: ['name', 'definition'],
                // A figure a member state reported is not removed by
                // removing the question it answered.
                references: fn (Model $indicator) => self::count([
                    'indicator_values' => ['indicator_values', 'indicator_id'],
                    'submission_values' => ['submission_values', 'indicator_id'],
                    'submission_comments' => ['submission_field_comments', 'indicator_id'],
                ], $indicator->getKey()),
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
                // An instrument others name as the one that replaced them
                // keeps its place in their lineage until they are changed.
                references: fn (Model $instrument) => [
                    'superseded_instruments' => Instrument::query()->withoutGlobalScopes()
                        ->where('superseded_by_id', $instrument->getKey())->count(),
                ],
            ),

            'publications' => new Resource(
                key: 'publications',
                model: Publication::class,
                columns: ['title', 'publication_type', 'published_on', 'is_public', 'languages', 'has_file', 'updated_at'],
                fields: [
                    // From the list kept under Content > Taxonomies.
                    self::term('publication_type', PublicationType::class),
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
                            ->where('publication_type', $record->typeSlug())
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
                    'publication_type' => ['required', 'string', 'exists:publication_types,slug'],
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
                    ['key' => 'type', 'column' => 'publication_type', 'options' => fn () => PublicationType::terms()->mapWithKeys(fn ($t) => [$t->slug => $t->label()])->all()],
                    ['key' => 'year', 'column' => 'published_on', 'type' => 'year'],
                    ['key' => 'visibility', 'column' => 'is_public', 'options' => ['1' => 'office.visibility_public', '0' => 'office.visibility_hidden']],
                ],
            ),

            'news' => new Resource(
                key: 'news',
                model: NewsItem::class,
                columns: ['title', 'category', 'status', 'published_at', 'languages'],
                fields: [
                    self::term('category', NewsCategory::class),
                    Field::belongsTo('sector_id', 'sector'),
                    Field::select('status', ['draft', 'published'], required: true),
                    Field::date('published_at'),
                    Field::text('author_name'),

                    // The photograph, chosen from the media library or
                    // uploaded on the form, with its focal point (Day 3).
                    // Empty means the placeholder of the item's type.
                    new Field('hero_image_path', 'photo', options: ['x' => 'hero_focal_x', 'y' => 'hero_focal_y'], optionsUsing: fn () => \App\Models\MediaAsset::query()
                        ->where('disk', 'public')
                        ->where('mime_type', 'like', 'image/%')
                        ->orderByDesc('id')
                        ->limit(200)
                        ->pluck('original_name', 'path')
                        ->all()),
                ],
                rulesUsing: fn (?Model $record, Request $request) => \App\Support\NewsPhoto::rules(),
                rules: [
                    'hero_image_path' => ['nullable', 'string', Rule::exists('media_assets', 'path')->whereNull('deleted_at')],
                    'category' => ['required', 'string', 'exists:news_categories,slug'],
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
                    ['key' => 'category', 'column' => 'category', 'options' => fn () => NewsCategory::terms()->mapWithKeys(fn ($c) => [$c->slug => $c->label()])->all()],
                    ['key' => 'year', 'column' => 'published_at', 'type' => 'year'],
                    ['key' => 'status', 'column' => 'status', 'options' => ['draft' => 'admin.status_draft', 'review' => 'office.status_review', 'published' => 'admin.status_published']],
                ],
            ),

            // S8 remainder (evening pass): the facts and milestones the
            // public project page shows when they are filled in.
            'projects' => new Resource(
                key: 'projects',
                model: \App\Models\Project::class,
                columns: ['title', 'programme', 'state', 'status', 'starts_on'],
                fields: [
                    Field::select('state', ['current', 'past'], required: true),
                    Field::select('status', ['draft', 'published'], required: true),
                    Field::date('published_at'),
                    Field::text('programme'),
                    Field::belongsTo('sector_id', 'sector'),
                    Field::belongsTo('lead_committee_id', 'leadCommittee'),
                    Field::text('partner'),
                    Field::text('funding_partner'),
                    Field::text('countries'),
                    Field::date('starts_on'),
                    Field::date('ends_on'),
                    Field::scoped('hero_image_path', fn () => \App\Models\MediaAsset::query()
                        ->where('disk', 'public')
                        ->where('mime_type', 'like', 'image/%')
                        ->orderByDesc('id')
                        ->limit(200)
                        ->pluck('original_name', 'path')
                        ->all()),
                ],
                rules: [
                    'state' => ['required', 'in:current,past'],
                    'status' => ['required', 'in:draft,published'],
                    'published_at' => ['nullable', 'date'],
                    'programme' => ['nullable', 'string', 'max:120'],
                    'sector_id' => ['nullable', 'exists:sectors,id'],
                    'lead_committee_id' => ['nullable', 'exists:committees,id'],
                    'partner' => ['nullable', 'string', 'max:200'],
                    'funding_partner' => ['nullable', 'string', 'max:200'],
                    'countries' => ['nullable', 'string', 'max:500'],
                    'starts_on' => ['nullable', 'date'],
                    'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
                    'hero_image_path' => ['nullable', 'string', Rule::exists('media_assets', 'path')->whereNull('deleted_at')],
                ],
                translatable: ['title', 'summary', 'body', 'milestones'],
                searchable: ['slug', 'programme', 'partner'],
                with: ['sector'],
                orderBy: 'starts_on',
                orderDirection: 'desc',
                filters: [
                    ['key' => 'state', 'column' => 'state', 'options' => ['current' => 'admin.option_current', 'past' => 'admin.option_past']],
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

    /**
     * Rows in each table whose column holds this id, keyed as given.
     * Counted on the table, under no global scope, because the database
     * cascade that would follow a delete sees every row.
     *
     * @param  array<string, array{0: string, 1: string}>  $tables
     * @return array<string, int>
     */
    private static function count(array $tables, int|string $id): array
    {
        return collect($tables)
            ->map(fn (array $t) => \Illuminate\Support\Facades\DB::table($t[0])->where($t[1], $id)->count())
            ->all();
    }

    /**
     * A required choice from one taxonomy list, held by slug: news
     * categories and publication types, kept under Content > Taxonomies.
     *
     * @param  class-string<NewsCategory|PublicationType>  $term
     */
    private static function term(string $name, string $term): Field
    {
        return new Field($name, 'relation', required: true, optionsUsing: fn () => $term::terms()
            ->mapWithKeys(fn ($t) => [$t->slug => $t->label()])
            ->all());
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
