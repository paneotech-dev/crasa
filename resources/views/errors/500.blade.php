{{--
    A server error. Deliberately standalone: no menu from the database, no
    session, nothing that could itself fail when the fault is the database.
    Same stylesheets, the logo, the explanation and the way home.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('errors.500_title') }} — {{ __('site.acronym') }}</title>
    <link rel="icon" href="{{ asset('img/crasa-logo.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/chrome.css') }}">
</head>
<body>
    <main id="content" class="c-section">
        <div class="c-page c-page--reading">
            <p><a href="{{ url('/'.app()->getLocale()) }}"><img src="{{ asset('img/crasa-logo.svg') }}" alt="{{ __('site.acronym') }}" width="120"></a></p>
            <p class="c-small">{{ __('errors.eyebrow', ['code' => 500]) }}</p>
            <h1>{{ __('errors.500_title') }}</h1>
            <p class="c-lead">{{ __('errors.500_body') }}</p>
            <p><a class="c-btn c-btn--primary" href="{{ url('/'.app()->getLocale()) }}">{{ __('errors.home') }}</a></p>
        </div>
    </main>
</body>
</html>
