<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContributionState;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Services\ContributionWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The Secretariat reviewing a member state's contribution. RP-06.
 *
 * THE SAME QUEUE AS DATA RETURNS, because it is the same job done by the
 * same three people: read what a member state sent, decide whether it is
 * right, and either accept it or send it back saying why. A second review
 * screen with a different shape would mean two places to look every
 * morning.
 */
class ContributionReviewController extends Controller
{
    public function __construct(private readonly ContributionWorkflow $workflow) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission(Permission::ValidateSubmission), 403);

        return view('pages.admin.contributions.index', [
            'awaiting' => Instrument::awaitingReview()
                ->with(['translations', 'country', 'sector', 'contributor'])
                ->orderBy('submitted_at')
                ->get(),
            'recent' => Instrument::withContributor()
                ->whereIn('contribution_state', [ContributionState::Validated->value, ContributionState::Returned->value])
                ->with(['translations', 'country', 'sector', 'reviewer'])
                ->orderByDesc('reviewed_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function show(Request $request, Instrument $instrument): View
    {
        abort_unless($request->user()->hasPermission(Permission::ValidateSubmission), 403);
        abort_if($instrument->contribution_state === null, 404);

        return view('pages.admin.contributions.show', [
            'instrument' => $instrument->load(['translations', 'country', 'sector', 'contributor', 'documents', 'versions']),
        ]);
    }

    public function validateContribution(Request $request, Instrument $instrument): RedirectResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ValidateSubmission), 403);

        $this->workflow->transition($instrument, ContributionState::Validated, $request->user());

        return redirect()
            ->route('admin.contributions', ['locale' => app()->getLocale()])
            ->with('status', __('contributions.validated_notice'));
    }

    public function returnContribution(Request $request, Instrument $instrument): RedirectResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ValidateSubmission), 403);

        // Returning without saying why makes the contributor guess, and
        // guessing is how a contribution comes back with the same fault.
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);

        $this->workflow->transition($instrument, ContributionState::Returned, $request->user(), $data['review_note']);

        return redirect()
            ->route('admin.contributions', ['locale' => app()->getLocale()])
            ->with('status', __('contributions.returned_notice'));
    }
}
