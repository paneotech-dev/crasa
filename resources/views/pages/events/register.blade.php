@extends('layouts.public')

@section('title', __('delegates.title'))

@section('content')
    <div class="c-section c-section--title">
        <div class="c-page">
            <p class="b__eyebrow">{{ $meeting->title }}</p>
            <h1>{{ __('delegates.title') }}</h1>
            <p class="c-lead">{{ __('delegates.lead') }}</p>
        </div>
    </div>

    <div class="c-section">
        <div class="c-page">
            {{-- Screen 18 draws accommodation, nights and dietary fields.
                 They are not here: the website collects arrival and
                 departure, the Event Management Platform manages the rest,
                 and that division is a separate commercial agreement.
                 Logged in docs/OPEN_ITEMS.md. --}}
            <form class="c-form" method="POST" action="{{ route('events.register.store', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">
                @csrf

                @if ($errors->any())
                    <div class="c-notice c-notice--error" role="alert">
                        <div><div class="c-notice__body">
                            <ul style="margin:0;padding-left:1.2em">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div></div>
                    </div>
                @endif

                <fieldset>
                    <legend>{{ __('delegates.you') }}</legend>

                    <div class="c-field">
                        <label class="c-label" for="delegate_name">{{ __('delegates.col_name') }}</label>
                        <input class="c-input" type="text" id="delegate_name" name="delegate_name" required
                               value="{{ old('delegate_name', $existing?->delegate_name ?? auth()->user()?->name) }}">
                    </div>

                    <div class="c-field">
                        <label class="c-label" for="delegate_email">{{ __('delegates.col_email') }}</label>
                        <input class="c-input" type="email" id="delegate_email" name="delegate_email" required
                               value="{{ old('delegate_email', $existing?->delegate_email ?? auth()->user()?->email) }}">
                    </div>

                    <div class="c-field">
                        <label class="c-label" for="delegate_role">{{ __('delegates.col_role') }}</label>
                        <input class="c-input" type="text" id="delegate_role" name="delegate_role"
                               value="{{ old('delegate_role', $existing?->delegate_role) }}">
                    </div>

                    {{-- A delegate signed in from a member state account
                         has this already and is shown it rather than asked
                         it. A delegate without an account has no other way
                         to say which regulator they attend for, and the
                         roster is sorted by it. --}}
                    @if (auth()->user()?->country)
                        <div class="c-field">
                            <span class="c-label">{{ __('delegates.col_country') }}</span>
                            <p class="c-field__hint">{{ auth()->user()->country->name }}</p>
                        </div>
                    @else
                        <div class="c-field">
                            <label class="c-label" for="country_id">{{ __('delegates.col_country') }}</label>
                            <select class="c-select" id="country_id" name="country_id" aria-describedby="country-hint">
                                <option value="">{{ __('delegates.country_none') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected((int) old('country_id', $existing?->country_id) === $country->id)>{{ $country->name }}</option>
                                @endforeach
                            </select>
                            <p class="c-field__hint" id="country-hint">{{ __('delegates.country_hint') }}</p>
                        </div>
                    @endif
                </fieldset>

                <fieldset>
                    <legend>{{ __('delegates.arrival') }}</legend>

                    <div class="c-field">
                        <label class="c-label" for="arrival_point">{{ __('delegates.col_arrival_point') }}</label>
                        <input class="c-input" type="text" id="arrival_point" name="arrival_point"
                               value="{{ old('arrival_point', $existing?->arrival_point) }}">
                    </div>

                    <div class="c-field">
                        <label class="c-label" for="arrival_on">{{ __('delegates.col_arrival_on') }}</label>
                        <input class="c-input" type="date" id="arrival_on" name="arrival_on" required
                               value="{{ old('arrival_on', $existing?->arrival_on?->toDateString()) }}">
                    </div>

                    <div class="c-field">
                        <label class="c-label" for="arrival_at">{{ __('delegates.col_arrival_at') }}</label>
                        <input class="c-input" type="time" id="arrival_at" name="arrival_at"
                               value="{{ old('arrival_at', $existing?->arrival_at) }}">
                    </div>

                    <div class="c-field">
                        <label class="c-label" for="arrival_reference">{{ __('delegates.col_arrival_reference') }}</label>
                        <input class="c-input" type="text" id="arrival_reference" name="arrival_reference"
                               value="{{ old('arrival_reference', $existing?->arrival_reference) }}">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>{{ __('delegates.departure') }}</legend>

                    <div class="c-field">
                        <label class="c-label" for="departure_on">{{ __('delegates.col_departure_on') }}</label>
                        <input class="c-input" type="date" id="departure_on" name="departure_on" required
                               value="{{ old('departure_on', $existing?->departure_on?->toDateString()) }}">
                    </div>

                    <div class="c-field">
                        <label class="c-label" for="departure_at">{{ __('delegates.col_departure_at') }}</label>
                        <input class="c-input" type="time" id="departure_at" name="departure_at"
                               value="{{ old('departure_at', $existing?->departure_at) }}">
                    </div>

                    <div class="c-field">
                        <label class="c-label" for="departure_reference">{{ __('delegates.col_departure_reference') }}</label>
                        <input class="c-input" type="text" id="departure_reference" name="departure_reference"
                               value="{{ old('departure_reference', $existing?->departure_reference) }}">
                    </div>
                </fieldset>

                <div class="c-field">
                    <label class="c-label" for="notes">{{ __('delegates.col_notes') }}</label>
                    <textarea class="c-input" id="notes" name="notes" rows="4" maxlength="1000">{{ old('notes', $existing?->notes) }}</textarea>
                    <p class="c-small">{{ __('delegates.notes_hint') }}</p>
                </div>

                {{-- Honeypot. Hidden from people and from screen readers, so
                     nobody is asked to fill a field that must stay empty. --}}
                <div class="c-honeypot" aria-hidden="true">
                    <label for="website">{{ __('contact.field_website') }}</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
                </div>

                <button type="submit" class="c-btn c-btn--primary">{{ __('delegates.file_action') }}</button>
            </form>
        </div>
    </div>
@endsection
