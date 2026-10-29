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
 * The events list in the back office (E4): New event, and per row Edit,
 * View on the site, Delegates and Delete with a confirmation.
 */
class MeetingListController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permission::ManageMeetings->value);

        return view('pages.admin.events.index', [
            'upcoming' => Meeting::upcoming()->withCount('delegateRegistrations')->with('committee')->get(),
            'past' => Meeting::past()->withCount('delegateRegistrations')->with('committee')->get(),
        ]);
    }
}
