{{--
    Sign-in family card (UI system, 29 October 2026): centred, the logo, the
    form, one line of help, and a side panel saying what the account opens.
--}}
@props(['title', 'intro' => null, 'help' => null, 'side' => true])
@include('partials.cpages.styles')
<section class="cp-auth" aria-labelledby="cp-auth-title">
    <div class="u-container">
        <div class="cp-auth__card {{ $side ? 'cp-auth__card--split' : '' }}">
            <div class="cp-auth__main">
                <a class="cp-auth__brand" href="{{ route('home', ['locale' => app()->getLocale()]) }}">
                    <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="56" height="56">
                    <span>CRASA</span>
                </a>
                <h1 class="cp-auth__title" id="cp-auth-title">{{ $title }}</h1>
                @if ($intro)<p class="cp-auth__intro">{{ $intro }}</p>@endif
                {{ $slot }}
                @if ($help)<p class="u-help cp-auth__help">{{ $help }}</p>@endif
            </div>
            @if ($side)
                <aside class="cp-auth__side" aria-labelledby="cp-auth-side-title">
                    <h2 class="u-h3" id="cp-auth-side-title">{{ __('cpages_c.auth_side_title') }}</h2>
                    <ul class="cp-auth__list" role="list">
                        @foreach (['returns', 'committees', 'calendar', 'library'] as $item)
                            <li>
                                <svg class="cp-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                <span>{{ __('cpages_c.auth_side_'.$item) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="u-help">{{ __('cpages_c.auth_side_access') }}</p>
                    <a class="u-btn u-btn--secondary" href="{{ route('contact', ['locale' => app()->getLocale()]) }}">{{ __('cpages_c.auth_side_contact') }}</a>
                </aside>
            @endif
        </div>
    </div>
</section>
