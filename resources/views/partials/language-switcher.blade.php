{{--
    Trilingual switcher. Three links, so the language changes without
    JavaScript and the reader stays on the page they were reading.

    The current language is always shown as text, never as a flag alone.
    Each control carries a 44px minimum target and the endonym as its
    accessible name.

    $variant: 'header' on a light surface, 'drawer' on navy.
--}}
@php
    $variant ??= 'header';
    $current = app()->getLocale();
@endphp

<div
    class="{{ $variant === 'drawer' ? 'c-drawer__lang' : 'c-lang' }}"
    role="group"
    aria-label="{{ __('nav.language') }}"
>
    @foreach (\App\Support\Locales::all() as $code => $language)
        <a
            class="c-lang__option"
            href="{{ \App\Support\Locales::urlFor($code, request()) }}"
            lang="{{ $code }}"
            hreflang="{{ $code }}"
            @if ($code === $current) aria-current="true" @endif
        >
            {{ $language['short'] }}
            <span class="c-sr">
                @if ($code === $current)
                    {{ $language['native'] }}
                @else
                    {{ __('site.switch_to', ['language' => __('site.languages.'.$code)]) }}
                @endif
            </span>
        </a>
    @endforeach
</div>
