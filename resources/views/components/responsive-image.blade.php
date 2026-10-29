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

    One exception, and it has to be asked for by name. A photograph behind a
    heading at 82 per cent overlay carries nothing the heading does not
    already say, and WCAG H67 wants exactly alt="" on it so a screen reader
    passes it over rather than reading a description before every page
    title. `decorative` is that case.

    It is a separate prop rather than an empty alt because an empty alt is
    also what you get from a caller who simply forgot. The section band
    passed alt="" for months and rendered no image at all: the guard was
    doing its job on a caller that had a legitimate reason and no way to say
    so.
--}}
@props([
    'name',
    'alt' => '',
    'decorative' => false,
    'sizes' => '100vw',
    'loading' => 'lazy',
    'fetchpriority' => null,
    'class' => '',
])
@php
    $image = \App\Support\ImageLibrary::resolve($name);
@endphp

@if ($image && ($decorative || filled($alt)))
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
            alt="{{ $decorative ? '' : $alt }}"
            loading="{{ $loading }}"
            @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
            decoding="async"
            @class(['c-img', $class])
        >
    </picture>
@endif
