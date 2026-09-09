{{--
    Contact. WS-10. No design screen exists, so this is built from the
    foundations components.

    Every field has a real label. A placeholder standing in for one
    disappears the moment a reader starts typing, which is exactly when they
    need it, and a screen reader gets nothing to announce.
--}}
@extends('layouts.public')

@section('title', __('contact.title'))
@section('description', __('contact.intro'))

@section('content')
    {{-- The band screen 08 draws, which this page was the only public one
         without. Its two entries are the only things that are true and
         labelled about contacting CRASA: where the Secretariat sits, and how
         many regulators it answers to. Opening hours and a response time
         would both be inventions. --}}
    <x-section-band
        :eyebrow="__('contact.band_eyebrow')"
        :title="__('contact.title')"
        :lead="__('contact.intro')"
        :counts="\App\Support\SectionCounts::forPage('contact')"
    />

    <div class="c-section">
        <div class="c-page c-page--reading">

            @if (session('enquiry_received'))
                <div class="c-notice c-notice--success" role="status">
                    <svg class="c-notice__icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="var(--c-green-dark)" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                        <path d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <div class="c-notice__title">{{ __('contact.received_title') }}</div>
                        <div class="c-notice__body">{{ __('contact.received_body') }}</div>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="c-notice c-notice--error" role="alert">
                    <svg class="c-notice__icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="var(--c-red)" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path>
                    </svg>
                    <div>
                        <div class="c-notice__title">{{ trans_choice('contact.errors_title', $errors->count(), ['count' => $errors->count()]) }}</div>
                        <div class="c-notice__body">
                            <ul class="c-list">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('contact.store', ['locale' => app()->getLocale()]) }}" class="c-form">
                @csrf

                <div class="c-field">
                    <label class="c-label" for="name">{{ __('contact.field_name') }} <span class="c-required" aria-hidden="true">*</span></label>
                    <input class="c-input" type="text" id="name" name="name" value="{{ old('name') }}"
                           required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    @error('name')<p class="c-field__error" id="name-error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="email">{{ __('contact.field_email') }} <span class="c-required" aria-hidden="true">*</span></label>
                    <input class="c-input" type="email" id="email" name="email" value="{{ old('email') }}"
                           required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')<p class="c-field__error" id="email-error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="organisation">{{ __('contact.field_organisation') }}</label>
                    <input class="c-input" type="text" id="organisation" name="organisation" value="{{ old('organisation') }}"
                           autocomplete="organization">
                    <p class="c-field__hint" id="organisation-hint">{{ __('contact.hint_organisation') }}</p>
                </div>

                <div class="c-field">
                    <label class="c-label" for="country_id">{{ __('contact.field_country') }}</label>
                    <select class="c-input" id="country_id" name="country_id">
                        <option value="">{{ __('contact.country_unspecified') }}</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected(old('country_id') == $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="c-field">
                    <label class="c-label" for="subject">{{ __('contact.field_subject') }} <span class="c-required" aria-hidden="true">*</span></label>
                    <input class="c-input" type="text" id="subject" name="subject" value="{{ old('subject') }}"
                           required @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                    @error('subject')<p class="c-field__error" id="subject-error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="message">{{ __('contact.field_message') }} <span class="c-required" aria-hidden="true">*</span></label>
                    <textarea class="c-input c-input--area" id="message" name="message" rows="8" required
                              aria-describedby="message-hint @error('message') message-error @enderror">{{ old('message') }}</textarea>
                    <p class="c-field__hint" id="message-hint">{{ __('contact.hint_message') }}</p>
                    @error('message')<p class="c-field__error" id="message-error">{{ $message }}</p>@enderror
                </div>

                {{-- Honeypot. Hidden from people and from screen readers, so
                     nobody is asked to fill a field that must stay empty. --}}
                <div class="c-honeypot" aria-hidden="true">
                    <label for="website">{{ __('contact.field_website') }}</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
                </div>

                <p class="c-form__note">{{ __('contact.privacy_note') }}</p>

                <button type="submit" class="c-btn c-btn--primary">{{ __('contact.submit') }}</button>
            </form>

            <h2>{{ __('contact.secretariat') }}</h2>
            <address class="c-address">
                <div>{{ __('footer.address_line_1') }}</div>
                <div>{{ __('footer.address_line_2') }}</div>
                <div><a href="mailto:{{ __('footer.email') }}">{{ __('footer.email') }}</a></div>
                <div>{{ __('footer.telephone') }}</div>
            </address>

            {{-- The map loads on request. Tiles are a third-party server, so
                 nothing about a reader leaves this platform unless they ask
                 for the map. The address above is readable without it. --}}
            <div class="c-map" data-map
                 data-lat="{{ config('crasa.map.latitude') }}"
                 data-lng="{{ config('crasa.map.longitude') }}"
                 data-zoom="{{ config('crasa.map.zoom') }}"
                 data-tiles="{{ config('crasa.map.tile_url') }}"
                 data-attribution="{{ config('crasa.map.tile_attribution') }}"
                 data-label="{{ __('contact.map_label') }}">
                <div class="c-map__placeholder">
                    <p class="c-map__note">{{ __('contact.map_note') }}</p>
                    <button type="button" class="c-btn c-btn--secondary" data-map-load>{{ __('contact.map_load') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/map.js') }}" defer></script>
@endpush
