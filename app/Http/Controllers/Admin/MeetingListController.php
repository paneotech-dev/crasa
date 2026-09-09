<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Which meetings have a delegate roster, and how full each one is.
 *
 * The roster and its export are per meeting and were reachable only by
 * knowing a meeting's slug. This is the way in: upcoming first, because a
 * roster matters before the meeting rather than after it.
 *
 * Reading only. Nothing here creates or edits a meeting: the website
 * displays and the Event Management Platform manages.
 */
class MeetingListController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permission::ManageMeetings->value);

        return view('pages.admin.delegates.meetings', [
            'upcoming' => Meeting::upcoming()->withCount('delegateRegistrations')->with('committee')->get(),
            'past' => Meeting::past()->withCount('delegateRegistrations')->with('committee')->limit(20)->get(),
        ]);
    }
}
