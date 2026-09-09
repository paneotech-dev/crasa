{{--
    Public header. Sticky. Identity, primary navigation, search, the
    trilingual switcher and the extranet action.

    Layout invariants that hold this together, from design foundations
    section 07: identity is flex:none and never shrinks; the nav carries
    min-width:0 so it can shrink and flex-wrap so it can drop to a second
    row; the utilities wrapper carries min-width:0, without which the
    switcher clips.
--}}
@php
    $primary = \App\Support\Navigation::primary();
    $extranetUrl = \App\Support\Navigation::url(config('navigation.extranet_path'));
    $searchUrl = \App\Support\Navigation::url(config('navigation.search_path'));

    // Null for a signed-out reader and for a viewer, who has no workspace.
    $workspace = \App\Support\Workspace::for(auth()->user());
@endphp

<header class="c-header" data-header>
    <div class="c-header__inner" data-header-inner>

        <a class="c-header__identity" href="{{ \App\Support\Navigation::url('') }}" data-header-identity>
            <img class="c-header__mark" src="{{ asset('img/crasa-logo.svg') }}" alt="" width="40" height="40">
            <span class="c-header__names">
                <span class="c-header__acronym">{{ __('site.acronym') }}</span>
                <span class="c-header__fullname">{{ __('site.full_name') }}</span>
            </span>
        </a>

        <nav class="c-nav" aria-label="{{ __('nav.primary') }}" data-header-nav>
            @foreach ($primary as $item)
                <a
                    class="c-nav__link"
                    href="{{ $item['url'] }}"
                    @if ($item['current']) aria-current="page" @endif
                >{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="c-header__utilities" data-header-utilities>
            <a class="c-iconbutton" href="{{ $searchUrl }}" aria-label="{{ __('nav.search') }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle><path d="M16.5 16.5L21 21"></path>
                </svg>
            </a>

            @include('partials.language-switcher', ['variant' => 'header'])

            {{-- Signed out, this is the way in. Signed in, the padlock is
                 answered: the reader goes to their own workspace, and a
                 viewer, who has none, goes to their account.

                 The header used to carry the padlocked link either way, so
                 a signed-in reader on a public page had no route to their
                 own work and no route out. --}}
            @guest
                <a class="c-extranet" href="{{ $extranetUrl }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M17 10V8a5 5 0 00-10 0v2"></path><rect x="5" y="10" width="14" height="10" rx="1.5"></rect>
                    </svg>
                    {{ __('nav.extranet') }}
                </a>
            @else
                @if ($workspace)
                    <a class="c-extranet" href="{{ $workspace['url'] }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M17 10V8a5 5 0 00-10 0v2"></path><rect x="5" y="10" width="14" height="10" rx="1.5"></rect>
                        </svg>
                        {{ $workspace['label'] }}
                    </a>
                @endif

                <a class="c-nav__link c-header__account" href="{{ route('account', ['locale' => app()->getLocale()]) }}">{{ __('nav.account') }}</a>
            @endguest
        </div>

        {{-- Shown only once the navigation no longer fits the rendered
             language. Without JavaScript the header keeps every item and
             wraps, so nothing is unreachable. --}}
        <button
            type="button"
            class="c-iconbutton c-header__toggle"
            data-drawer-toggle
            aria-expanded="false"
            aria-controls="site-drawer"
            aria-label="{{ __('nav.open_menu') }}"
            data-label-open="{{ __('nav.open_menu') }}"
            data-label-close="{{ __('nav.close_menu') }}"
        >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path>
            </svg>
            <span class="c-header__toggle-label">{{ __('nav.menu') }}</span>
        </button>
    </div>

    <div class="c-drawer" id="site-drawer" data-drawer hidden>
        <nav class="c-drawer__nav" aria-label="{{ __('nav.primary') }}">
            @foreach ($primary as $item)
                <a
                    class="c-drawer__link"
                    href="{{ $item['url'] }}"
                    @if ($item['current']) aria-current="page" @endif
                >{{ $item['label'] }}</a>
            @endforeach
            <a class="c-drawer__link" href="{{ $searchUrl }}">{{ __('nav.search') }}</a>
        </nav>

        @include('partials.language-switcher', ['variant' => 'drawer'])

        <a class="c-drawer__extranet" href="{{ $workspace['url'] ?? $extranetUrl }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M17 10V8a5 5 0 00-10 0v2"></path><rect x="5" y="10" width="14" height="10" rx="1.5"></rect>
            </svg>
            {{ $workspace['label'] ?? __('nav.extranet') }}
        </a>

        @auth
            <a class="c-drawer__link" href="{{ route('account', ['locale' => app()->getLocale()]) }}">{{ __('nav.account') }}</a>
        @endauth
    </div>
</header>
