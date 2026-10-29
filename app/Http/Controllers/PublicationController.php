<?php

namespace App\Http\Controllers;

use App\Enums\PublicationType;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Publication;
use App\Models\Sector;
use App\Support\Facets;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * WS-05, screen 07.
 *
 * THE FACETS ARE A SIDEBAR AND EVERY ONE HOLDS A SET. Screen 07 line 115
 * draws an aside at flex:1 1 270px beside a main at flex:999 1 360px, and
 * every facet inside it is a list of checkboxes with a count on the right.
 * A checkbox holds a set, so a facet takes ?type[]=guideline&type[]=study.
 * A scalar ?type=guideline still resolves, because links written before
 * 9 September 2026 must keep working.
 *
 * EVERY COUNT IS THE SIZE OF THE RESULT THE CLICK WOULD PRODUCE. Not how
 * many exist in total, and not how many match that option in isolation. A
 * "South Africa (7)" that yields two results once the year filter is taken
 * into account is worse than no number at all.
 *
 * That costs one grouped query per facet in the ordinary case, where the
 * facet has nothing selected: the group counts are then exactly the sizes
 * the clicks would produce. It costs one count per option in the case where
 * the facet already has a selection, because a click there changes a set
 * rather than defining one, and no single grouped query can answer it.
 * Facets::values caps the selection so a crafted query string cannot turn
 * one page load into a thousand counts.
 */
