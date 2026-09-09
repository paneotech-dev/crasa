<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\FeatureInterest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recording that an account would use something that is not built.
 *
 * THE ONLY THING THAT HAPPENS HERE. A row, an audit entry, and a redirect
 * back. No third-party request, no queue, no notification, no address
 * collected. The proposal it belongs to has no input a reader can type
 * into anywhere on the platform.
 *
 * Member state representatives and committee members only, because they
 * are the people the proposal is for and because a count that includes the
 * Secretariat's own staff is a count of the wrong thing.
 */
class FeatureInterestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user !== null && in_array($user->role, [Role::MemberStateRep, Role::CommitteeMember], true),
            403
        );

        $data = $request->validate([
            'feature' => ['required', Rule::in(FeatureInterest::FEATURES)],
        ]);

        // firstOrCreate, so a second click is not a second vote.
        $interest = FeatureInterest::firstOrCreate(
            ['user_id' => $user->id, 'feature' => $data['feature']],
            ['created_at' => now()]
        );

        if ($interest->wasRecentlyCreated) {
            AuditLog::record(AuditLog::CREATED, $interest, ['feature' => $data['feature']], $user);
        }

        return back()->with('status', __('assistant.noted'));
    }
}
