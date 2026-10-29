<?php

namespace App\Support;

use App\Enums\BlockType;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\PageBlock;
use App\Models\Publication;
use Illuminate\Support\Collection;

/**
 * Supplies a block with the records it displays.
 *
 * The blocks were written with their empty states first and never given a
 * source, so a seeded database still rendered "No publications match this
 * filter" three times down the homepage. An empty state is correct when
 * there is no data; it is a bug when there is.
 *
 * Every query here is read-only and respects the same visibility rules as
 * the pages the records live on: unlisted publications, unpublished news and
 * draft instruments never reach a block.
 */
final class BlockData
{
    /** @return array<string, mixed> */
    public static function for(PageBlock $block): array
    {
        /*
         * The lists every visitor sees alike are kept for ten minutes as
         * plain arrays. The blocks that hand their template models (the
         * member grid, the comparison, documents, committees) are built per
         * request from eager-loaded queries instead.
         */
        if (in_array($block->type(), [BlockType::NewsroomStrip, BlockType::FeaturedPublications, BlockType::EventsStrip], true)) {
            $items = PublicCache::remember(
                'block:'.$block->id.':'.$block->updated_at?->getTimestamp(),
                fn () => collect(self::compute($block)['items'])->all(),
            );

            return ['items' => collect($items)];
        }

        return self::compute($block);
    }

    /** @return array<string, mixed> */
    private static function compute(PageBlock $block): array
    {
        return match ($block->type()) {
            BlockType::FeaturedPublications => ['items' => self::publications($block)],
            BlockType::NewsroomStrip => ['items' => self::news($block)],
            BlockType::DocumentList => ['documents' => self::documents($block)],
            BlockType::MemberGrid => ['countries' => self::countries()],
            BlockType::CommitteeGrid => ['committees' => self::committees($block)],
            BlockType::FeaturedComparison => self::comparison($block),
            BlockType::EventsStrip => self::events($block),
            default => [],
        };
    }