class PublicationController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'type' => Facets::values($request, 'type'),
            // Screen 07 draws Sector first among its facets.
            'sector' => Facets::values($request, 'sector'),
            'country' => Facets::values($request, 'country'),
            'committee' => Facets::values($request, 'committee'),
            'year' => Facets::values($request, 'year'),
            /*
             * "Available in", screen 07's fifth facet.
             *
             * A trilingual obligation rather than a convenience. A regulator
             * who works in French needs to filter the library to what exists
             * in French, and telling them a document is available only in
             * English is more useful than letting them open it and find out.
             *
             * It filters on the attached documents' language, not on the
             * record's translations: the title may be translated while the
             * PDF is not, and it is the PDF the reader came for.
             */
            'language' => Facets::values($request, 'language'),
        ];

        $types = collect(PublicationType::cases());
        $sectors = Sector::orderBy('name')->get();
        $countries = Country::orderBy('name')->get();
        $committees = Committee::orderBy('name')->get();
        $years = Publication::listed()
            ->orderByDesc('published_on')
            ->pluck('published_on')
            // Only real years: an undated publication has none (PE-T09).
            ->filter()
            // Derived in PHP rather than with a raw YEAR(), which exists in
            // MariaDB and not in SQLite.
            ->map(fn ($date) => (string) $date->format('Y'))
            ->unique()
            ->values();

        $options = [
            'type' => $types->map(fn ($t) => (string) $t->value)->all(),
            'sector' => $sectors->pluck('slug')->all(),
            'country' => $countries->pluck('iso_code')->all(),
            'committee' => $committees->pluck('slug')->all(),
            'language' => Locales::SUPPORTED,
            'year' => $years->all(),
        ];

        $publications = $this->query($filters)
            ->with(['translations', 'sector', 'committee', 'country', 'documents', 'supersededBy'])
            ->orderByDesc('published_on')
            ->paginate(12)
            ->withQueryString();

        return view('pages.publications.index', [
            'publications' => $publications,
            'filters' => $filters,
            'counts' => $this->facetCounts($filters, $options),
            'total' => \App\Support\PublishedCounts::publications(),
            'types' => $types,
            'sectors' => $sectors,
            'countries' => $countries,
            'committees' => $committees,
            'languages' => Locales::SUPPORTED,
            'years' => $years,
        ]);
    }

    public function show(string $slug)
    {
        $publication = Publication::listed()
            ->where('slug', $slug)
            ->with(['translations', 'sector', 'committee', 'country', 'documents', 'supersedes', 'supersededBy'])
            ->first();

        abort_if($publication === null, 404);

        return view('pages.publications.show', [
            'publication' => $publication,
            'lineage' => $publication->lineage(),
        ]);
    }

    /**
     * How many publications each facet option would return if it were
     * clicked. See the class note: grouped when the facet is empty, one
     * count per option when it is not.
     *
     * @param  array<string, list<string>>  $filters
     * @param  array<string, list<string>>  $options
     * @return array<string, array<string, int>>
     */
    private function facetCounts(array $filters, array $options): array
    {
        $grouped = [
            'type' => fn (Builder $q) => $q
                ->selectRaw('publication_type, COUNT(*) as total')
                ->groupBy('publication_type')
                ->pluck('total', 'publication_type'),

            'sector' => fn (Builder $q) => $q
                ->join('sectors', 'sectors.id', '=', 'publications.sector_id')
                ->selectRaw('sectors.slug, COUNT(*) as total')
                ->groupBy('sectors.slug')
                ->pluck('total', 'slug'),

            'country' => fn (Builder $q) => $q
                ->join('countries', 'countries.id', '=', 'publications.country_id')
                ->selectRaw('countries.iso_code, COUNT(*) as total')
                ->groupBy('countries.iso_code')
                ->pluck('total', 'iso_code'),

            'committee' => fn (Builder $q) => $q
                ->join('committees', 'committees.id', '=', 'publications.committee_id')
                ->selectRaw('committees.slug, COUNT(*) as total')
                ->groupBy('committees.slug')
                ->pluck('total', 'slug'),

            // Counted distinct: a publication with an English and a French
            // PDF is one publication in each language, not two in either.
            'language' => fn (Builder $q) => $q
                ->join('documents', function ($join) {
                    $join->on('documents.documentable_id', '=', 'publications.id')
                        ->where('documents.documentable_type', '=', (new Publication)->getMorphClass());
                })
                ->selectRaw('documents.locale, COUNT(DISTINCT publications.id) as total')
                ->groupBy('documents.locale')
                ->pluck('total', 'locale'),

            // Grouped in PHP rather than with YEAR(), which MariaDB has and
            // SQLite does not. One row per publication, and the page has
            // already loaded them.
            'year' => fn (Builder $q) => $q
                ->pluck('published_on')
                ->filter()
                ->countBy(fn ($date) => (string) $date->format('Y')),
        ];

        $counts = [];

        foreach ($options as $key => $values) {
            $counts[$key] = $this->countsFor($filters, $key, $values, $grouped[$key]);
        }

        return $counts;
    }

    /**
     * @param  array<string, list<string>>  $filters
     * @param  list<string>  $options
     * @return array<string, int>
     */
    private function countsFor(array $filters, string $key, array $options, callable $grouped): array
    {
        if (($filters[$key] ?? []) === []) {
            return $grouped($this->query(array_merge($filters, [$key => []])))
                ->map(fn ($total) => (int) $total)
                ->all();
        }

        $counts = [];

        foreach ($options as $option) {
            $option = (string) $option;

            $next = in_array($option, $filters[$key], true)
                ? array_values(array_diff($filters[$key], [$option]))
                : [...$filters[$key], $option];

            $counts[$option] = $this->query(array_merge($filters, [$key => $next]))->count();
        }

        return $counts;
    }

    /**
     * @param  array<string, list<string>>  $filters
     * @return Builder<Publication>
     */
    private function query(array $filters): Builder
    {
        return Publication::listed()
            ->when($filters['type'], fn ($q, $v) => $q->whereIn('publication_type', $v))
            ->when($filters['sector'], fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->whereIn('slug', $v)))
            ->when($filters['country'], fn ($q, $v) => $q->whereHas('country', fn ($c) => $c->whereIn('iso_code', $v)))
            ->when($filters['committee'], fn ($q, $v) => $q->whereHas('committee', fn ($c) => $c->whereIn('slug', $v)))
            ->when($filters['year'], fn ($q, $v) => $q->where(function ($q) use ($v) {
                foreach ($v as $year) {
                    $q->orWhereYear('published_on', (int) $year);
                }
            }))
            ->when($filters['language'], fn ($q, $v) => $q->whereHas('documents', fn ($d) => $d->whereIn('locale', $v)));
    }
}
