<?php

namespace App\Support;

use App\Enums\BlockType;
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
        return match ($block->type()) {
            BlockType::FeaturedPublications => ['items' => self::publications($block)],
            BlockType::NewsroomStrip => ['items' => self::news($block)],
            BlockType::DocumentList => ['documents' => self::documents($block)],
            BlockType::MemberGrid => ['countries' => self::countries()],
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
                ->when($block->setting('upcoming_only', true), fn ($q) => $q->upcoming(), fn ($q) => $q->orderByDesc('starts_on'))
                ->limit(max(1, min(12, $limit)))
                ->get()
                ->map(fn ($meeting) => [
                    'title' => $meeting->title,
                    'kicker' => $meeting->starts_on->isoFormat('D MMMM YYYY'),
                    'meta' => $meeting->location ?? '',
                    'url' => route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]),
                ]),
        ];
    }

    /**
     * A pinned comparison, from the sourced indicators.
     *
     * A section whose only content is an empty state is a section that is
     * not ready. This one shows a real pinned comparison drawn from the
     * sourced indicators, and links through to the comparator with the
     * same states and indicator already selected, so the homepage is an
     * entry point to RP-10 rather than a picture of one.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, indicator: Indicator|null}
     */
    private static function comparison(PageBlock $block): array
    {
        $indicator = Indicator::active()
            ->when($block->setting('indicator_id'), fn ($q, $id) => $q->whereKey($id))
            // Failing that, the indicator with the widest reporting, because
            // a comparison of four blanks demonstrates nothing.
            ->withCount('values')
            ->orderByDesc('values_count')
            ->orderBy('display_order')
            ->first();

        if ($indicator === null) {
            return ['rows' => collect(), 'indicator' => null];
        }

        $pinned = collect($block->setting('country_ids', []))->filter();

        $values = IndicatorValue::where('indicator_id', $indicator->id)
            ->with('country')
            ->when($pinned->isNotEmpty(), fn ($q) => $q->whereIn('country_id', $pinned->all()))
            ->orderByDesc('period')
            ->get()
            ->unique('country_id')
            ->sortByDesc('value_numeric')
            ->take(4);

        /*
         * A pinned member state with no value still appears, and says so.
         *
         * Dropping it would leave four filled cells on the homepage and
         * hide the thing the comparator is careful about: "Not reported"
         * and "0" mean opposite things, and a reader should meet that
         * distinction on the front page rather than discover it later.
         */
        if ($pinned->isNotEmpty()) {
            $missing = Country::whereIn('id', $pinned->all())
                ->whereNotIn('id', $values->pluck('country_id'))
                ->get()
                ->map(fn (Country $country) => new IndicatorValue([
                    'indicator_id' => $indicator->id,
                    'country_id' => $country->id,
                ]))
                ->each(fn (IndicatorValue $v, $i) => $v->setRelation('country', Country::find($v->country_id)));

            $values = $values->concat($missing)->take(4);
        }

        $codes = $values->map(fn (IndicatorValue $v) => $v->country?->iso_code)->filter()->values();

        return [
            'indicator' => $indicator,
            // The comparator, opened on exactly what the homepage shows.
            'comparator_url' => route('comparator', [
                'locale' => app()->getLocale(),
                'mode' => 'compare',
                'category' => $indicator->category,
                'countries' => $codes->implode(','),
            ]),
            'rows' => $values->map(fn (IndicatorValue $v) => [
                'country' => $v->country?->name,
                'value' => $v->value_numeric !== null
                    ? rtrim(rtrim(number_format((float) $v->value_numeric, 1), '0'), '.')
                    : null,
                'reported' => $v->value_numeric !== null || filled($v->value_text),
                'unit' => $indicator->unit,
                'period' => $v->period,
                'confidence' => $v->confidence,
                'is_sample' => $v->is_sample,
            ])->values(),
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
            ->orderByDesc('published_at')
            ->limit((int) $block->setting('limit', 3))
            ->get()
            ->map(fn (NewsItem $n) => [
                'kicker' => __($n->category->labelKey()),
                'title' => $n->translated('title', $n->slug),
                'summary' => $n->translated('summary', ''),
                'image_slot' => $n->fallbackImageSlot(),
                'hero' => $n->hero_image_path,
                'sector' => $n->sector?->name,
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
}
