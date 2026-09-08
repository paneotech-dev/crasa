<?php

namespace App\Http\Controllers;

use App\Enums\PublicationType;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Publication;
use Illuminate\Http\Request;

/**
 * WS-05, screen 07.
 *
 * Facet counts come from one grouped query per facet rather than a query per
 * option, so adding a fifteenth member state does not add fifteen queries.
 */
class PublicationController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'type' => $request->string('type')->value() ?: null,
            'country' => $request->string('country')->value() ?: null,
            'committee' => $request->string('committee')->value() ?: null,
            'year' => $request->integer('year') ?: null,
        ];

        $publications = $this->query($filters)
            ->with(['translations', 'sector', 'committee', 'country', 'documents', 'supersededBy'])
            ->orderByDesc('published_on')
            ->paginate(12)
            ->withQueryString();

        return view('pages.publications.index', [
            'publications' => $publications,
            'filters' => $filters,
            'types' => PublicationType::cases(),
            'countries' => Country::orderBy('name')->get(),
            'committees' => Committee::orderBy('name')->get(),
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

    /** @param array<string, mixed> $filters */
    private function query(array $filters)
    {
        return Publication::listed()
            ->when($filters['type'], fn ($q, $v) => $q->where('publication_type', $v))
            ->when($filters['country'], fn ($q, $v) => $q->whereHas('country', fn ($c) => $c->where('iso_code', $v)))
            ->when($filters['committee'], fn ($q, $v) => $q->whereHas('committee', fn ($c) => $c->where('slug', $v)))
            ->when($filters['year'], fn ($q, $v) => $q->whereYear('published_on', $v));
    }
}
