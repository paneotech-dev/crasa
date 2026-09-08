{{--
    One image slot, art-directed.

    Each breakpoint gets its own crop, not just its own width: the hero is a
    21:9 band at desktop and 4:3 at phone, because a wide band on a phone is
    a strip of sky with the subject cropped out of it. That needs media-gated
    sources — a single srcset would tell the browser these are the same image
    at different sizes and let it pick a crop the layout was not built for.

    Never the archive original: the best of those is 20 MB.

    Alt text is required. There is no default, and a slot with no alt text
    does not render, because an image nobody described is worse than no
    image.
--}}
@props([
    'name',
    'alt',
    'sizes' => '100vw',
    'loading' => 'lazy',
    'class' => '',
])
@php
    $image = \App\Support\ImageLibrary::resolve($name);
@endphp

@if ($image && filled($alt))
    <picture>
        @foreach ($image['sources'] as $source)
            <source
                type="{{ $source['type'] }}"
                srcset="{{ $source['srcset'] }}"
                @if ($source['media']) media="{{ $source['media'] }}" @endif
                sizes="{{ $sizes }}"
            >
        @endforeach
        <img
            src="{{ $image['src'] }}"
            alt="{{ $alt }}"
            loading="{{ $loading }}"
            decoding="async"
            @class(['c-img', $class])
        >
    </picture>
@endif
