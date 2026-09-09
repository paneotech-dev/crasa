<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\Submission;
use App\Services\IndicatorImport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk import and export for a return, RP-08 and BUILD_SPEC 6.2.
 *
 * Two steps: upload and read, then confirm and write. The inspected rows
 * are held in the session between the two rather than written to a staging
 * table, because the pending state belongs to one person's screen for one
 * minute and a table would need cleaning up afterwards.
 */
class SubmissionImportController extends Controller
{
    public function __construct(private readonly IndicatorImport $import) {}

    /** The template, per indicator category or for the whole return. */
    public function template(Request $request): StreamedResponse
    {
        Gate::authorize('create', Submission::class);

        $category = $request->string('category')->toString();
        $categories = Indicator::query()->distinct()->pluck('category')->all();
        $category = in_array($category, $categories, true) ? $category : null;

        $name = 'crasa-'.($category ?: 'all-indicators').'-template.csv';

        return response()->streamDownload(
            fn () => print ($this->import->template($category)),
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function show(Submission $submission): View
    {
        Gate::authorize('update', $submission);

        return view('pages.submissions.import', [
            'submission' => $submission,
            'categories' => Indicator::query()->distinct()->orderBy('category')->pluck('category'),
            'pending' => session('import.'.$submission->id),
        ]);
    }

    /**
     * Read the file and report on it. Nothing is written yet.
     *
     * The error report names the row, the column and the reason, which is
     * the shape CLAUDE.md asks for: an error that says "import failed"
     * makes somebody open the file and guess.
     */
    public function inspect(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('update', $submission);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,text/comma-separated-values', 'max:2048'],
        ]);

        $result = $this->import->inspect($request->file('file'), $submission);

        return back()->with('import.'.$submission->id, [
            'name' => $request->file('file')->getClientOriginalName(),
            'errors' => $result['errors']->all(),
            'rows' => $result['rows']->map(fn (array $row) => [
                'line' => $row['line'],
                'indicator_id' => $row['indicator']->id,
                'indicator' => $row['indicator']->name,
                'numeric' => $row['numeric'],
                'value' => $row['value'],
                'source_note' => $row['source_note'],
            ])->all(),
        ]);
    }

    /**
     * Commit the inspected rows, as one transaction.
     *
     * The file is not re-read: what is written is exactly what was
     * reported on, so nothing can change between the report and the write.
     */
    public function commit(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('update', $submission);

        $pending = $request->session()->pull('import.'.$submission->id);

        abort_if($pending === null, 419, __('import.expired'));

        // A file with any error at all is not committed. Half an import is
        // worse than none, because nobody can tell which half is real.
        if ($pending['errors'] !== []) {
            return back()->withErrors(['file' => __('import.has_errors')]);
        }

        $indicators = Indicator::whereIn('id', array_column($pending['rows'], 'indicator_id'))->get()->keyBy('id');

        $rows = collect($pending['rows'])->map(fn (array $row) => [
            'indicator' => $indicators->get($row['indicator_id']),
            'numeric' => $row['numeric'],
            'value' => $row['value'],
            'source_note' => $row['source_note'],
        ])->filter(fn (array $row) => $row['indicator'] !== null);

        $written = $this->import->commit($submission, $rows);

        return redirect()
            ->route('submissions.edit', ['locale' => app()->getLocale(), 'submission' => $submission->id])
            ->with('status', trans_choice('import.committed', $written, ['count' => $written]));
    }

    public function exportCsv(Submission $submission): StreamedResponse
    {
        Gate::authorize('view', $submission);

        $submission->load('values.indicator', 'country');

        return response()->streamDownload(
            fn () => print ($this->import->exportCsv($submission)),
            $this->filename($submission, 'csv'),
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function exportJson(Submission $submission): Response
    {
        Gate::authorize('view', $submission);

        $submission->load('values.indicator', 'country');

        return response($this->import->exportJson($submission), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($submission, 'json').'"',
        ]);
    }

    private function filename(Submission $submission, string $extension): string
    {
        return 'crasa-'.strtolower((string) $submission->country?->iso_code).'-'.$submission->period.'.'.$extension;
    }
}
