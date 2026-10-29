<?php

namespace App\Http\Controllers;

use App\Enums\Clearance;
use App\Models\Meeting;
use App\Services\CalendarFeed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

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

    /**
     * One public event as an iCalendar file, for "Add to my calendar".
     *
     * Public, dated meetings only: a committee meeting's date is for the
     * member states that sit on it, and a meeting with no stated date has
     * nothing to put in a calendar.
     */
    public function ics(Meeting $meeting, CalendarFeed $feed): Response
    {
        abort_unless($meeting->clearance === Clearance::Public && ! $meeting->date_unknown && $meeting->starts_on, 404);

        $url = route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]);

        return response($feed->single($meeting->load('committee'), $url), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="crasa-'.$meeting->slug.'.ics"',
        ]);
    }
}
