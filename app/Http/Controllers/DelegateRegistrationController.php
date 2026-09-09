<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDelegateRegistrationRequest;
use App\Mail\DelegateRegistrationFiled;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
            'countries' => Country::orderBy('name')->get(),
            /*
             * Only a signed-in delegate's own filing is prefilled. Looking
             * one up by email would let anybody type an address and read
             * back another delegate's flight number.
             */
            'existing' => auth()->check()
                ? DelegateRegistration::where('meeting_id', $meeting->id)
                    ->where('user_id', auth()->id())
                    ->first()
                : null,
        ]);
    }

    public function store(StoreDelegateRegistrationRequest $request, Meeting $meeting): RedirectResponse
    {
        abort_if($meeting->starts_on->isPast(), 410, __('delegates.closed'));

        $data = $request->safe()->except('website');
        $user = $request->user();

        /*
         * The unique key is the meeting and the email, so filing again
         * amends rather than duplicating. That is what the confirmation
         * tells the delegate to do: there is no edit link to send them,
         * because a link that opens somebody's travel details is a link
         * that opens somebody's travel details.
         *
         * A signed-in delegate's country comes from their account and
         * overrides what the form said; a delegate without one chooses it.
         */
        $registration = DelegateRegistration::updateOrCreate(
            ['meeting_id' => $meeting->id, 'delegate_email' => $data['delegate_email']],
            array_merge($data, [
                'user_id' => $user?->id,
                'country_id' => $user?->country_id ?? ($data['country_id'] ?? null),
                'status' => 'filed',
            ])
        );

        /*
         * Stored first, then confirmed. If the mail fails the delegate is
         * still on the roster, which is the thing the Secretariat needs.
         */
        try {
            Mail::to($registration->delegate_email)
                ->queue(new DelegateRegistrationFiled($registration->fresh(['meeting', 'country'])));
        } catch (Throwable $e) {
            Log::error('A delegate registration was stored but its confirmation could not be queued.', [
                'registration' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug])
            ->with('status', __('delegates.filed', ['name' => $registration->delegate_name]));
    }
}
