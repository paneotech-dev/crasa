<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Indicator;
use App\Models\Submission;
use App\Notifications\SubmissionStateChanged;
use App\Services\SubmissionWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The Secretariat's review queue, RP-09.
 *
 * The queue is submissions waiting on the Secretariat, which is the only
 * list anyone in Gaborone actually wants: fourteen member states filing
 * annually is not a volume problem, it is a "whose turn is it" problem.
 */
class SubmissionReviewController extends Controller
{
    public function __construct(private readonly SubmissionWorkflow $workflow) {}

    public function index(Request $request): View
    {
        Gate::authorize(Permission::ValidateSubmission->value);

        $status = $request->string('status')->toString();
        $status = in_array($status, array_column(SubmissionStatus::cases(), 'value'), true) ? $status : null;

        return view('pages.admin.submissions.index', [
            'submissions' => Submission::query()
                ->with(['country', 'author', 'values'])
                ->when($status, fn ($q) => $q->where('status', $status))
                // Waiting on the Secretariat first: that is the queue.
                ->orderByRaw("case when status = 'submitted' then 0 else 1 end")
                ->orderByDesc('submitted_at')
                ->orderByDesc('period')
                ->paginate(25)
                ->withQueryString(),
            'pending' => Submission::where('status', SubmissionStatus::Submitted)->count(),
            'statuses' => SubmissionStatus::cases(),
            'filter' => $status,
        ]);
    }

    public function show(Submission $submission): View
    {
        Gate::authorize(Permission::ValidateSubmission->value);

        $submission->load(['values.indicator', 'comments.indicator', 'comments.author', 'events.actor', 'country', 'author']);

        return view('pages.admin.submissions.show', [
            'submission' => $submission,
            'categories' => Indicator::active()->orderBy('display_order')->get()->groupBy('category'),
            'values' => $submission->values->keyBy('indicator_id'),
            'canReview' => Gate::allows('review', $submission),
        ]);
    }

    /**
     * A comment against one field.
     *
     * Screen 06 attaches comments to fields rather than to the submission,
     * so the representative goes straight to what needs changing rather
     * than re-reading twelve indicators.
     */
    public function comment(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('comment', $submission);

        $data = $request->validate([
            'indicator_id' => ['required', 'exists:indicators,id'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $this->workflow->comment(
            $submission,
            Indicator::findOrFail($data['indicator_id']),
            $request->user(),
            $data['comment'],
        );

        return back()->with('status', __('submissions.comment_added'));
    }

    /** Validate or return. Both write an event row and notify the sender. */
    public function decide(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('review', $submission);

        $data = $request->validate([
            'status' => ['required', Rule::in([SubmissionStatus::Validated->value, SubmissionStatus::Returned->value])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission->load(['values.indicator', 'comments']);

        $this->workflow->transition(
            $submission,
            SubmissionStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null,
        );

        // RP-05. The member state is told what happened to their return,
        // which is regulatory content. Nothing here notifies about events.
        // Client brief item 7: every representative of that member state
        // hears the result, not only the one who filed it.
        $submission->refresh();
        \App\Support\Notifier::send(
            collect([$submission->author])->concat(\App\Support\Notifier::representativesOf($submission->country_id)),
            new SubmissionStateChanged($submission),
            'validation',
            'submission:'.$submission->id.':'.$submission->status->value.':'.$submission->updated_at?->timestamp,
            fn (\App\Models\User $user) => \Illuminate\Support\Facades\Gate::forUser($user)->allows('view', $submission),
        );

        return redirect()
            ->route('admin.submissions.show', ['locale' => app()->getLocale(), 'submission' => $submission->id])
            ->with('status', __('submissions.decided_'.$data['status']));
    }
}
