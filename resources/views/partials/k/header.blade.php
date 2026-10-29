{{--
    Public header, ported from Concept 3 (29 October 2026): utility bar in
    deep green, identity row (logo, full name on one line, mission line,
    search), sticky navigation row with a mega panel per section, and the
    phone drawer with the same links as accordions.

    The items, their order and labels are the menu records
    (Navigation::primary()). The panels' section links, related links and
    which featured card they show are config/megamenu.php; the featured
    cards are real records (HomeData::features(), cached).

    A section with a page of its own keeps its label as a link; the chevron
    beside it opens the panel. Without script the panel opens on hover and
    on keyboard focus inside the item, so every link stays reachable.
--}}
@php
    $primary = \App\Support\Navigation::primary();
    $panels = \App\Support\HomeData::panels($primary);
    $extranetUrl = \App\Support\Navigation::url(config('navigation.extranet_path'));
    $searchUrl = \App\Support\Navigation::url(config('navigation.search_path'));
    $backOfficeUrl = route('admin.dashboard', ['locale' => app()->getLocale()]);
    $workspace = \App\Support\Workspace::for(auth()->user());
    $social = array_intersect_key(\App\Support\SocialAccounts::all(), array_flip(['x', 'facebook', 'linkedin']));
    $current = app()->getLocale();
    $chev = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
    $arrow = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    $searchIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
@endphp

