{{--
    Base layout for the extranet.

    Screen 04 draws the extranet with its own navy left rail, not the back
    office's horizontal section bar. Until now the extranet extended the
    back office layout, which put screen 09's pattern on a surface that has
    an approved pattern of its own.

    From the screen, verbatim:

      <nav aria-label="Extranet" style="flex:1 1 220px;max-width:280px;
           min-width:0;background:var(--c-navy);color:var(--c-cream);
           padding:var(--s-5) 0;display:flex;flex-direction:column;
           gap:var(--s-6)">

    Flat links, not grouped. The screen draws seven and no group headings,
    and grouping five items under three headings adds headings without
    adding navigation.

    The clearance panel is pinned to the bottom with margin-top:auto, as the
    screen draws it, because a reader who does not know their own clearance
    reads an empty folder list as a fault rather than as a boundary.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('has-js');</script>

    <title>@yield('title', __('extranet.title')) — {{ __('extranet.title') }}</title>

    {{-- Never indexed. It is behind authentication, but a crawler that
         reaches a login redirect should not keep the URL. --}}
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

    @php
        $sections = \App\Support\ExtranetNavigation::sections();
        $clearance = \App\Support\ExtranetNavigation::clearance(auth()->user());
    @endphp

    <div class="e-shell">
        <nav class="e-rail" aria-label="{{ __('extranet.title') }}">
            <a class="e-rail__mark" href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">
                <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="36" height="36">
                <span>
                    <span class="e-rail__wordmark">{{ __('site.acronym') }}</span>
                    <span class="e-rail__kicker">{{ __('extranet.title') }}</span>
                </span>
            </a>

            <div class="e-rail__links">
                @foreach ($sections as $section)
                    <a class="e-rail__link @if ($section['current']) is-current @endif"
                       href="{{ $section['url'] }}"
                       @if ($section['current']) aria-current="page" @endif>
                        <x-extranet-icon :name="$section['icon']" />
                        {{ $section['label'] }}
                    </a>
                @endforeach
            </div>

            @if ($clearance)
                {{-- Screen 04 pins this to the bottom and states the
                     boundary in the reader's own terms: "You can reach
                     Public and Member material, and only Botswana's own
                     country folders." --}}
                <div class="e-rail__clearance">
                    <p class="e-rail__clearance-title">{{ __('extranet.clearance_title') }}</p>
                    <p class="e-rail__clearance-level">{{ $clearance['level'] }}</p>
                    <p class="e-rail__clearance-scope">{{ $clearance['scope'] }}</p>
                </div>
            @endif
        </nav>

        <main id="content" class="e-main" tabindex="-1">
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
    </div>

    @stack('scripts')
</body>
</html>
