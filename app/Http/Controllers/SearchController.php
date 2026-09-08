<?php

namespace App\Http\Controllers;

use App\Search\SearchDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WS-04. No design screen exists, so the results page is built from the
 * foundations: the publications library's facet chips, and screen 07's
 * document row for results.
 */
class SearchController extends Controller
{
    public function __construct(private SearchDriver $search) {}

    public function index(Request $request)
    {
        $query = trim($request->string('q')->value());

        $filters = [
            'country' => $request->string('country')->value() ?: null,
            'sector' => $request->string('sector')->value() ?: null,
            'type' => $request->string('type')->value() ?: null,
            'year' => $request->integer('year') ?: null,
        ];

        $results = $this->search->search(
            $query,
            $filters,
            (int) config('crasa.search.per_page', 12),
            max(1, $request->integer('page', 1))
        );

        return view('pages.search.index', [
            'query' => $query,
            'filters' => $filters,
            'results' => $results,
            'driver' => $this->search->name(),
        ]);
    }

    /** Predictive suggestions. Titles only. */
    public function suggest(Request $request): JsonResponse
    {
        return response()->json([
            'suggestions' => $this->search->suggest(trim($request->string('q')->value())),
        ]);
    }
}
