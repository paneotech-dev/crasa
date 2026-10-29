<?php

namespace App\Http\Controllers;

use App\Enums\Clearance;
use App\Events\RegistrationReceived;
use App\Http\Requests\StoreDelegateRegistrationRequest;
use App\Mail\DelegateRegistrationFiled;
use App\Models\Authority;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
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
    public function create(Meeting $meeting): View|RedirectResponse
    {
        if ($redirect = $this->gate($meeting)) {
            return $redirect;
        }

        $existing = auth()->check()
            ? DelegateRegistration::where('meeting_id', $meeting->id)
                ->where('user_id', auth()->id())
                ->first()
            : null;

        // Confirmed by the Secretariat: no longer edited here.
        if ($existing && $existing->status === 'confirmed') {
            return redirect()->route('events.register.done', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]);
        }

        return view('pages.events.register', [
            'meeting' => $meeting,
            'countries' => Country::orderBy('name')->get(),
            'authorities' => Authority::withoutGlobalScopes()->with('country')->orderBy('acronym')->get(),
            /*
             * Only a signed-in delegate's own filing is prefilled. Looking
             * one up by email would let anybody type an address and read
             * back another delegate's flight number.
             */
            'existing' => $existing,
        ]);
    }

    /**
     * The form is open, and the reader may see the meeting. A members-only
     * meeting sends a signed-out reader to sign in and back here.
     */
    private function gate(Meeting $meeting): ?RedirectResponse
    {
        if ($meeting->clearance !== Clearance::Public && ! auth()->check()) {
            return redirect()->guest(route('login', ['locale' => app()->getLocale()]));
        }

        abort_unless(Gate::allows('view', $meeting), 404);
        abort_unless($meeting->registrationIsOpen(), 410, __('delegates.closed'));

        return null;
    }

    /**
     * After sending: what was filed, what happens next, and the way back to
     * the form while the Secretariat has not confirmed it. Shows the
     * signed-in delegate's own filing, or the one this browser just sent.
     */
    public function done(Meeting $meeting): View
    {
        abort_unless(Gate::allows('view', $meeting), 404);

        $registration = auth()->check()
            ? DelegateRegistration::where('meeting_id', $meeting->id)->where('user_id', auth()->id())->first()
            : null;

        if (! $registration && ($id = session('registration_done.'.$meeting->id))) {
            $registration = DelegateRegistration::withoutGlobalScopes()->where('meeting_id', $meeting->id)->find($id);
        }

        abort_if($registration === null, 404);

        return view('pages.events.register-done', [
            'meeting' => $meeting,
            'registration' => $registration->load(['country', 'authority']),
        ]);
    }

    public function store(StoreDelegateRegistrationRequest $request, Meeting $meeting): RedirectResponse
    {
        if ($redirect = $this->gate($meeting)) {
            return $redirect;
        }

        $data = $request->safe()->except('website');
        $user = $request->user();

        // A filing the Secretariat has confirmed is settled; a change goes
        // through the Secretariat rather than over the top of it.
        $confirmed = DelegateRegistration::withoutGlobalScopes()->where('meeting_id', $meeting->id)
            ->where('delegate_email', $data['delegate_email'])->where('status', 'confirmed')->exists();
        if ($confirmed) {
            return back()->withInput()->withErrors(['delegate_email' => __('d3_people.reg_already_confirmed')]);
        }

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
                'authority_id' => $data['authority_id'] ?? $user?->authority_id,
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

        event(new RegistrationReceived($registration));

        session()->put('registration_done.'.$meeting->id, $registration->id);

        return redirect()
            ->route('events.register.done', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug])
            ->with('status', __('delegates.filed', ['name' => $registration->delegate_name]));
    }
}
