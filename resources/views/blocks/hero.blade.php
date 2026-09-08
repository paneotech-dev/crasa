{{-- Hero. Rotation is a user action, never an autoplaying carousel. --}}
@php
    $headline = $block->translate('headline');
    $subhead = $block->translate('subhead');
    $ctaLabel = $block->translate('cta_label');
    $ctaUrl = $block->setting('cta_url');
    $image = $block->setting('image');
@endphp

<section class="b-hero" @if ($headline?->isFallback()) lang="{{ $headline->locale }}" @endif>
    <div class="b-hero__inner">
        @if ($headline)
            <h1 class="b-hero__headline">{{ $headline->value }}</h1>
        @endif

        @if ($subhead)
            <p class="b-hero__subhead" @if ($subhead->isFallback()) lang="{{ $subhead->locale }}" @endif>{{ $subhead->value }}</p>
        @endif

        @if ($ctaLabel && $ctaUrl)
            <div class="b-hero__actions">
                <a class="c-btn c-btn--on-navy" href="{{ $ctaUrl }}">{{ $ctaLabel->value }}</a>
            </div>
        @endif

        @if (! $headline)
            <div class="b-hero__actions">
                @include('blocks._empty', [
                    'title' => __('blocks.hero_empty_title'),
                    'body' => __('blocks.hero_empty_body'),
                ])
            </div>
        @endif

        @if ($image && \App\Support\ImageLibrary::resolve($image))
            <figure class="b-hero__figure">
                <x-responsive-image
                    :name="$image"
                    :alt="__('images.'.$image)"
                    sizes="(max-width: 1440px) 100vw, 1440px"
                    loading="eager"
                    class="c-img--cover"
                />
            </figure>
        @else
            <div class="b-hero__media">
                @include('blocks._slot', ['note' => __('blocks.hero_slot_note'), 'ratio' => '21:9'])
            </div>
        @endif
    </div>
</section>
