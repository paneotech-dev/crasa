{{--
    Contact (S6, C15, 29 October 2026). Short hero; the form card on the
    left and a stack of contact cards on the right; the map full width under
    both, drawn on load with a pin on the Secretariat. The saved image of the
    same area is the ground under the live map, so if the tile server cannot
    be reached the reader still has a map with the pin; with scripting off a
    link opens the map on OpenStreetMap.

    Every field has a real label above it, required in words, helper text
    tied with aria-describedby and its error next to it.
--}}
@extends('layouts.public')

@section('title', __('contact.title'))
@section('description', __('contact.intro'))

@include('partials.cpages.styles')

@php
    $locale = app()->getLocale();
    $lat = config('crasa.map.latitude');
    $lng = config('crasa.map.longitude');
    $googleMaps = 'https://www.google.com/maps/search/?api=1&query='.$lat.','.$lng;
    $osm = 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map=16/'.$lat.'/'.$lng;
    $phone = \App\Support\SiteContact::phone();
    $email = \App\Support\SiteContact::email();
    $socials = collect(\App\Support\SocialAccounts::all())->filter();
    $counts = \App\Support\SectionCounts::forPage('contact');
@endphp

@section('content')
    @include('partials.cpages.hero', [
        'eyebrow' => __('contact.band_eyebrow'),
        'title' => __('contact.title'),
        'lead' => __('contact.intro'),
        'figures' => $counts,
    ])

    <section class="u-section cp-contact">
        <div class="u-container cp-contact__grid">
            <div class="u-panel cp-contact__form">
                <h2 class="u-h3">{{ __('cpages.contact_form_title') }}</h2>
                <p class="u-help cp-contact__help">{{ __('cpages.contact_form_help') }}</p>

                @if (session('enquiry_received'))
                    <div class="u-success cp-contact__success" role="status">
                        <svg width="20" height="20" class="cp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                        <div>
                            <div>{{ __('contact.received_title') }}</div>
                            <div class="cp-contact__success-body">{{ __('contact.received_body') }}</div>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="cp-contact__errors" role="alert">
                        <p class="u-error">{{ trans_choice('contact.errors_title', $errors->count(), ['count' => $errors->count()]) }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('contact.store', ['locale' => $locale]) }}" class="cp-form">
                    @csrf

                    <div class="cp-form__row">
                        <div @class(['u-field', 'u-field--error' => $errors->has('name')])>
                            <label for="name">{{ __('contact.field_name') }} <span class="u-req">({{ __('cpages.required') }})</span></label>
                            <input class="u-input" type="text" id="name" name="name" value="{{ old('name') }}" required autocomplete="name" @error('name') aria-invalid="true" @enderror @error('name') aria-describedby="name-error" @enderror>
                            @error('name')<p class="u-error" id="name-error">{{ $message }}</p>@enderror
                        </div>

                        <div @class(['u-field', 'u-field--error' => $errors->has('email')])>
                            <label for="email">{{ __('contact.field_email') }} <span class="u-req">({{ __('cpages.required') }})</span></label>
                            <input class="u-input" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" @error('email') aria-invalid="true" @enderror @error('email') aria-describedby="email-error" @enderror>
                            @error('email')<p class="u-error" id="email-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="cp-form__row">
                        <div class="u-field">
                            <label for="organisation">{{ __('contact.field_organisation') }}</label>
                            <input class="u-input" type="text" id="organisation" name="organisation" value="{{ old('organisation') }}" autocomplete="organization" aria-describedby="organisation-hint">
                            <p class="u-help" id="organisation-hint">{{ __('contact.hint_organisation') }}</p>
                        </div>

                        <div class="u-field">
                            <label for="country_id">{{ __('contact.field_country') }}</label>
                            <select class="u-select" id="country_id" name="country_id">
                                <option value="">{{ __('contact.country_unspecified') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected(old('country_id') == $country->id)>{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div @class(['u-field', 'u-field--error' => $errors->has('subject')])>
                        <label for="subject">{{ __('contact.field_subject') }} <span class="u-req">({{ __('cpages.required') }})</span></label>
                        <input class="u-input" type="text" id="subject" name="subject" value="{{ old('subject') }}" required @error('subject') aria-invalid="true" @enderror @error('subject') aria-describedby="subject-error" @enderror>
                        @error('subject')<p class="u-error" id="subject-error">{{ $message }}</p>@enderror
                    </div>

                    <div @class(['u-field', 'u-field--error' => $errors->has('message')])>
                        <label for="message">{{ __('contact.field_message') }} <span class="u-req">({{ __('cpages.required') }})</span></label>
                        <textarea class="u-textarea" id="message" name="message" rows="7" required @error('message') aria-invalid="true" @enderror aria-describedby="message-hint @error('message') message-error @enderror">{{ old('message') }}</textarea>
                        <p class="u-help" id="message-hint">{{ __('contact.hint_message') }}</p>
                        @error('message')<p class="u-error" id="message-error">{{ $message }}</p>@enderror
                    </div>

                    {{-- Honeypot. Hidden from people and from screen readers, so
                         nobody is asked to fill a field that must stay empty. --}}
                    <div class="c-honeypot" aria-hidden="true">
                        <label for="website">{{ __('contact.field_website') }}</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
                    </div>

                    @include('partials.turnstile')

                    <div class="cp-form__submit">
                        <button type="submit" class="u-btn u-btn--primary">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                            {{ __('contact.submit') }}
                        </button>
                        <p class="u-help">{{ __('contact.privacy_note') }}</p>
                    </div>
                </form>
            </div>

            <aside class="cp-contact__cards" aria-label="{{ __('contact.secretariat') }}">
                <div class="u-panel cp-contact-card">
                    <span class="cp-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-6.3 7-12a7 7 0 1 0-14 0c0 5.7 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                    <div>
                        <h2 class="cp-contact-card__title">{{ __('cpages.contact_address_title') }}</h2>
                        <address class="cp-contact-card__text">
                            {{ \App\Support\SiteContact::addressLine1() }}<br>
                            {{ \App\Support\SiteContact::addressLine2() }}
                        </address>
                        <a class="cp-contact-card__link" href="{{ $googleMaps }}" target="_blank" rel="noopener">{{ __('cpages.contact_open_maps') }}</a>
                    </div>
                </div>

                <div class="u-panel cp-contact-card">
                    <span class="cp-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg></span>
                    <div>
                        <h2 class="cp-contact-card__title">{{ __('cpages.contact_email_title') }}</h2>
                        <a class="cp-contact-card__link" href="mailto:{{ $email }}">{{ $email }}</a>
                        <h2 class="cp-contact-card__title cp-contact-card__title--second">{{ __('cpages.contact_call_title') }}</h2>
                        <a class="cp-contact-card__link" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>
                    </div>
                </div>

                <div class="u-panel cp-contact-card">
                    <span class="cp-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                    <div>
                        <h2 class="cp-contact-card__title">{{ __('cpages.contact_hours_title') }}</h2>
                        <p class="cp-contact-card__text">{{ __('cpages.contact_hours') }}</p>
                    </div>
                </div>

                @if ($socials->isNotEmpty())
                    <div class="u-panel cp-contact-card">
                        <span class="cp-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg></span>
                        <div>
                            <h2 class="cp-contact-card__title">{{ __('cpages.contact_social_title') }}</h2>
                            <ul class="cp-contact-social" role="list">
                                @foreach ($socials as $network => $url)
                                    <li>
                                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ __('blocks.social_account', ['network' => \App\Support\SocialAccounts::label($network)]) }}">
                                            <x-social-mark :network="$network" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </section>

    <section class="u-section u-section--pale cp-contact-map-section" aria-labelledby="contact-map-title">
        <div class="u-container">
            <div class="cp-section-head">
                <div>
                    <h2 class="u-h2" id="contact-map-title">{{ __('cpages.contact_map_title') }}</h2>
                    <p class="u-text u-muted cp-map__label">{{ __('contact.map_label') }}</p>
                </div>
                <a class="u-btn u-btn--secondary" href="{{ $googleMaps }}" target="_blank" rel="noopener">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s7-6.3 7-12a7 7 0 1 0-14 0c0 5.7 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    {{ __('cpages.contact_open_maps') }}
                </a>
            </div>
            <div class="cp-map" data-contact-map
                 data-lat="{{ $lat }}" data-lng="{{ $lng }}" data-zoom="{{ config('crasa.map.zoom') }}"
                 data-tiles="{{ config('crasa.map.tile_url') }}"
                 data-attribution="{{ config('crasa.map.tile_attribution') }}"
                 data-label="{{ __('contact.map_label') }}">
                <div class="cp-map__static">
                    <img src="{{ asset('images/contact/contact-map.webp') }}" alt="{{ __('cpages.contact_map_static') }}" width="768" height="512" loading="lazy">
                    <span class="cp-map__pin" aria-hidden="true"><svg viewBox="0 0 24 24" width="36" height="36"><path d="M12 23s8-7.2 8-13.5A8 8 0 0 0 4 9.5C4 15.8 12 23 12 23z" fill="#0F8757" stroke="#fff" stroke-width="1.5"/><circle cx="12" cy="9.5" r="3" fill="#fff"/></svg></span>
                    <span class="cp-map__credit">{{ __('cpages.contact_map_credit') }}</span>
                </div>
                <noscript>
                    <p class="cp-map__noscript"><a class="u-btn u-btn--secondary" data-map-load href="{{ $osm }}">{{ __('contact.map_load') }}</a></p>
                </noscript>
            </div>
        </div>
    </section>

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages.contact_cta_title'),
        'ctaText' => __('cpages.contact_cta_text'),
        'ctaPrimary' => [__('cpages.contact_cta_library'), route('publications', ['locale' => $locale])],
        'ctaSecondary' => [__('cpages.contact_cta_members'), route('members', ['locale' => $locale])],
    ])
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/contact-map.js') }}" defer></script>
@endpush
