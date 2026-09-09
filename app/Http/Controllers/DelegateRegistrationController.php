<?php

namespace App\Http\Controllers;

use App\Models\DelegateRegistration;
use App\Models\Meeting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Delegate registration, WS-07, screen 18.
 *
 * A delegate files their arrival and departure against a meeting. That is
 * all this does.
 *
 * WHAT IT DELIBERATELY DOES NOT DO, and why the list is here rather than
 * only in a commit message: accommodation, nights, dietary requirements,
 * transfer scheduling, driver allocation, reminders, chase emails and
 * post-event feedback. Screen 18 draws several of those. Each one is the
 * managing half rather than the collecting half, and the division is a
 * separate commercial agreement rather than a design preference.
 *
 * If a method appears here that schedules, allocates, reminds or books,
 * that line has been crossed.
 */
class DelegateRegistrationController extends Controller
{
    public function create(Meeting $meeting): View
    {
        abort_if($meeting->starts_on->isPast(), 410, __('delegates.closed'));

        return view('pages.events.register', [
            'meeting' => $meeting,
            'existing' => DelegateRegistration::where('meeting_id', $meeting->id)
                ->where('user_id', auth()->id())
                ->first(),
        ]);
    }

    public function store(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_if($meeting->starts_on->isPast(), 410, __('delegates.closed'));

        $data = $request->validate([
            'delegate_name' => ['required', 'string', 'max:160'],
            'delegate_email' => ['required', 'email', 'max:160'],
            'delegate_role' => ['nullable', 'string', 'max:120'],

            'arrival_point' => ['nullable', 'string', 'max:120'],
            'arrival_on' => ['required', 'date'],
            'arrival_at' => ['nullable', 'date_format:H:i'],
            'arrival_reference' => ['nullable', 'string', 'max:60'],

            // A departure before an arrival is a typo somebody should be
            // told about now rather than at the airport.
            'departure_on' => ['required', 'date', 'after_or_equal:arrival_on'],
            'departure_at' => ['nullable', 'date_format:H:i'],
            'departure_reference' => ['nullable', 'string', 'max:60'],

            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $registration = DelegateRegistration::updateOrCreate(
            ['meeting_id' => $meeting->id, 'delegate_email' => $data['delegate_email']],
            $data + [
                'user_id' => $user?->id,
                'country_id' => $user?->country_id,
                'status' => 'filed',
            ]
        );

        return redirect()
            ->route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug])
            ->with('status', __('delegates.filed', ['name' => $registration->delegate_name]));
    }
}
