{{--
    One image slot, served as width-stepped derivatives with a WebP source
    and a JPEG fallback. Never the archive original: the best of those is
    20 MB, which is not something to send a reader on a mobile connection.

    A slot with no image renders nothing here; the calling block renders the
    designed absence instead.

    Alt text is required in all three languages. There is no default: an
    image without alt text does not render.
--}}
@props([
    // Not "slot": that name is reserved by Blade for a component's content
    // slot, and a prop of that name is silently swallowed.
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
        @if ($image['webpsrcset'] !== '')
            <source type="image/webp" srcset="{{ $image['webpsrcset'] }}" sizes="{{ $sizes }}">
        @endif
        <img
            src="{{ $image['src'] }}"
            srcset="{{ $image['srcset'] }}"
            sizes="{{ $sizes }}"
            alt="{{ $alt }}"
            loading="{{ $loading }}"
            decoding="async"
            @class(['c-img', $class])
        >
    </picture>
@endif
