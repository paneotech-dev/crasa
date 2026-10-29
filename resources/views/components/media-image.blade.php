{{--
    An image from the media library.

    Separate from x-responsive-image, which serves the art-directed supplied
    library by slot name. This one serves an uploaded asset by reference and
    enforces the rule screen 14 states in its own alt-text field: "Required.
    This asset cannot appear on the Portuguese site until it is filled."

    So an asset with no alt text in the language being rendered renders
    nothing at all. Not an empty alt attribute, which would tell a screen
    reader the image is decorative when it is not, and not the English,
    which would be a sentence in the wrong language read out mid-page.
--}}
@props([
    'reference',
    'sizes' => '100vw',
    'loading' => 'lazy',
    'class' => '',
    'width' => 1200,
    'fetchpriority' => null,
])
@php
    $asset = \App\Support\MediaReference::resolve($reference);
    $locale = app()->getLocale();
    $alt = $asset?->mayAppearIn($locale)
        ? $asset->translationValuesFor('alt')[$locale]
        : null;
@endphp

@if ($asset && $alt !== null && $asset->url($width))
    <picture>
        <img
            src="{{ $asset->url($width) }}"
            srcset="{{ \App\Support\MediaReference::srcset($asset) }}"
            sizes="{{ $sizes }}"
            alt="{{ $alt }}"
            @if ($asset->width) width="{{ $asset->width }}" @endif
            @if ($asset->height) height="{{ $asset->height }}" @endif
            loading="{{ $loading }}"
            @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
            decoding="async"
            @class(['c-img', $class])
        >
    </picture>
@endif
