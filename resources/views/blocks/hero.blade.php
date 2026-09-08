{{--
    Hero, as screen 02 draws it: a full-bleed navy band with the photograph
    behind a gradient scrim, not an image in a rounded box below the
    headline.

    Rotation is a carousel with the accessibility that implies. It pauses on
    hover and on focus, never advances while something inside it has focus,
    is fully keyboard operable, announces slide changes through a polite live
    region, and under prefers-reduced-motion does not rotate at all.

    With one slide none of the controls render: a carousel of one is a
    banner.
--}}
@php
    $slides = collect($block->setting('rotation_items', []));

    // The block's own headline is always the first slide, so a hero with no
    // rotation configured is simply a one-slide carousel.
    $primary = [
        'headline' => $block->translate('headline'),
        'subhead' => $block->translate('subhead'),
        'cta_label' => $block->translate('cta_label'),
        'cta_url' => \App\Support\Navigation::resolve($block->setting('cta_url')),
        'image' => $block->setting('image'),
    ];

    $rendered = collect([$primary])->concat(
        $slides->map(fn ($slide, $i) => [
            'headline' => $block->translate('slide_'.$i.'_headline'),
            'subhead' => $block->translate('slide_'.$i.'_subhead'),
            'cta_label' => $block->translate('slide_'.$i.'_cta_label'),
            'cta_url' => \App\Support\Navigation::resolve($slide['cta_url'] ?? null),
            'image' => $slide['image'] ?? null,
        ])
    )->filter(fn ($s) => $s['headline'] !== null)->values();

    /*
     * Rotation runs only with more than one slide.
     *
     * The library has one usable image and it is not institutional. Screen 02
     * draws the hero with a photograph, so the hero keeps a photograph and
     * the rotation is built and ready; it simply has nothing to rotate.
     * Repeating the same photograph three times to manufacture a slider
     * would be worse than a static slide.
     */
    $multiple = $rendered->count() > 1;
@endphp

<section
    class="b-hero"
    @if ($block->anchor) id="{{ $block->anchor }}" @endif
    @if ($multiple)
        data-hero
        aria-roledescription="carousel"
        aria-label="{{ __('blocks.hero_carousel') }}"
    @endif
>
    @foreach ($rendered as $index => $slide)
        <div
            class="b-hero__slide"
            @if ($multiple)
                data-hero-slide
                role="group"
                aria-roledescription="{{ __('blocks.hero_slide') }}"
                aria-label="{{ __('blocks.hero_slide_of', ['index' => $index + 1, 'total' => $rendered->count()]) }}"
                @if ($index !== 0) hidden @endif
            @endif
        >
            @php $image = $slide['image'] ? \App\Support\ImageLibrary::resolve($slide['image']) : null; @endphp

            @if ($image)
                {{-- Behind the text, not beside it. The scrim below is what
                     keeps the headline readable over a photograph.

                     The image keeps its alt text rather than being marked
                     decorative: screen 02 gives each hero slide an accessible
                     label, and the foundations make alt a required field in
                     all three languages. --}}
                <div class="b-hero__media">
                    <x-responsive-image
                        :name="$slide['image']"
                        :alt="__('images.'.$slide['image'])"
                        sizes="100vw"
                        :loading="$index === 0 ? 'eager' : 'lazy'"
                        class="c-img--cover"
                    />
                </div>
                {{-- Screen 02 draws a colour-blend tint and then the
                     readability scrim, in that order. --}}
                <div class="b-hero__tint" aria-hidden="true"></div>
                <div class="b-hero__scrim" aria-hidden="true"></div>
                <div class="b-hero__weave" aria-hidden="true"></div>
            @endif

            <div class="b-hero__inner">
                <div class="b-hero__copy">
                    @if ($index === 0)
                        <h1 class="b-hero__headline" @if ($slide['headline']->isFallback()) lang="{{ $slide['headline']->locale }}" @endif>
                            {{ $slide['headline']->value }}
                        </h1>
                    @else
                        <p class="b-hero__headline" role="heading" aria-level="2">{{ $slide['headline']->value }}</p>
                    @endif

                    @if ($slide['subhead'])
                        <p class="b-hero__subhead" @if ($slide['subhead']->isFallback()) lang="{{ $slide['subhead']->locale }}" @endif>
                            {{ $slide['subhead']->value }}
                        </p>
                    @endif

                    @if ($slide['cta_label'] && $slide['cta_url'])
                        <div class="b-hero__actions">
                            <a class="c-btn c-btn--on-navy" href="{{ $slide['cta_url'] }}">{{ $slide['cta_label']->value }}</a>
                        </div>
                    @endif
                </div>

                @if (! $image)
                    <div class="b-hero__aside">
                        @include('blocks._slot', ['note' => __('blocks.hero_slot_note'), 'ratio' => '3:2'])
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if (! $rendered->count())
        <div class="b-hero__inner">
            <div class="b-hero__copy">
                @include('blocks._empty', [
                    'title' => __('blocks.hero_empty_title'),
                    'body' => __('blocks.hero_empty_body'),
                ])
            </div>
        </div>
    @endif

    @if ($multiple)
        <div class="b-hero__controls">
            <button type="button" class="b-hero__arrow" data-hero-prev aria-label="{{ __('blocks.hero_previous') }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6"></path>
                </svg>
            </button>

            <div class="b-hero__dots" role="tablist" aria-label="{{ __('blocks.hero_choose') }}">
                @foreach ($rendered as $index => $slide)
                    <button
                        type="button"
                        class="b-hero__dot"
                        data-hero-dot="{{ $index }}"
                        role="tab"
                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                        aria-label="{{ __('blocks.hero_slide_of', ['index' => $index + 1, 'total' => $rendered->count()]) }}"
                    ></button>
                @endforeach
            </div>

            <button type="button" class="b-hero__arrow" data-hero-next aria-label="{{ __('blocks.hero_next') }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M9 6l6 6-6 6"></path>
                </svg>
            </button>

            <button type="button" class="b-hero__pause" data-hero-pause aria-pressed="false"
                    data-label-pause="{{ __('blocks.hero_pause') }}"
                    data-label-play="{{ __('blocks.hero_play') }}">
                <span data-hero-pause-label>{{ __('blocks.hero_pause') }}</span>
            </button>
        </div>

        {{-- Slide changes are announced here, politely, so a screen reader
             is told what happened without being interrupted. --}}
        <div class="c-sr" aria-live="polite" data-hero-live></div>
    @endif
</section>
