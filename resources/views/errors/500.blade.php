{{--
    A server error. Deliberately standalone: no menu from the database, no
    session, nothing that could itself fail when the fault is the database.
    Same stylesheets, the logo, the explanation, a search form and the main
    sections (UI system, 29 October 2026).
--}}
@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('errors.500_title') }} | {{ __('site.acronym') }}</title>
    <link rel="icon" href="{{ asset('img/crasa-logo.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/chrome.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pages.css') }}">
</head>
<body>
    <header class="cp-error__bar">
        <div class="u-container">
            <a class="cp-auth__brand" href="{{ url('/'.$locale) }}"><img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="48" height="48"><span>{{ __('site.acronym') }}</span></a>
        </div>
    </header>
    <main id="content">
        @include('errors._body', ['code' => 500, 'locale' => $locale, 'back' => null])
    </main>
</body>
</html>
