{{--
    The shell both administered surfaces sit in.

    Screen 09 draws a horizontal section bar in a page header and it is
    overruled, on a ruling of 10 September 2026 and logged as a disclosed
    variation. With twenty-four destinations the bar wrapped two and three
    rows deep before any content began, and the back office is the surface
    the Secretariat uses daily. Screen 09's content is untouched: the
    figures, the recent activity and the sample-data count are all still
    there, inside this.

    The extranet uses the same shell. Two shells for two signed-in surfaces
    is two things to keep in step, and screen 04's rail was already the same
    idea drawn narrower.

    Layouts pass three things: the navigation groups, the wordmark, and
    where the mark points. Everything else is identical, which is the point.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('crasa.noindex'))<meta name="robots" content="noindex, nofollow">@endif
    <meta name="crasa-pw-show" content="{{ __('auth.show_password') }}">
    <meta name="crasa-pw-hide" content="{{ __('auth.hide_password') }}">
    <script>document.documentElement.classList.add('has-js');</script>
    {{-- The display options a reader chose, on the public site or here. --}}
    @include('partials.a11y-head')

    <title>@yield('title', $brand) | {{ $brand }}</title>

    {{-- Never indexed. It is behind authentication, but a crawler that
         reaches a login redirect should not keep the URL. --}}
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" href="{{ asset('img/crasa-logo.svg') }}" type="image/svg+xml">

    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/vendor/bootstrap-grid.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/grid.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/base.css') }}">
    {{-- The UI system's tokens (--u-*), which the light back office reads. --}}
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/ui.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/admin-tokens.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/admin.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/admin-workflow.css') }}">
    {{-- The display options: the public sheet, then what the shell needs
         on top of it (text size on the sidebar and content, high contrast
         on the admin tokens). --}}
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/a11y.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/a11y-shell.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/print.css') }}" media="print">
    @stack('styles')
</head>
<body data-surface="admin" data-theme="{{ (auth()->user()?->theme ?? \App\Enums\Theme::System)->value }}">
{{-- Cloudflare's email obfuscation is told to leave this page alone. Its
     decoder throws on the SVG map links, and the addresses are meant to be
     read and clicked anyway. --}}
<!--email_off-->
    <a class="c-skip" href="#content">{{ __('nav.skip') }}</a>

    <div class="a-shell" id="top">
        @include('partials.shell-sidebar', ['groups' => $groups, 'brand' => $brand, 'home' => $home])

        {{-- The drawer's backdrop. Present with no script and inert:
             a-shell__veil is display:none until the drawer opens. --}}
        <div class="a-shell__veil" data-shell-veil hidden></div>

        <div class="a-shell__body">
            @include('partials.shell-topbar', ['search' => $search ?? true])

            {{-- Back to top and the display options, the same control as on
                 the public site, in the bottom right corner. After the top
                 bar in the source, so a keyboard reader meets it before the
                 content. --}}
            @include('partials.float-controls')

            <main id="content" class="a-main" tabindex="-1">
                @hasSection('crumbs')
                    <nav class="a-crumbs" aria-label="{{ __('admin.breadcrumb') }}">
                        <ol>@yield('crumbs')</ol>
                    </nav>
                @endif

                <div class="a-pagehead">
                    <div class="a-pagehead__text">
                        <h1>@hasSection('heading')@yield('heading')@else@yield('title')@endif</h1>
                        @hasSection('note')
                            <p class="a-pagehead__note">@yield('note')</p>
                        @endif
                    </div>
                    @hasSection('actions')
                        <div class="a-pagehead__actions">@yield('actions')</div>
                    @endif
                </div>

                @include('partials.shell-alerts')

                @yield('content')
            </main>
        </div>
    </div>

    <script src="{{ \App\Support\Assets::url('js/shell.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/tablewrap.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/password.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/modal.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/admin-workflow.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/a11y.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/float.js') }}" defer></script>
    @stack('scripts')
<!--/email_off-->
</body>
</html>
