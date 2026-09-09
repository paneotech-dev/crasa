<?php

namespace App\Http\Controllers;

use App\Enums\Clearance;
use App\Models\Meeting;
use Illuminate\Contracts\View\View;

/**
 * The public events section, WS-07.
 *
 * READ ONLY, AND THAT IS THE FEATURE. Upcoming and past meetings, with a
 * link out to wherever a delegate registers. The website displays; the
 * Event Management Platform manages. Nothing here creates, edits or
 * schedules an event, and there is no route that could.
 */
class EventsController extends Controller
{
    public function index(): View
    {
        return view('pages.events.index', [
            'upcoming' => Meeting::public()->upcoming()->with('committee')->get(),
            'past' => Meeting::public()->past()->with('committee')->limit(12)->get(),
        ]);
    }

    public function show(Meeting $meeting): View
    {
        abort_unless($meeting->clearance === Clearance::Public, 404);

        return view('pages.events.show', ['meeting' => $meeting->load('committee')]);
    }
}
