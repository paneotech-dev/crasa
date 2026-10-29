<?php

namespace App\Http\Requests;

use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A delegate filing their own arrival and departure. Screen 18, WS-07.
 *
 * WHAT IS NOT HERE, and the list is here rather than only in a commit
 * message: accommodation, nights required, dietary requirements, visa
 * letters, transfer scheduling, driver allocation, reminders and post-event
 * feedback. Screen 18 draws several of them. Each is the managing half
 * rather than the collecting half, and the division is a separate
 * commercial agreement rather than a design preference. A rule for any of
 * them is the moment this crosses the line, and DelegateBoundaryTest walks
 * the names.
 *
 * No puzzle CAPTCHA, for the same reason the contact form has none: a
 * puzzle a screen reader user cannot solve fails WCAG 2.1 AA and excludes
 * the people most likely to need it. A honeypot and a rate limit do the
 * job, with Cloudflare Turnstile in front of the route when its keys are
 * set (VerifyTurnstile).
 */
class StoreDelegateRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A meeting whose form is closed takes no more filings (Meeting::registrationIsOpen).
     *
     * Here rather than in the controller. A form request validates during
     * resolution, so a controller-level check runs after the rules and a
     * delegate filing against last year's meeting was told their name was
     * required rather than that registration had closed. 410 Gone, because
     * the form was there and is not any more.
     */
    protected function prepareForValidation(): void
    {
        $meeting = $this->route('meeting');

        abort_if(
            $meeting instanceof Meeting && ! $meeting->registrationIsOpen(),
            410,
            __('delegates.closed')
        );
    }

    public function rules(): array
    {
        return [
            'delegate_name' => ['required', 'string', 'max:160'],
            'delegate_email' => ['required', 'string', 'email:rfc', 'max:160'],
            'delegate_role' => ['nullable', 'string', 'max:120'],
            // Which member state the delegate attends for. A delegate with
            // no account has no other way to say so, and the roster is
            // sorted by it.
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            // The regulator the delegate attends for, where they name one.
            'authority_id' => ['nullable', 'integer', 'exists:authorities,id'],

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

            // The honeypot. A person never sees this field, so anything in
            // it came from a bot.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => __('delegates.rejected'),
        ];
    }

    public function attributes(): array
    {
        return [
            'delegate_name' => __('delegates.col_name'),
            'delegate_email' => __('delegates.col_email'),
            'delegate_role' => __('delegates.col_role'),
            'country_id' => __('delegates.col_country'),
            'authority_id' => __('d3_people.authority'),
            'arrival_point' => __('delegates.col_arrival_point'),
            'arrival_on' => __('delegates.col_arrival_on'),
            'arrival_at' => __('delegates.col_arrival_at'),
            'arrival_reference' => __('delegates.col_arrival_reference'),
            'departure_on' => __('delegates.col_departure_on'),
            'departure_at' => __('delegates.col_departure_at'),
            'departure_reference' => __('delegates.col_departure_reference'),
            'notes' => __('delegates.col_notes'),
        ];
    }
}
