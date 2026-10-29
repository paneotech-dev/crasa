{{--
    The Turnstile widget, inside a public form, above its submit button.
    Draws nothing while Turnstile is off (App\Support\Turnstile::enabled),
    and loads Cloudflare's script only on a page that includes it.
--}}
@if (\App\Support\Turnstile::enabled())
    <div class="c-field c-turnstile" role="group" aria-label="{{ __('captcha.label') }}">
        <div class="cf-turnstile" data-sitekey="{{ \App\Support\Turnstile::siteKey() }}"
             data-language="{{ app()->getLocale() }}" data-theme="light"></div>
        @error(\App\Support\Turnstile::FIELD)<p class="c-field__error" id="turnstile-error">{{ $message }}</p>@enderror
    </div>
    @once
        @push('scripts')
            <script src="{{ \App\Support\Turnstile::SCRIPT }}" async defer></script>
        @endpush
    @endonce
@endif
