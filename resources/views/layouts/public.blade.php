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

    {{-- Every alternate language of this page, so a reader arriving from a
         search result lands in the language they searched in. --}}
    @foreach (\App\Support\Locales::SUPPORTED as $alternate)
        <link rel="alternate" hreflang="{{ $alternate }}" href="{{ \App\Support\Locales::urlFor($alternate, request()) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locales::urlFor(\App\Support\Locales::FALLBACK, request()) }}">

    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/chrome.css') }}">
    @stack('styles')
</head>
<body>
    <a class="c-skip" href="#content">{{ __('nav.skip') }}</a>

    @include('partials.site-header')

    <main id="content" tabindex="-1">
        @yield('content')
    </main>

    @include('partials.site-footer')

    <script src="{{ asset('js/chrome.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