    /**
     * Upcoming public meetings.
     *
     * The platform displays events; it does not manage them. This block
     * reads what the Secretariat has published and links out to whoever
     * runs registration, which is the whole extent of the feature on this
     * side of the boundary.
     *
     * @return array{items: Collection<int, array<string, mixed>>}
     */
    private static function events(PageBlock $block): array
    {
        $limit = (int) $block->setting('limit', 3);

        return [
            'items' => Meeting::query()
                ->public()
                ->when($block->setting('upcoming_only', true), fn ($q) => $q->upcoming(), fn ($q) => $q->orderBy('date_unknown')->orderByDesc('starts_on'))
                ->limit(max(1, min(12, $limit)))
                ->get()
                ->map(fn ($meeting) => [
                    'title' => $meeting->title,
                    'kicker' => $meeting->date_unknown ? __('events.date_not_stated') : $meeting->starts_on->isoFormat('D MMMM YYYY'),
                    'meta' => $meeting->location ?? '',
                    'url' => route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]),
                ]),
        ];
    }

    /**
     * A comparison the reader can drive.
     *
     * This block used to render a fixed four-state table under an intro
     * that said "select up to four member states and see them side by
     * side" — describing an interaction the reader could not have on the
     * page they were reading it on. RP-10 is the contract's flagship tool
     * and the homepage was a picture of it.
     *
     * It now carries its own selectors: two member states and one
     * indicator, in the query string, so the whole state of the comparison
     * is in the URL a reader pastes into an email. The full four-state tool
     * is one link away and the link arrives already set to what the reader
     * built here.
     *
     * The selectors are a GET form. With no script the homepage reloads to
     * the same anchor; with one, the table swaps in place.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, indicator: Indicator|null, ...}
     */
    private static function comparison(PageBlock $block): array
    {
        $request = request();

        // Only indicators a member state has actually filed a figure for. An
        // indicator offering two blank cells demonstrates nothing.
        $choices = Indicator::active()
            // Counted in the same query: the fallback below ranks every
            // indicator by how widely it is reported, which was one count
            // query per indicator.
            ->withCount('values')
            ->whereHas('values', fn ($q) => $q->whereNotNull('value_numeric'))
            ->orderBy('category')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $indicator = $choices->firstWhere('slug', $request->query('cmp_indicator'))
            ?? $choices->firstWhere('id', $block->setting('indicator_id'))
            // Failing both, the indicator with the widest reporting.
            ?? $choices->sortByDesc('values_count')->first();

        if ($indicator === null) {
            return ['rows' => collect(), 'indicator' => null, 'choices' => $choices, 'states' => collect(), 'selected' => []];
        }

        $states = Country::orderBy('name')->get();
        $pinned = collect($block->setting('country_ids', []))->filter();

        // Two member states. Whatever the reader asked for, then whatever
        // the block pins, then the two that have filed the highest figures
        // so the default is a comparison rather than two blanks.
        $chosen = collect([$request->query('cmp_a'), $request->query('cmp_b')])
            ->map(fn ($iso) => $states->firstWhere('iso_code', is_string($iso) ? strtolower($iso) : null))
            ->filter()
            ->unique('id')
            ->values();

        if ($chosen->count() < 2) {
            $fallback = $states->whereIn('id', $pinned->all())->values();

            if ($fallback->count() < 2) {
                $ranked = IndicatorValue::where('indicator_id', $indicator->id)
                    ->whereNotNull('value_numeric')
                    ->orderByDesc('value_numeric')
                    ->pluck('country_id')
                    ->unique();

                $fallback = $fallback->concat($states->whereIn('id', $ranked->all()))->unique('id')->values();
            }

            $chosen = $chosen
                ->concat($fallback->reject(fn (Country $c) => $chosen->contains('id', $c->id)))
                ->take(2)
                ->values();
        }

        $chosen = $chosen->take(2)->values();

        $values = IndicatorValue::where('indicator_id', $indicator->id)
            ->whereIn('country_id', $chosen->pluck('id')->all())
            ->orderByDesc('period')
            ->get()
            ->unique('country_id')
            ->keyBy('country_id');

        return [
            'indicator' => $indicator,
            'choices' => $choices,
            'states' => $states,
            'selected' => [
                'indicator' => $indicator->slug,
                'a' => $chosen->get(0)?->iso_code,
                'b' => $chosen->get(1)?->iso_code,
            ],
            // The comparator, opened on exactly what the homepage shows.
            'comparator_url' => route('comparator', [
                'locale' => app()->getLocale(),
                'mode' => 'compare',
                'category' => $indicator->category,
                'countries' => $chosen->pluck('iso_code')->implode(','),
            ]),
            /*
             * A chosen member state with no value still appears, and says
             * so. Dropping it would hide the thing the comparator is
             * careful about: "Not reported" and "0" mean opposite things,
             * and a reader should meet that distinction on the front page
             * rather than discover it later.
             */
            'rows' => $chosen->map(function (Country $country) use ($indicator, $values) {
                $value = $values[$country->id] ?? null;
                $cell = new ComparisonCell($indicator, $country, $value);

                return [
                    'country' => $country->name,
                    'iso' => $country->iso_code,
                    'value' => $cell->isReported() ? $cell->display() : null,
                    'reported' => $cell->isReported(),
                    'unit' => $indicator->unitLabel(),
                    'period' => $value?->period,
                    'confidence' => $value?->confidence ?? 'reported',
                    'is_sample' => (bool) $value?->is_sample,
                ];
            })->values(),
        ];
    }

    /** @return Collection<int, array<string, string>> */
    private static function publications(PageBlock $block): Collection
    {
        $filter = $block->setting('filter', []) ?? [];

        return Publication::listed()
            ->with(['translations', 'sector', 'country', 'documents', 'supersededBy'])
            ->when($filter['sector'] ?? null, fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->where('slug', $v)))
            ->when($filter['country'] ?? null, fn ($q, $v) => $q->where('country_id', $v))
            ->when($filter['committee'] ?? null, fn ($q, $v) => $q->where('committee_id', $v))
            ->when($filter['type'] ?? null, fn ($q, $v) => $q->where('publication_type', $v))
            ->orderByDesc('published_on')
            ->limit((int) $block->setting('limit', 3))
            ->get()
            ->map(fn (Publication $p) => [
                'kicker' => __($p->publication_type->labelKey()),
                'title' => $p->translated('title', $p->slug),
                // Screen 07's document row carries a summary and a
                // thumbnail, not a label and a date. A third of the page
                // width should hold more than a line and a half.
                'summary' => $p->translated('summary', ''),
                'thumbnail' => $p->primaryDocument()?->thumbnail_path,
                'filetype' => $p->primaryDocument()
                    ? strtoupper(pathinfo($p->primaryDocument()->original_filename, PATHINFO_EXTENSION))
                    : null,
                'size' => $p->primaryDocument()?->readableSize(),
                'locale' => $p->primaryDocument()?->locale,
                'superseded' => $p->supersededBy->isNotEmpty(),
                'meta' => collect([
                    $p->country?->name,
                    $p->sector?->name,
                    $p->published_on->format('Y'),
                ])->filter()->implode(', '),
                'url' => route('publications.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]),
            ]);
    }

    /** @return Collection<int, array<string, string>> */
    private static function news(PageBlock $block): Collection
    {
        $filter = $block->setting('filter', []) ?? [];

        return NewsItem::published()
            ->with(['translations', 'sector'])
            ->when($filter['sector'] ?? null, fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->where('slug', $v)))
            ->orderBy('date_unknown')
            ->orderByDesc('published_at')
            ->limit((int) $block->setting('limit', 3))
            ->get()
            ->map(fn (NewsItem $n) => [
                'kicker' => __($n->category->labelKey()),
                'title' => $n->translated('title', $n->slug),
                'summary' => $n->translated('summary', ''),
                'image_slot' => $n->fallbackImageSlot(),
                // The card-sized WebP rendition, not the original upload.
                'hero' => ResizedImage::cardPath($n->hero_image_path),
                'sector' => $n->sector?->name,
                // For the designed panel a sector uses when the library has
                // no photograph for it. Broadcasting has none at all.
                'sector_slug' => $n->sector?->slug,
                'meta' => collect([
                    $n->published_at?->translatedFormat('j F Y'),
                    $n->sector?->name,
                ])->filter()->implode(', '),
                'url' => route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $n->slug]),
            ]);
    }

    /** @return Collection<int, Document> */
    private static function documents(PageBlock $block): Collection
    {
        // Public documents only. A block on a public page must never surface
        // a member-graded file, whoever is reading.
        return Document::query()
            ->public()
            ->with('documentable')
            ->orderByDesc('created_at')
            ->limit((int) $block->setting('limit', 3))
            ->get();
    }

    /** @return Collection<int, Country> */
    private static function countries(): Collection
    {
        return Country::with(['authority.sectors', 'committees'])
            ->orderBy('name')
            ->get();
    }

    /**
     * The committees, with the counts each card states.
     *
     * withCount rather than loading the relations: a card states how many
     * member states are seated and how many documents the committee owns,
     * and loading fourteen countries per committee to count them would be
     * six queries to print six numbers.
     *
     * @return Collection<int, Committee>
     */
    private static function committees(PageBlock $block): Collection
    {
        $filter = $block->setting('filter', []) ?? [];

        return Committee::query()
            // translations, since the mandate became per language on
            // 10 September 2026. Without it every card lazy-loads its own.
            ->with(['sector', 'chairAuthority.country', 'translations'])
            ->withCount(['countries', 'publications', 'meetings'])
            ->unless($block->setting('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($filter['sector'] ?? null, fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->where('slug', $v)))
            ->orderBy('name')
            ->get();
    }
}
