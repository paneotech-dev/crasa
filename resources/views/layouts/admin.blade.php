{{--
    Base layout for the back office.

    Screen 09 draws a horizontal section bar in the page header, not a
    sidebar, and keeps Lora on headings while the interface itself is set in
    Inter. Both are followed. The layout carries no navigation logic of its
    own: what a user can reach is decided by policy on the route, and the
    nav only renders what they can already open.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('has-js');</script>

    <title>@yield('title', __('admin.back_office')) — {{ __('admin.back_office') }}</title>

    {{-- The back office is never indexed. It is behind authentication, but
         a crawler that reaches a login redirect should not keep the URL. --}}
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" href="{{ asset('img/crasa-logo.svg') }}" type="image/svg+xml">

    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-grid.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/grid.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}" media="print">
    @stack('styles')
</head>
<body data-surface="admin" data-theme="{{ (auth()->user()?->theme ?? \App\Enums\Theme::System)->value }}">
    <a class="c-skip" href="#content">{{ __('nav.skip') }}</a>

    @include('partials.admin-header')

    <main id="content" tabindex="-1">
        <div class="a-page">
            @hasSection('crumbs')
                <nav class="a-crumbs" aria-label="{{ __('admin.breadcrumb') }}">
                    <ol>@yield('crumbs')</ol>
                </nav>
            @endif

            <div class="a-pagehead">
                <div>
                    <h1>@yield('heading', View::getSection('title'))</h1>
                    @hasSection('note')
                        <div class="a-pagehead__note">@yield('note')</div>
                    @endif
                </div>
                @hasSection('actions')
                    <div class="a-pagehead__actions">@yield('actions')</div>
                @endif
            </div>

            {{-- Mail that goes nowhere is the one misconfiguration on this
                 platform that produces no error anywhere. The Secretariat is
                 told on every back office screen until it is fixed, because
                 the alternative is finding out at a workshop when somebody
                 asks why they never received the notification. --}}
            @if (\App\Support\DeliveryCheck::mailIsSilentInProduction())
                <div class="a-notice a-notice--error" role="alert">
                    <strong>{{ __('admin.mail_silent_title') }}</strong>
                    {{ __('admin.mail_silent_body', ['driver' => config('mail.default'), 'env' => app()->environment()]) }}
                </div>
            @elseif (\App\Support\DeliveryCheck::fromAddressIsUnset())
                <div class="a-notice a-notice--pending" role="alert">
                    <strong>{{ __('admin.mail_from_title') }}</strong>
                    {{ __('admin.mail_from_body', ['address' => config('mail.from.address')]) }}
                </div>
            @endif

            @if (session('status'))
                <div class="a-notice a-notice--success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="a-notice a-notice--error" role="alert">
                    <ul style="margin:0;padding-left:1.2em">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>
