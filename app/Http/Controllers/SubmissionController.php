<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Indicator;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\SubmissionStateChanged;
use App\Services\SubmissionWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The e-Form, RP-09, against screen 06.
 *
 * A member state representative fills in their own country's return for a
 * period. Everything they can reach is their own: the country scope holds
 * the reads and the policy holds the writes, and neither is a controller
 * check that somebody has to remember.
 */
class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionWorkflow $workflow) {}

    /** Every return this account may see. For a representative, their own. */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Submission::class);

        return view('pages.submissions.index', [
            'submissions' => Submission::query()
                ->with(['country', 'author', 'values'])
                ->orderByDesc('period')
                ->orderBy('country_id')
                ->paginate(25),
            'periods' => $this->periods(),
            'canOpen' => Gate::allows('create', Submission::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Submission::class);

        $data = $request->validate([
            'period' => ['required', 'string', 'max:20', 'regex:/^\d{4}(-Q[1-4])?$/'],
        ]);

        $user = $request->user();

        abort_if($user->country_id === null, 403, __('submissions.no_country'));

        $submission = $this->workflow->open($user->country_id, $data['period'], $user);

        return redirect()
            ->route('submissions.edit', ['locale' => app()->getLocale(), 'submission' => $submission->id])
            ->with('status', __('submissions.opened', ['period' => $submission->period]));
    }

    public function edit(Submission $submission): View
    {
        Gate::authorize('view', $submission);

        $submission->load(['values.indicator', 'comments.indicator', 'comments.author', 'events.actor', 'country']);

        return view('pages.submissions.edit', [
            'submission' => $submission,
            'categories' => Indicator::active()->orderBy('display_order')->get()->groupBy('category'),
            'values' => $submission->values->keyBy('indicator_id'),
            'comments' => $submission->openComments(),
            'editable' => Gate::allows('update', $submission),
        ]);
    }

    /**
     * Saving the figures.
     *
     * Validated on the server against each indicator's own data_type, so a
     * text field cannot carry a word into a numeric indicator whatever the
     * form sends.
     */
    public function update(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('update', $submission);

        $indicators = Indicator::active()->get()->keyBy('id');
        $rules = [];

        foreach ($indicators as $indicator) {
            $rules += $this->workflow->rulesFor($indicator, 'values.'.$indicator->id);
        }

        $data = $request->validate($rules);

        foreach ($data['values'] ?? [] as $indicatorId => $field) {
            $indicator = $indicators->get((int) $indicatorId);

            if ($indicator === null) {
                continue;
            }

            $numeric = in_array($indicator->data_type, ['numeric', 'percentage', 'currency', 'boolean', 'year'], true);
            $value = $field['value'] ?? null;

            $submission->values()->updateOrCreate(
                ['indicator_id' => $indicator->id],
                [
                    'value_numeric' => $numeric && $value !== null && $value !== '' ? $value : null,
                    'value_text' => $numeric ? null : ($value ?: null),
                    'currency' => $field['currency'] ?? null,
                    'source_note' => $field['source_note'] ?? null,
                ]
            );
        }

        return back()->with('status', __('submissions.saved'));
    }

    /** Sending the return to the Secretariat. */
    public function submit(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('submit', $submission);

        $submission->load('values.indicator');

        $this->workflow->transition($submission, SubmissionStatus::Submitted, $request->user());

        // RP-05, regulatory content only. Notifying the Secretariat that a
        // member state has filed is about the data, not about an event.
        $this->notifySecretariat($submission);

        return back()->with('status', __('submissions.submitted'));
    }

    /** @return list<string> */
    private function periods(): array
    {
        $year = now()->year;

        return [(string) $year, (string) ($year - 1), (string) ($year - 2)];
    }

    private function notifySecretariat(Submission $submission): void
    {
        User::query()
            ->whereIn('role', ['administrator', 'editor'])
            ->where('status', 'active')
            ->get()
            ->each(fn ($user) => $user->notify(new SubmissionStateChanged($submission)));
    }
}
