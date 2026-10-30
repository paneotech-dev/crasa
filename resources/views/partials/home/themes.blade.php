{{--
    Thematic areas band on the home page (30 October 2026), after "CRASA in
    figures": one card per theme (Bootstrap Icon and short title) in a row
    that scrolls by itself.

    The first list holds the real links, each reachable by keyboard once.
    The second is a copy that only closes the loop: hidden from assistive
    technology, with no links and nothing focusable. The row pauses on
    hover, on keyboard focus and with the pause button; it does not move
    at all under prefers-reduced-motion or the "Pause animations" display
    option (html.a11y-pause), where the cards wrap instead (public/css/themes.css).
--}}
@php
    $homeThemes = \App\Support\Themes::all();
    $themeUrl = fn ($slug) => route('themes.show', ['locale' => app()->getLocale(), 'theme' => $slug]);
@endphp
@if ($homeThemes !== [])
@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/themes.css') }}">
@endpush
@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/themes.js') }}" defer></script>
@endpush
<section class="th-band" id="themes" aria-labelledby="th-band-h">
    <div class="k-wrap th-band__head">
        <h2 id="th-band-h">{{ __('d3_themes.home_heading') }}</h2>
        <div class="th-band__tools">
            <button class="th-toggle" type="button" data-th-toggle hidden
                    data-label-pause="{{ __('d3_themes.home_pause') }}" data-label-play="{{ __('d3_themes.home_play') }}">{{ __('d3_themes.home_pause') }}</button>
            <a class="k-all" href="{{ route('themes', ['locale' => app()->getLocale()]) }}">{{ __('d3_themes.home_all') }} <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
    <div class="th-marquee" data-th-marquee>
        <div class="th-track">
            <ul class="th-list" role="list">
                @foreach ($homeThemes as $theme)
                    <li><a class="th-card" href="{{ $themeUrl($theme['slug']) }}"><span class="th-card__ico">{{ \App\Support\Themes::icon($theme['icon']) }}</span><span class="th-card__t">{{ $theme['title'] }}</span></a></li>
                @endforeach
            </ul>
            <ul class="th-list th-list--copy" role="list" aria-hidden="true">
                @foreach ($homeThemes as $theme)
                    <li><span class="th-card"><span class="th-card__ico">{{ \App\Support\Themes::icon($theme['icon']) }}</span><span class="th-card__t">{{ $theme['title'] }}</span></span></li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
@endif
