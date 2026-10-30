{{--
    Cookie choice (item 1h): the banner, the settings dialog and a live
    region, driven by public/js/consent.js.

    The banner is in every page's HTML and hidden; the script shows it when
    this browser holds no current choice. Nothing here depends on the
    server knowing the choice, because a public page is the same cached
    page for everyone: third-party content is always sent as a placeholder
    and the script swaps it in when the reader has allowed it.

    Placed at the start of the body, after the skip link, so a keyboard or
    screen reader user meets it first; drawn at the foot of the screen and
    never over more than half of it.
--}}
@php
    $privacyUrl = \App\Support\Navigation::url('privacy');
@endphp
<section class="cc" data-consent-banner aria-labelledby="cc-title" hidden>
    <div class="cc__inner">
        <h2 class="cc__title" id="cc-title">{{ __('d3_privacy.banner_title') }}</h2>
        <p class="cc__text">{{ __('d3_privacy.banner_text') }} <a href="{{ $privacyUrl }}">{{ __('d3_privacy.notice_link') }}</a></p>
        <div class="cc__actions">
            <button type="button" class="u-btn u-btn--primary" data-consent-all>{{ __('d3_privacy.accept_all') }}</button>
            <button type="button" class="u-btn u-btn--primary" data-consent-none>{{ __('d3_privacy.reject') }}</button>
            <button type="button" class="u-btn u-btn--secondary" data-consent-settings aria-haspopup="dialog">{{ __('d3_privacy.settings') }}</button>
        </div>
    </div>
</section>

<dialog class="cc-dialog" data-consent-dialog aria-labelledby="cc-dialog-title" aria-describedby="cc-dialog-intro">
    <form method="dialog" class="cc-dialog__form" data-consent-form>
        <h2 class="cc__title" id="cc-dialog-title">{{ __('d3_privacy.settings_title') }}</h2>
        <p class="cc__text" id="cc-dialog-intro">{{ __('d3_privacy.settings_intro') }} <a href="{{ $privacyUrl }}">{{ __('d3_privacy.notice_link') }}</a></p>

        <div class="cc-cat">
            <p class="cc-cat__name">{{ __('d3_privacy.essential') }} <span class="cc-cat__on">({{ __('d3_privacy.always_on') }})</span></p>
            <p class="cc-cat__text">{{ __('d3_privacy.essential_text') }}</p>
        </div>
        <div class="cc-cat">
            <input type="checkbox" class="cc-cat__box" id="cc-social" name="social" value="1" aria-describedby="cc-social-text">
            <label class="cc-cat__name" for="cc-social">{{ __('d3_privacy.social') }}</label>
            <p class="cc-cat__text" id="cc-social-text">{{ __('d3_privacy.social_text') }}</p>
        </div>
        <div class="cc-cat">
            <input type="checkbox" class="cc-cat__box" id="cc-maps" name="maps" value="1" aria-describedby="cc-maps-text">
            <label class="cc-cat__name" for="cc-maps">{{ __('d3_privacy.maps') }}</label>
            <p class="cc-cat__text" id="cc-maps-text">{{ __('d3_privacy.maps_text') }}</p>
        </div>

        <div class="cc__actions">
            <button type="submit" class="u-btn u-btn--primary" data-consent-save>{{ __('d3_privacy.save') }}</button>
            <button type="button" class="u-btn u-btn--secondary" data-consent-all>{{ __('d3_privacy.accept_all') }}</button>
            <button type="button" class="u-btn u-btn--secondary" data-consent-none>{{ __('d3_privacy.reject') }}</button>
            <button type="button" class="u-btn u-btn--ghost" data-consent-close>{{ __('d3_privacy.close') }}</button>
        </div>
    </form>
</dialog>

<p class="c-sr" role="status" data-consent-status data-saved="{{ __('d3_privacy.saved') }}"></p>
