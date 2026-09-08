<?php

namespace App\Support;

use App\Enums\BlockType;
use App\Models\Country;
use App\Models\Document;
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
            // Indicators arrive with the completeness dashboard. Until then
            // the empty state is the honest answer, not a bug.
            BlockType::FeaturedComparison => ['rows' => collect()],
            // The events mirror is reserved scope: the platform displays
            // events, it does not manage them, and nothing syncs yet.
            BlockType::EventsStrip => ['items' => collect()],
            default => [],
        };
    }

    /** @return Collection<int, array<string, string>> */
    private static function publications(PageBlock $block): Collection
    {
        $filter = $block->setting('filter', []) ?? [];

        return Publication::listed()
            ->with(['translations', 'sector', 'country'])
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
