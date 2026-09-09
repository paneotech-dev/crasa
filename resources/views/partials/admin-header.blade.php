{{--
    Screen 09 header, quoted:

      background:var(--c-white);border-bottom:1px solid var(--c-line);
      padding:var(--s-3) var(--s-6);display:flex;align-items:center;
      gap:var(--s-5);flex-wrap:wrap

    with the logo and wordmark on the left, the section bar beside it, and
    the interface language, theme and identity pushed right by margin-left:auto.

    The nav renders only the sections this user can open. That is a
    convenience, not the access control: every route re-checks its policy.
--}}
@php
    $user = auth()->user();
    $sections = \App\Support\AdminNavigation::sections();
    $theme = $user?->theme ?? \App\Enums\Theme::System;
@endphp

<header class="a-header">
    <a class="a-header__identity" href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}">
        <img src="{{ asset('img/crasa-logo.svg') }}" alt="">
        <span class="a-header__wordmark">{{ __('admin.back_office') }}</span>
    </a>

    <nav class="a-nav" aria-label="{{ __('admin.sections') }}">
        @foreach ($sections as $section)
            <a class="a-nav__link"
               href="{{ $section['url'] }}"
               @if ($section['current']) aria-current="page" @endif>{{ $section['label'] }}</a>
        @endforeach
    </nav>

    <div class="a-header__utilities">
        {{-- Interface: English. The globe is Lucide, one library. --}}
        <a class="a-chip" href="{{ \App\Support\Locales::urlFor(\App\Support\Locales::next(app()->getLocale()), request()) }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 010 18"/></svg>
            {{ __('admin.interface', ['language' => \App\Support\Locales::nameOf(app()->getLocale())]) }}
        </a>

        {{-- Theme. Screen 09: "The Secretariat sets it per account, and it
             follows the operating system by default." A form, because it
             writes to the account. --}}
        <form method="POST" action="{{ route('account.theme', ['locale' => app()->getLocale()]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="theme" value="{{ $theme->next()->value }}">
            <button type="submit" class="a-chip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 3v2"/><path d="M12 19v2"/><path d="M3 12h2"/><path d="M19 12h2"/></svg>
                {{ __('admin.theme_'.$theme->value) }}
            </button>
        </form>

        <a class="a-chip" href="{{ route('admin.help', ['locale' => app()->getLocale()]) }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.6 2.6 0 015 .9c0 1.7-2.5 2.1-2.5 3.6"/><path d="M12 17h.01"/></svg>
            {{ __('help.title') }}
        </a>

        <span class="a-identity">
            <span class="a-identity__initials" aria-hidden="true">{{ \App\Support\Initials::of($user?->name ?? '') }}</span>
            <span class="a-identity__text">
                <a class="a-identity__name" href="{{ route('account', ['locale' => app()->getLocale()]) }}">{{ $user?->name }}</a>
                <span class="a-identity__where">{{ $user?->authority?->name ?? __('admin.secretariat') }}</span>
            </span>
        </span>
    </div>
</header>
