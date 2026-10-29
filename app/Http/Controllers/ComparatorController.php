<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Services\ComparisonExport;
use App\Support\Comparison;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The regulatory comparator, RP-10, against screen 03.
 *
 * PROGRESSIVE ENHANCEMENT, NOT A SINGLE-PAGE APPLICATION. The page renders
 * the whole comparison server-side from its query string, and React takes
 * it over to make selection interactive. Three reasons, all of them from
 * the contract rather than from taste:
 *
 *   The comparison has to be deep-linkable, because regulators paste these
 *   into emails. A URL that resolves to an empty shell until JavaScript
 *   runs is not a permalink.
 *
 *   Screen 03 says the screen "reads correctly as a printed page and under
 *   a screen reader". A print stylesheet cannot rescue a canvas that was
 *   never drawn.
 *
 *   WCAG 2.1 AA is an acceptance criterion, and the surest way to pass it
 *   is for the accessible version to be the one that ships by default.
 *
 * The exports read the same Comparison object as the page, so an exported
 * figure cannot differ from the figure on screen.
 */
class ComparatorController extends Controller
{
    public function __construct(private readonly ComparisonExport $export) {}

    public function index(Request $request): View
    {
        $comparison = Comparison::fromQuery($request->query());

        return view('pages.comparator.index', [
            'comparison' => $comparison,
            'categories' => $this->categories(),
        ]);
    }

    /**
     * The same comparison as JSON, for the React component.
     *
     * It reads this rather than holding its own copy of the data, so the
     * interactive view and the printed page can never drift apart.
     */
    public function data(Request $request): JsonResponse
    {
        $comparison = Comparison::fromQuery($request->query());

        return response()->json($this->export->payload($comparison));
    }

    /** RP-08: export in CSV. Every row carries its source and its period. */
    public function csv(Request $request): StreamedResponse
    {
        $comparison = Comparison::fromQuery($request->query());

        return response()->streamDownload(
            fn () => print ($this->export->csv($comparison)),
            $this->export->filename($comparison, 'csv'),
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /** The same rows as the CSV, as an Excel workbook (D2). */
    public function xlsx(Request $request): Response
    {
        $comparison = Comparison::fromQuery($request->query());

        return response($this->export->xlsx($comparison), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->export->filename($comparison, 'xlsx').'"',
        ]);
    }

    /**
     * BUILD_SPEC 6.3: a branded PNG and PDF carrying source, date and
     * permalink.
     *
     * Rendered as SVG and rasterised by Imagick, which is already on this
     * host for PDF thumbnails. No headless browser and no new dependency:
     * a screenshot service would have been a third thing to run, to secure
     * and to keep in step with the design.
     */
    public function image(Request $request): Response
    {
        $comparison = Comparison::fromQuery($request->query());

        return response($this->export->png($comparison), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$this->export->filename($comparison, 'png').'"',
        ]);
    }

    public function pdf(Request $request): Response
    {
        $comparison = Comparison::fromQuery($request->query());

        return response($this->export->pdf($comparison), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->export->filename($comparison, 'pdf').'"',
        ]);
    }

    /**
     * The indicator taxonomy, grouped as screen 03's sidebar draws it.
     *
     * @return array<string, Collection<int, Indicator>>
     */
    private function categories(): array
    {
        return Indicator::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->groupBy('category')
            ->all();
    }
}
