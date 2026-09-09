{{--
    The sector's own mark, for the panel a card falls back to when the
    library has no photograph for that sector.

    Screen 01's iconography rules: one set, inline SVG on a 24px grid, 2px
    stroke, round caps and joins, no fills, and never carrying meaning on
    its own. Here the sector's name sits directly beneath it.

    Broadcasting has no photograph in the library at all, so this is the
    surface it gets. A word alone on navy where its neighbours have
    photographs reads as the card that failed to load.
--}}
@props(['sector' => null])

@php
    $paths = match ($sector) {
        // A mast radiating: telecommunications.
        'telecommunications' => ['M12 20v-8', 'M8.5 12a5 5 0 017 0', 'M5.5 12a9 9 0 0113 0', 'M12 9.5a1.2 1.2 0 010 .01'],
        // A broadcast tower with waves either side.
        'broadcasting' => ['M12 21V9', 'M8 21h8', 'M7.5 6.5a6 6 0 019 0', 'M4.5 3.5a10 10 0 0115 0'],
        // A parcel.
        'postal' => ['M4 8l8-4 8 4v8l-8 4-8-4z', 'M4 8l8 4 8-4', 'M12 12v8'],
        default => ['M4 6h16', 'M4 12h16', 'M4 18h10'],
    };
@endphp

<svg class="c-sector-icon" width="28" height="28" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    @foreach ($paths as $d)
        <path d="{{ $d }}"></path>
    @endforeach
</svg>
