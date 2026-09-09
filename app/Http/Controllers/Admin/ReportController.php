<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Report;
use App\Services\ReportBuilder;
use App\Services\ReportDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Regional briefs, RP-12. Generate, view, store and print.
 *
 * One click on a member state or on an indicator. Stored, because a brief
 * that only exists in the tab that made it cannot be found again, and the
 * question six months later is always "where is the one we sent in March".
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportBuilder $builder,
        private readonly ReportDocument $document,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Report::class);

        $user = $request->user();

        /*
         * A conditional holder is offered their own member state and no
         * regional indicator briefs, because an indicator brief is the
         * figure across all fourteen states and that is exactly what the
         * condition withholds. The policy refuses the same thing on the
         * write path; this only keeps the form honest.
         */
        $conditional = $user->permissionIsConditional(Permission::ExportReports);

        return view('pages.admin.reports.index', [
            'reports' => Report::with('author')->latest()->get()
                ->filter(fn (Report $report) => Gate::allows('view', $report))
                ->values(),
            'countries' => Country::orderBy('name')
                ->when($conditional, fn ($q) => $q->whereKey($user->country_id))
                ->get(),
            'indicators' => $conditional ? collect() : Indicator::active()->orderBy('display_order')->get(),
            'periods' => $this->periods(),
            'conditional' => $conditional,
        ]);
    }

    /** One click. The brief is generated and stored in the same action. */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Report::class);

        $data = $request->validate([
            'subject_type' => ['required', Rule::in([Report::COUNTRY, Report::INDICATOR])],
            'subject_key' => ['required', 'string', 'max:120'],
            'period' => ['nullable', 'string', 'max:20'],
        ]);

        // The conditional grant, enforced on the write path rather than
        // only hidden in the form.
        abort_unless(
            Gate::forUser($request->user())->allows('onSubject', [Report::class, $data['subject_type'], $data['subject_key']]),
            403
        );

        $title = $this->titleFor($data['subject_type'], $data['subject_key']);

        abort_if($title === null, 422, __('reports.unknown_subject'));

        $report = Report::create([
            'subject_type' => $data['subject_type'],
            'subject_key' => $data['subject_key'],
            'title' => $title,
            'period' => ($data['period'] ?? null) ?: null,
            'locale' => app()->getLocale(),
            'generated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.reports.show', ['locale' => app()->getLocale(), 'report' => $report->id])
            ->with('status', __('reports.generated'));
    }

    /**
     * Viewing one.
     *
     * Regenerated from the current figures rather than replayed from a
     * stored copy, which is what anyone reopening it wants. The exported
     * PDF beside it is the copy that was circulated, and the two being
     * different is the point.
     */
    public function show(Report $report): View
    {
        Gate::authorize('view', $report);

        return view('pages.admin.reports.show', [
            'report' => $report,
            'compiled' => $this->builder->compile($report),
            'range' => $this->builder->range($this->builder->compile($report)['rows']),
        ]);
    }

    /**
     * The PDF, stored so the circulated copy can be found again.
     *
     * Stored on the private disk: a brief may carry figures a member state
     * has not yet published, and a public path would be a URL somebody
     * could guess.
     */
    public function pdf(Report $report): Response
    {
        Gate::authorize('view', $report);

        $pdf = $this->document->pdf($report);
        $path = 'reports/'.$report->id.'/'.$this->document->filename($report);

        Storage::disk('local')->put($path, $pdf);

        $report->update(['pdf_path' => $path, 'exported_at' => now()]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->document->filename($report).'"',
        ]);
    }

    public function destroy(Report $report): RedirectResponse
    {
        Gate::authorize('delete', $report);

        if ($report->pdf_path) {
            Storage::disk('local')->delete($report->pdf_path);
        }

        $report->delete();

        return redirect()
            ->route('admin.reports', ['locale' => app()->getLocale()])
            ->with('status', __('reports.deleted'));
    }

    private function titleFor(string $type, string $key): ?string
    {
        if ($type === Report::COUNTRY) {
            $country = Country::where('iso_code', $key)->first();

            return $country ? __('reports.title_country', ['country' => $country->name]) : null;
        }

        $indicator = Indicator::where('slug', $key)->first();

        return $indicator ? __('reports.title_indicator', ['indicator' => $indicator->name]) : null;
    }

    /** @return list<string> */
    private function periods(): array
    {
        return IndicatorValue::query()
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->all();
    }
}
