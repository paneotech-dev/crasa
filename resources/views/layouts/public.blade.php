{{--
    Base layout for every public surface.

    Screens fill @section('content'). The header, footer and language
    switcher are included from partials and are not rebuilt per screen,
    which is what keeps the switcher from clipping and the header from
    overflowing. See design foundations, section 06.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('has-js');</script>

    <title>@yield('title', __('site.acronym')) — {{ __('site.acronym') }}</title>
    @hasSection('description')
        <meta name="description" content="@yield('description')">
    @endif

    <link rel="icon" href="{{ asset('img/crasa-logo.svg') }}" type="image/svg+xml">

    {{-- WS-02. The manifest is a route because its start URL carries the
         reader's language; a fixed one installs an English app on a
         Portuguese reader's phone.

         apple-touch-icon is separate because iOS ignores the manifest's
         icons and reads this instead. --}}
    <link rel="manifest" href="{{ route('manifest', ['locale' => app()->getLocale()]) }}">
    <link rel="apple-touch-icon" href="{{ asset('img/app/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0F1B2D">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ __('site.acronym') }}">

    {{-- Every alternate language of this page, so a reader arriving from a
         search result lands in the language they searched in. --}}
    @foreach (\App\Support\Locales::SUPPORTED as $alternate)
        <link rel="alternate" hreflang="{{ $alternate }}" href="{{ \App\Support\Locales::urlFor($alternate, request()) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locales::urlFor(\App\Support\Locales::FALLBACK, request()) }}">

    {{-- Order matters. Tokens first, then Bootstrap's grid-only build and the
         bridge that binds it to them, then our own layers. --}}
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/vendor/bootstrap-grid.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/grid.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/base.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/chrome.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/blocks.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/print.css') }}" media="print">
    @stack('styles')
</head>
<body>
{{-- Cloudflare's email obfuscation is told to leave this page alone. Its
     decoder throws on the SVG map links, and the addresses are meant to be
     read and clicked anyway. --}}
<!--email_off-->
    <a class="c-skip" href="#content">{{ __('nav.skip') }}</a>

    @include('partials.site-header')

    <main id="content" tabindex="-1">
        @yield('content')
    </main>

    {{-- The install prompt, screen 15 line 374.
         "width:300px;border:1px solid var(--c-sand);border-radius:var(--r-lg);
          overflow:hidden;background:var(--c-white);box-shadow:var(--e-2)"

         It was a full-width navy band above the footer. That is not what the
         screen draws and it is not what the rule under the drawing allows:
         "Shown once, on the second visit, never as an interstitial over
         content." A band in the flow is not an interstitial, but it is also
         not the card, and it pushed the footer down on every page for every
         reader whose browser offered the prompt.

         A 300px card, bottom right on desktop and across the bottom on a
         phone. app.js still reveals it only on a second visit and never
         again once dismissed. --}}
    <div class="c-install" data-install hidden>
        <div class="c-install__card" role="dialog" aria-modal="false"
             aria-labelledby="install-title">
            {{-- The source bar. A card asking to install something states
                 where it comes from, which is the browser's own convention
                 and the screen's line 375. --}}
            <p class="c-install__source">{{ request()->getHost() }}</p>

            <div class="c-install__body">
                <div class="c-install__head">
                    <img class="c-install__icon" src="{{ \App\Support\Assets::url('img/app/icon-192.png') }}"
                         alt="" width="52" height="52">
                    <div class="c-install__names">
                        <p class="c-install__title" id="install-title">{{ __('app.install_title') }}</p>
                        <p class="c-install__meta">{{ request()->getHost() }}</p>
                    </div>
                </div>

                <p class="c-install__copy">{{ __('app.install_body') }}</p>

                <div class="c-install__actions">
                    <button class="c-btn c-btn--primary" type="button" data-install-accept>{{ __('app.install') }}</button>
                    <button class="c-btn c-btn--secondary" type="button" data-install-dismiss>{{ __('app.install_not_now') }}</button>
                </div>

                <p class="c-install__note">{{ __('app.install_note') }}</p>
            </div>
        </div>
    </div>

    @include('partials.site-footer')

    <script src="{{ \App\Support\Assets::url('js/chrome.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/nav.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/hero.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/app.js') }}" defer></script>
    @stack('scripts')
<!--/email_off-->
</body>
</html>
