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

        {{-- A root item with children opens a dropdown. Screen 01 draws no
             dropdown -- it names --e-2 as the elevation for "Hover,
             dropdowns, sticky headers" and stops -- so the elevation is the
             screen's and the rest is a disclosed variation.

             THE FLOOR. Without script the group is open: .c-nav__menu is
             only hidden once has-js is on the root, so every child link is
             in the document and reachable by keyboard either way. The
             button is what turns it into a dropdown, and it is not drawn
             when there is nothing to run it. --}}
        <nav class="c-nav" aria-label="{{ __('nav.primary') }}" data-header-nav>
            @foreach ($primary as $item)
                @if (($item['children'] ?? []) === [])
                    <a
                        class="c-nav__link"
                        href="{{ $item['url'] }}"
                        @if ($item['current']) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                @else
                    <div class="c-nav__group" data-nav-group>
                        <button
                            type="button"
                            class="c-nav__link c-nav__toggle"
                            aria-expanded="false"
                            aria-controls="nav-menu-{{ $item['key'] }}"
                            @if ($item['current']) aria-current="page" @endif
                            data-nav-toggle
                        >
                            {{ $item['label'] }}
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </button>

                        <div class="c-nav__menu" id="nav-menu-{{ $item['key'] }}" data-nav-menu>
                            {{-- The group's own page first, where it has
                                 one. "About" is a page as well as a
                                 heading, and a reader who opens the group
                                 to reach it should find it. --}}
                            @if ($item['url'])
                                <a class="c-nav__child" href="{{ $item['url'] }}">{{ __('nav.overview', ['section' => $item['label']]) }}</a>
                            @endif

                            @foreach ($item['children'] as $child)
                                <a class="c-nav__child" href="{{ $child['url'] }}"
                                   @if ($child['current']) aria-current="page" @endif>{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
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
                {{-- A group with no page of its own is a heading here
                     rather than a link to nowhere. --}}
                @if ($item['url'])
                    <a
                        class="c-drawer__link"
                        href="{{ $item['url'] }}"
                        @if ($item['current']) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                @else
                    <p class="c-drawer__heading">{{ $item['label'] }}</p>
                @endif

                {{-- Nested, indented, in the same order. A drawer that
                     drops the second level is a drawer that hides half the
                     site on a phone. --}}
                @foreach ($item['children'] ?? [] as $child)
                    <a class="c-drawer__link c-drawer__link--child" href="{{ $child['url'] }}"
                       @if ($child['current']) aria-current="page" @endif>{{ $child['label'] }}</a>
                @endforeach
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
