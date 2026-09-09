<?php

namespace App\Http\Controllers;

use App\Enums\PublicationType;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Publication;
use App\Models\Sector;
use App\Support\Locales;
use Illuminate\Http\Request;

/**
 * WS-05, screen 07.
 *
 * Facet counts come from one grouped query per facet rather than a query per
 * option, so adding a fifteenth member state does not add fifteen queries.
 *
 * Each facet's counts are computed with the OTHER filters applied but not
 * its own. That is what makes the number honest: it states what a reader
 * would get if they clicked, rather than how many exist in total. A "South
 * Africa (7)" that yields two results once the year filter is taken into
 * account is worse than no number.
 */
class PublicationController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'type' => $request->string('type')->value() ?: null,
            // Screen 07 draws Sector first among its facets and the build
            // had none at all, although every publication carries a
            // sector_id.
            'sector' => $request->string('sector')->value() ?: null,
            'country' => $request->string('country')->value() ?: null,
            'committee' => $request->string('committee')->value() ?: null,
            'year' => $request->integer('year') ?: null,
            /*
             * "Available in", screen 07's fifth facet.
             *
             * This is a trilingual obligation rather than a convenience. A
             * regulator who works in French needs to filter the library to
             * what exists in French, and telling them a document is
             * available only in English is more useful than letting them
             * open it and find out.
             *
             * It filters on the attached documents' language, not on the
             * record's translations: the title may be translated while the
             * PDF is not, and it is the PDF the reader came for.
             */
            'language' => $request->string('language')->value() ?: null,
        ];

        $publications = $this->query($filters)
            ->with(['translations', 'sector', 'committee', 'country', 'documents', 'supersededBy'])
            ->orderByDesc('published_on')
            ->paginate(12)
            ->withQueryString();

        return view('pages.publications.index', [
            'publications' => $publications,
            'filters' => $filters,
            'counts' => $this->facetCounts($filters),
            'types' => PublicationType::cases(),
            'sectors' => Sector::orderBy('name')->get(),
            'countries' => Country::orderBy('name')->get(),
            'committees' => Committee::orderBy('name')->get(),
            'languages' => Locales::SUPPORTED,
            // Derived in PHP rather than with a raw YEAR(), which exists in
            // MariaDB and not in SQLite. The distinct year count is small,
            // and whereYear() below is Laravel's portable helper.
            'years' => Publication::listed()
                ->orderByDesc('published_on')
                ->pluck('published_on')
                ->map(fn ($date) => (int) $date->format('Y'))
                ->unique()
                ->values(),
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
     * How many publications each facet option would return.
     *
     * One grouped query per facet. Options that would return nothing keep
     * their place in the list and are shown as unavailable rather than
     * removed: a reader who cannot see that Comoros has no publications
     * cannot tell whether it has none or whether the filter is broken. The
     * shape of the data is part of the answer.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, int>>
     */
    private function facetCounts(array $filters): array
    {
        $without = fn (string $key) => $this->query(array_merge($filters, [$key => null]));

        return [
            'type' => $without('type')
                ->selectRaw('publication_type, COUNT(*) as total')
                ->groupBy('publication_type')
                ->pluck('total', 'publication_type')
                ->map(fn ($total) => (int) $total)
                ->all(),

            'sector' => $without('sector')
                ->join('sectors', 'sectors.id', '=', 'publications.sector_id')
                ->selectRaw('sectors.slug, COUNT(*) as total')
                ->groupBy('sectors.slug')
                ->pluck('total', 'slug')
                ->map(fn ($total) => (int) $total)
                ->all(),

            'language' => $without('language')
                ->join('documents', function ($join) {
                    $join->on('documents.documentable_id', '=', 'publications.id')
                        ->where('documents.documentable_type', '=', (new Publication)->getMorphClass());
                })
                ->selectRaw('documents.locale, COUNT(DISTINCT publications.id) as total')
                ->groupBy('documents.locale')
                ->pluck('total', 'locale')
                ->map(fn ($total) => (int) $total)
                ->all(),

            'country' => $without('country')
                ->join('countries', 'countries.id', '=', 'publications.country_id')
                ->selectRaw('countries.iso_code, COUNT(*) as total')
                ->groupBy('countries.iso_code')
                ->pluck('total', 'iso_code')
                ->map(fn ($total) => (int) $total)
                ->all(),

            'committee' => $without('committee')
                ->join('committees', 'committees.id', '=', 'publications.committee_id')
                ->selectRaw('committees.slug, COUNT(*) as total')
                ->groupBy('committees.slug')
                ->pluck('total', 'slug')
                ->map(fn ($total) => (int) $total)
                ->all(),

            // Grouped in PHP rather than with YEAR(), which MariaDB has and
            // SQLite does not. The candidate set is one row per publication
            // and the page already loads them.
            'year' => $without('year')
                ->pluck('published_on')
                ->countBy(fn ($date) => (string) $date->format('Y'))
                ->all(),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function query(array $filters)
    {
        return Publication::listed()
            ->when($filters['type'], fn ($q, $v) => $q->where('publication_type', $v))
            ->when($filters['sector'], fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->where('slug', $v)))
            ->when($filters['country'], fn ($q, $v) => $q->whereHas('country', fn ($c) => $c->where('iso_code', $v)))
            ->when($filters['committee'], fn ($q, $v) => $q->whereHas('committee', fn ($c) => $c->where('slug', $v)))
            ->when($filters['year'], fn ($q, $v) => $q->whereYear('published_on', $v))
            ->when($filters['language'], fn ($q, $v) => $q->whereHas('documents', fn ($d) => $d->where('locale', $v)));
    }
}
