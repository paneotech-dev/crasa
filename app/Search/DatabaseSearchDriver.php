<?php

namespace App\Search;

use App\Models\Country;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Search over MariaDB InnoDB FULLTEXT.
 *
 * SECURITY. The country scope applies here exactly as it does everywhere
 * else. Search is the classic hole in an otherwise sound authorisation
 * layer: the records are reachable by a different path, the policies are not
 * consulted because nothing is being "viewed" yet, and a snippet leaks the
 * text even when the link would 403. Every source below filters by what the
 * reader may see before it ranks anything, and there is a test for it.
 *
 * Unpublished instruments, unlisted publications and unpublished news never
 * enter the result set at all, for anyone.
 */
class DatabaseSearchDriver implements SearchDriver
{
    public function name(): string
    {
        return DB::getDriverName() === 'mysql' ? 'mariadb-fulltext' : 'like-fallback';
    }

    /** FULLTEXT is MySQL and MariaDB only; SQLite gets LIKE. */
    private function supportsFulltext(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    public function search(string $query, array $filters = [], int $perPage = 12, int $page = 1): SearchResults
    {
        $query = trim($query);

        if ($query === '') {
            return SearchResults::empty();
        }

        $hits = collect()
            ->concat($this->instruments($query, $filters))
            ->concat($this->publications($query, $filters))
            ->concat($this->news($query, $filters))
            ->concat($this->members($query, $filters))
            ->sortByDesc(fn (SearchHit $hit) => $hit->score)
            ->values();

        $facets = $this->facetsFor($hits);
        $total = $hits->count();

        return new SearchResults(
            hits: $hits->forPage($page, $perPage)->values(),
            total: $total,
            page: $page,
            perPage: $perPage,
            facets: $facets,
        );
    }

    /**
     * Predictive suggestions.
     *
     * SECURITY. This method used to read the translations table directly.
     * That was a hole: the query matched every title row of every
     * publication, news item and instrument, published or not, listed or
     * not, and returned it as a suggestion. A reader could learn the title
     * of an unlisted country publication by typing three letters, and the
     * link they would then follow would correctly refuse them — which tells
     * them the document exists, which is the part that matters.
     *
     * Suggestions now come from the same visibility scopes the results come
     * from: Instrument::published(), Publication::listed(),
     * NewsItem::published(). A title cannot be suggested unless the same
     * reader could have found it by searching, and could then open it.
     *
     * Matching is LIKE rather than FULLTEXT because a reader typing "spec"
     * has not finished a word yet and FULLTEXT will not match a prefix. The
     * candidate set is bounded by the limit and by the visibility scope
     * before it is sorted.
     *
     * @return array<int, string>
     */
    public function suggest(string $partial, int $limit = 6): array
    {
        $partial = trim($partial);

        if (mb_strlen($partial) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $partial).'%';

        $titled = fn (Builder $query) => $query
            ->whereHas('translations', fn ($t) => $t->where('field', 'title')->where('value', 'like', $like))
            ->with('translations')
            ->limit($limit * 3)
            ->get()
            ->map(fn ($record) => $record->translated('title', ''));

        $suggestions = collect()
            ->concat($titled(Instrument::published()))
            ->concat($titled(Publication::listed()))
            ->concat($titled(NewsItem::published()))
            // Member states and their regulators are public by definition,
            // and they are the most common thing typed into this field.
            ->concat(Country::query()
                ->where('name', 'like', $like)
                ->orWhereHas('authority', fn ($a) => $a->where('name', 'like', $like)->orWhere('acronym', 'like', $like))
                ->limit($limit)
                ->pluck('name'));

        return $suggestions
            ->filter(fn (string $value) => filled($value))
            ->unique()
            // Shortest first, sorted in PHP: CHAR_LENGTH is MySQL-only and
            // the candidate set is already small.
            ->sortBy(fn (string $value) => mb_strlen($value))
            ->take($limit)
            ->values()
            ->all();
    }

    /* Sources ------------------------------------------------------------- */

    /** @return Collection<int, SearchHit> */
    private function instruments(string $query, array $filters): Collection
    {
        $ids = $this->matchingTranslatableIds(Instrument::class, $query);

        if ($ids === []) {
            return collect();
        }

        return Instrument::published()
            ->whereIn('id', array_keys($ids))
            ->with(['translations', 'country', 'sector'])
            ->when($filters['country'] ?? null, fn ($q, $v) => $q->whereHas('country', fn ($c) => $c->where('iso_code', $v)))
            ->when($filters['sector'] ?? null, fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->where('slug', $v)))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('instrument_type', $v))
            ->get()
            ->map(fn (Instrument $i) => new SearchHit(
                type: 'instrument',
                title: $i->translated('title', $i->slug),
                snippet: $this->snippetFrom([$i->translated('summary', ''), $i->translated('title', '')], $query),
                url: route('publications', ['locale' => app()->getLocale()]),
                meta: $i->instrument_type->label(),
                country: $i->country?->name,
                sector: $i->sector?->name,
                year: $i->enacted_on?->year,
                score: $ids[$i->id] ?? 0,
            ));
    }

    /** @return Collection<int, SearchHit> */
    private function publications(string $query, array $filters): Collection
    {
        $ids = $this->matchingTranslatableIds(Publication::class, $query);

        if ($ids === []) {
            return collect();
        }

        return Publication::listed()
            ->whereIn('id', array_keys($ids))
            ->with(['translations', 'country', 'sector'])
            ->when($filters['country'] ?? null, fn ($q, $v) => $q->whereHas('country', fn ($c) => $c->where('iso_code', $v)))
            ->when($filters['sector'] ?? null, fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->where('slug', $v)))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('publication_type', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereYear('published_on', $v))
            ->get()
            ->map(fn (Publication $p) => new SearchHit(
                type: 'publication',
                title: $p->translated('title', $p->slug),
                snippet: $this->snippetFrom([$p->translated('summary', ''), $p->translated('title', '')], $query),
                url: route('publications.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]),
                meta: __($p->publication_type->labelKey()),
                country: $p->country?->name,
                sector: $p->sector?->name,
                year: $p->published_on?->year,
                score: $ids[$p->id] ?? 0,
            ));
    }

    /** @return Collection<int, SearchHit> */
    private function news(string $query, array $filters): Collection
    {
        $ids = $this->matchingTranslatableIds(NewsItem::class, $query);

        if ($ids === []) {
            return collect();
        }

        return NewsItem::published()
            ->whereIn('id', array_keys($ids))
            ->with(['translations', 'sector'])
            ->when($filters['sector'] ?? null, fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->where('slug', $v)))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereYear('published_at', $v))
            ->get()
            ->map(fn (NewsItem $n) => new SearchHit(
                type: 'news',
                title: $n->translated('title', $n->slug),
                snippet: $this->snippetFrom([$n->translated('summary', ''), $n->translated('title', '')], $query),
                url: route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $n->slug]),
                meta: __($n->category->labelKey()),
                sector: $n->sector?->name,
                year: $n->published_at?->year,
                score: $ids[$n->id] ?? 0,
            ));
    }

    /** @return Collection<int, SearchHit> */
    private function members(string $query, array $filters): Collection
    {
        if (($filters['type'] ?? null) !== null && $filters['type'] !== 'member') {
            return collect();
        }

        return Country::with('authority')
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', '%'.$query.'%')
                ->orWhereHas('authority', fn ($a) => $a
                    ->where('name', 'like', '%'.$query.'%')
                    ->orWhere('acronym', 'like', '%'.$query.'%')))
            ->when($filters['country'] ?? null, fn ($q, $v) => $q->where('iso_code', $v))
            ->get()
            ->map(fn (Country $c) => new SearchHit(
                type: 'member',
                title: $c->name,
                snippet: $this->snippetFrom([$c->authority?->name ?? '', $c->name], $query),
                url: route('members.show', ['locale' => app()->getLocale(), 'iso' => $c->iso_code]),
                meta: $c->authority?->acronym,
                country: $c->name,
                score: 1.0,
            ));
    }

    /* Matching ------------------------------------------------------------ */

    /**
     * Record ids whose translated text matches, with a relevance score.
     *
     * @return array<int, float>
     */
    private function matchingTranslatableIds(string $type, string $query): array
    {
        $rows = DB::table('translations')
            ->where('translatable_type', $type)
            ->when(
                $this->supportsFulltext(),
                fn ($q) => $q
                    ->selectRaw('translatable_id, MAX(MATCH(value) AGAINST (? IN NATURAL LANGUAGE MODE)) as score', [$query])
                    ->whereRaw('MATCH(value) AGAINST (? IN NATURAL LANGUAGE MODE)', [$query]),
                fn ($q) => $q
                    ->selectRaw('translatable_id, 1 as score')
                    ->where('value', 'like', '%'.$query.'%')
            )
            ->groupBy('translatable_id')
            ->get();

        return $rows->pluck('score', 'translatable_id')
            ->map(fn ($score) => (float) $score)
            ->all();
    }

    /**
     * The snippet is taken from whichever field actually contains a searched
     * term, falling back to the first field with any text.
     *
     * Taking it from the summary regardless meant a record matched on its
     * title showed a snippet with nothing highlighted, which tells a reader
     * nothing about why it is in their results.
     *
     * @param  list<string>  $candidates
     */
    private function snippetFrom(array $candidates, string $query): string
    {
        $terms = $this->terms($query);

        foreach ($candidates as $candidate) {
            $text = trim(strip_tags($candidate));

            if ($text === '') {
                continue;
            }

            foreach ($terms as $term) {
                if (mb_stripos($text, $term) !== false) {
                    return $this->snippet($candidate, $query);
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (trim($candidate) !== '') {
                return $this->snippet($candidate, $query);
            }
        }

        return '';
    }

    /** @return list<string> */
    private function terms(string $query): array
    {
        return collect(preg_split('/\s+/', $query))
            ->filter(fn ($t) => mb_strlen($t) >= 3)
            ->values()
            ->all();
    }

    /**
     * A snippet around the first match, with the searched terms marked.
     *
     * Escaped before the marks are inserted, so a result cannot inject
     * markup through a title.
     */
    private function snippet(string $text, string $query): string
    {
        // Tags are stripped first, then the remainder is escaped, then the
        // marks are inserted. A record's own text can therefore never carry
        // markup into the results page.
        $text = trim(strip_tags($text));

        if ($text === '') {
            return '';
        }

        $terms = collect($this->terms($query));

        $position = 0;
        foreach ($terms as $term) {
            $found = mb_stripos($text, $term);
            if ($found !== false) {
                $position = max(0, $found - 60);
                break;
            }
        }

        $window = Str::limit(mb_substr($text, $position), 200);
        $escaped = e($position > 0 ? '…'.$window : $window);

        foreach ($terms as $term) {
            $escaped = preg_replace(
                '/('.preg_quote(e($term), '/').')/iu',
                '<mark>$1</mark>',
                $escaped
            );
        }

        return $escaped;
    }

    /**
     * Facet counts from the result set itself, so a facet never offers a
     * filter that would return nothing.
     *
     * @param  Collection<int, SearchHit>  $hits
     * @return array<string, array<string, int>>
     */
    private function facetsFor(Collection $hits): array
    {
        return [
            'type' => $hits->countBy(fn (SearchHit $h) => $h->type)->all(),
            'country' => $hits->filter(fn (SearchHit $h) => $h->country !== null)
                ->countBy(fn (SearchHit $h) => $h->country)->all(),
            'sector' => $hits->filter(fn (SearchHit $h) => $h->sector !== null)
                ->countBy(fn (SearchHit $h) => $h->sector)->all(),
            'year' => $hits->filter(fn (SearchHit $h) => $h->year !== null)
                ->countBy(fn (SearchHit $h) => (string) $h->year)
                ->sortKeysDesc()->all(),
        ];
    }
}