<header class="k-header" id="top" data-header data-k-header>
    <div class="k-ubar" data-surface-tone="dark">
        <div class="k-wrap">
            <span class="k-hide-sm">{{ __('chome.agency') }}</span>
            <div class="k-ubar__r">
                <div class="k-langs" role="group" aria-label="{{ __('nav.language') }}">
                    @foreach (\App\Support\Locales::all() as $code => $language)
                        <a href="{{ \App\Support\Locales::urlFor($code, request()) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                           @if ($code === $current) aria-current="true" @endif>
                            <span class="k-hide-sm">{{ $language['native'] }}</span><span class="k-show-sm" aria-hidden="true">{{ $language['short'] }}</span>
                            @if ($code !== $current)<span class="c-sr">{{ __('site.switch_to', ['language' => __('site.languages.'.$code)]) }}</span>@endif
                        </a>
                    @endforeach
                </div>
                <span class="k-sep k-hide-sm" aria-hidden="true"></span>
                {{-- The three channels in the utility bar on desktop (C11); on
                     phones they are in the drawer. --}}
                @if ($social !== [])
                    <ul class="k-ubar__soc k-hide-sm" role="list">
                        @foreach ($social as $network => $url)
                            <li><a href="{{ $url }}" rel="noopener" target="_blank" data-network="{{ $network }}"
                                   aria-label="{{ __('blocks.social_account', ['network' => \App\Support\SocialAccounts::label($network)]) }}"><x-social-mark :network="$network" tone="white" /></a></li>
                        @endforeach
                    </ul>
                    <span class="k-sep k-hide-sm" aria-hidden="true"></span>
                @endif
                @includeIf('partials.a11y-toolbar')
                <a class="k-hide-sm" href="{{ $backOfficeUrl }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>{{ __('nav.back_office') }}
                </a>
                @guest
                    <a class="k-hide-sm" href="{{ $extranetUrl }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>{{ __('nav.extranet') }}
                    </a>
                @else
                    @if ($workspace)
                        <a class="k-hide-sm" href="{{ $workspace['url'] }}">{{ $workspace['label'] }}</a>
                    @endif
                    <a class="k-hide-sm" href="{{ route('account', ['locale' => $current]) }}">{{ __('nav.account') }}</a>
                @endguest
            </div>
        </div>
    </div>

    <div class="k-ident">
        <div class="k-wrap">
            <a class="k-idb" href="{{ \App\Support\Navigation::url('') }}" aria-label="{{ __('site.full_name') }}">
                <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="56" height="56">
                <span aria-hidden="true">
                    <b class="k-idb__full">{{ __('site.full_name') }}</b>
                    <b class="k-idb__short">{{ __('site.acronym') }}</b>
                    <span class="k-idb__mission">{{ __('site.mission') }}</span>
                </span>
            </a>
            <form class="k-qsearch" role="search" action="{{ $searchUrl }}" method="get">
                <label class="c-sr" for="site-search">{{ __('nav.search') }}</label>
                <input id="site-search" type="search" name="q" placeholder="{{ __('nav.search_placeholder') }}" autocomplete="off">
                <button type="submit" aria-label="{{ __('nav.search') }}">{!! $searchIcon !!}</button>
            </form>
            <button class="k-menu-btn" type="button" data-k-drawer-open aria-controls="k-drawer" aria-expanded="false">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>{{ __('nav.menu') }}
            </button>
        </div>
    </div>

    <div class="k-navrow" data-k-navrow>
        <div class="k-wrap">
            <nav class="k-nav" aria-label="{{ __('nav.primary') }}" data-k-nav>
                @foreach ($primary as $item)
                    @php $panel = $panels[$item['key']] ?? null; @endphp
                    @if ($panel === null)
                        <div class="k-ni">
                            <a class="k-nt" href="{{ $item['url'] }}" @if ($item['current']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        </div>
                    @else
                        <div class="k-ni k-ni--dd" data-k-dd>
                            @if ($item['url'])
                                <a class="k-nt" href="{{ $item['url'] }}" @if ($item['current']) aria-current="page" @endif>{{ $item['label'] }}</a>
                                <button class="k-chev" type="button" aria-expanded="false" aria-controls="k-mega-{{ $item['key'] }}"
                                        aria-label="{{ __('chome.open_panel', ['section' => $item['label']]) }}" data-k-dd-toggle>{!! $chev !!}</button>
                            @else
                                <button class="k-nt k-nt--btn" type="button" aria-expanded="false" aria-controls="k-mega-{{ $item['key'] }}" data-k-dd-toggle>{{ $item['label'] }}{!! $chev !!}</button>
                            @endif
                            <div class="k-mega" id="k-mega-{{ $item['key'] }}" data-k-mega>
                                <div class="k-mg">
                                    <div class="k-mg__links">
                                        <p class="k-mg__h">{{ $item['label'] }}</p>
                                        @foreach ($panel['links'] as $link)
                                            <a class="k-ml" href="{{ $link['url'] }}">
                                                <b>{{ $link['label'] }}</b>
                                                @if ($link['desc'])<small>{{ $link['desc'] }}</small>@endif
                                            </a>
                                        @endforeach
                                    </div>
                                    <div class="k-mg__related">
                                        <p class="k-mg__h">{{ __('chome.related') }}</p>
                                        @foreach ($panel['related'] as $link)
                                            <a class="k-ml k-ml--plain" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                                        @endforeach
                                    </div>
                                    @if ($f = $panel['feature'])
                                        <a class="k-feat" href="{{ $f['url'] }}">
                                            @if ($f['thumb'])
                                                <span class="k-feat__ph k-feat__ph--{{ $f['kind'] }}"><img src="{{ $f['thumb'] }}" alt="" loading="lazy" decoding="async" width="320" height="180"></span>
                                            @elseif ($f['kind'] === 'event' && ! empty($f['day']))
                                                <span class="k-feat__ph k-feat__ph--date" aria-hidden="true"><b>{{ $f['day'] }}</b><span>{{ $f['month'] }}</span></span>
                                            @else
                                                <span class="k-feat__ph k-feat__ph--mark" aria-hidden="true"><img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="56" height="56"></span>
                                            @endif
                                            <span class="k-lbl">{{ $f['label'] }}</span>
                                            <b>{{ $f['title'] }}</b>
                                            @if ($f['meta'])<small>{{ $f['meta'] }}</small>@endif
                                            <span class="k-all">{{ __('chome.read_more') }} {!! $arrow !!}</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>
</header>

<div class="k-scrim" data-k-drawer-close hidden></div>
<aside class="k-drawer" id="k-drawer" aria-label="{{ __('nav.menu') }}" data-k-drawer hidden>
    <div class="k-d-head" data-surface-tone="dark">
        <b>{{ __('nav.menu') }}</b>
        <button class="k-iconbtn" type="button" aria-label="{{ __('nav.close_menu') }}" data-k-drawer-close>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>
    <div class="k-d-body">
        <div class="k-d-langs" role="group" aria-label="{{ __('nav.language') }}">
            @foreach (\App\Support\Locales::all() as $code => $language)
                <a href="{{ \App\Support\Locales::urlFor($code, request()) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                   @if ($code === $current) aria-current="true" @endif>{{ $language['native'] }}</a>
            @endforeach
        </div>
        <div class="k-d-search">
            <form class="k-qsearch" role="search" action="{{ $searchUrl }}" method="get">
                <label class="c-sr" for="drawer-search">{{ __('nav.search') }}</label>
                <input id="drawer-search" type="search" name="q" placeholder="{{ __('nav.search_placeholder') }}" autocomplete="off">
                <button type="submit" aria-label="{{ __('nav.search') }}">{!! $searchIcon !!}</button>
            </form>
        </div>
        <nav aria-label="{{ __('nav.primary') }}" class="k-d-nav">
            @foreach ($primary as $item)
                @php $panel = $panels[$item['key']] ?? null; @endphp
                @if ($panel === null)
                    <a class="k-d-link" href="{{ $item['url'] }}" @if ($item['current']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @else
                    <div class="k-dacc" data-k-acc>
                        <button type="button" aria-expanded="false" aria-controls="k-dp-{{ $item['key'] }}" data-k-acc-toggle>{{ $item['label'] }}{!! $chev !!}</button>
                        <div class="k-dp" id="k-dp-{{ $item['key'] }}" data-k-acc-panel>
                            <div>
                                @if ($item['url'])
                                    <a class="k-dp__lead" href="{{ $item['url'] }}" @if ($item['current']) aria-current="page" @endif>{{ $item['label'] }}</a>
                                @endif
                                @foreach ($panel['links'] as $link)
                                    <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                                @endforeach
                                @foreach ($panel['related'] as $link)
                                    <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>
    </div>
    <div class="k-d-foot">
        <div class="k-d-btns">
            <a class="k-btn k-btn--ghost" href="{{ $backOfficeUrl }}">{{ __('nav.back_office') }}</a>
            <a class="k-btn k-btn--green" href="{{ $workspace['url'] ?? $extranetUrl }}">{{ $workspace['label'] ?? __('nav.extranet') }}</a>
        </div>
        @auth
            <a class="k-d-link k-d-link--small" href="{{ route('account', ['locale' => $current]) }}">{{ __('nav.account') }}</a>
        @endauth
        @if ($social !== [])
            <ul class="k-d-social" role="list">
                @foreach ($social as $network => $url)
                    <li><a href="{{ $url }}" rel="noopener" data-network="{{ $network }}"
                           aria-label="{{ __('blocks.social_account', ['network' => \App\Support\SocialAccounts::label($network)]) }}"><x-social-mark :network="$network" /></a></li>
                @endforeach
            </ul>
        @endif
    </div>
</aside>
