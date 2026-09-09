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
        'eyebrow' => $block->translate('eyebrow'),
        'headline' => $block->translate('headline'),
        'subhead' => $block->translate('subhead'),
        'cta_label' => $block->translate('cta_label'),
        'cta_url' => \App\Support\Navigation::resolve($block->setting('cta_url')),
        'image' => $block->setting('image'),
        'panel' => $block->setting('panel', 'figures'),
    ];

    $rendered = collect([$primary])->concat(
        $slides->map(fn ($slide, $i) => [
            'eyebrow' => $block->translate('slide_'.$i.'_eyebrow'),
            'headline' => $block->translate('slide_'.$i.'_headline'),
            'subhead' => $block->translate('slide_'.$i.'_subhead'),
            'cta_label' => $block->translate('slide_'.$i.'_cta_label'),
            'cta_url' => \App\Support\Navigation::resolve($slide['cta_url'] ?? null),
            'image' => $slide['image'] ?? null,
            'panel' => $slide['panel'] ?? null,
        ])
    )->filter(fn ($s) => $s['headline'] !== null)->values();

    /*
     * The panel each slide carries, built before the markup so a slide whose
     * panel has no data to draw is dropped rather than rendered empty.
     *
     * Screen 02 draws two kinds of slide, one with a photograph behind a
     * scrim and one with a data panel in the second column. There are no
     * photographs yet, so all three are the second kind and each states
     * something different: what the register holds, what two member states
     * report against one indicator, and what CRASA published last.
     *
     * A carousel of near-identical slides is worse than no carousel. These
     * differ because the content differs, which is the only reason a
     * carousel is ever the right control.
     */
    $panels = [
        'figures' => \App\Support\HeroPanel::build(),
        'comparison' => \App\Support\HeroPanel::comparison(),
        'publication' => \App\Support\HeroPanel::publication(),
    ];

    $hasPanel = fn (?string $kind) => match ($kind) {
        'figures' => ($panels['figures']['bars'] ?? []) !== [],
        'comparison' => $panels['comparison'] !== null,
        'publication' => $panels['publication'] !== null,
        default => false,
    };

    /*
     * Slides two and three exist only to carry their panel. If the register
     * cannot supply a comparison, or the library is empty, they have nothing
     * to say and are dropped: a stripped database serves one slide and a
     * banner rather than three slides and two empty boxes.
     *
     * The first slide is not conditional. Its copy is the content and the
     * figures panel is an addition to it, so it survives a database with no
     * indicators in it at all.
     */
    $rendered = $rendered->reject(
        fn ($s) => in_array($s['panel'], ['comparison', 'publication'], true) && ! $hasPanel($s['panel'])
    )->values();

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
                {{-- "Slide 2 of 3: featured comparison". A dot labelled
                     only by its number tells a reader how far along they
                     are and nothing about where they would land. --}}
                aria-label="{{ __('blocks.hero_slide_of', [
                    'index' => $index + 1,
                    'total' => $rendered->count(),
                    'name' => __('blocks.hero_panel_'.($slide['panel'] ?? 'figures').'_name'),
                ]) }}"
                @if ($index !== 0) hidden @endif
            @endif
        >
            @php
                // Two kinds of image can sit in this slot: a slot name from
                // the supplied art-directed library, or an asset the
                // Secretariat uploaded. Both are strings in one setting, so
                // the reference prefix is what tells them apart.
                $isUpload = \App\Support\MediaReference::isReference($slide['image']);
                $image = $isUpload
                    ? \App\Support\MediaReference::resolve($slide['image'])
                    : ($slide['image'] ? \App\Support\ImageLibrary::resolve($slide['image']) : null);
            @endphp

            @if ($image)
                {{-- Behind the text, not beside it. The scrim below is what
                     keeps the headline readable over a photograph.

                     The image keeps its alt text rather than being marked
                     decorative: screen 02 gives each hero slide an accessible
                     label, and the foundations make alt a required field in
                     all three languages. --}}
                <div class="b-hero__media">
                    @if ($isUpload)
                        <x-media-image
                            :reference="$slide['image']"
                            sizes="100vw"
                            :width="1600"
                            :loading="$index === 0 ? 'eager' : 'lazy'"
                            class="c-img--cover"
                        />
                    @else
                        <x-responsive-image
                            :name="$slide['image']"
                            :alt="__('images.'.$slide['image'])"
                            sizes="100vw"
                            :loading="$index === 0 ? 'eager' : 'lazy'"
                            class="c-img--cover"
                        />
                    @endif
                </div>
                {{-- Screen 02 draws a colour-blend tint and then the
                     readability scrim, in that order. --}}
                <div class="b-hero__tint" aria-hidden="true"></div>
                <div class="b-hero__scrim" aria-hidden="true"></div>
                <div class="b-hero__weave" aria-hidden="true"></div>
            @else
                {{-- No photograph, and that is a composition rather than a
                     gap. Screen 02 draws a slide with no photograph of its
                     own — its chart slide — and what it puts on the navy is
                     the weave alone:

                       repeating-linear-gradient(58deg,rgba(26,140,90,.09) 0 2px,transparent 2px 24px)

                     The navy ground and the type are the design. The
                     headline runs at its own measure because the grid is
                     auto-fit and there is nothing beside it to share the
                     row with, which is the screen's own layout with one
                     column rather than a variation on it. --}}
                <div class="b-hero__weave" aria-hidden="true"></div>
            @endif

            <div class="b-hero__inner">
                <div class="b-hero__copy">
                    @if ($slide['eyebrow'])
                        {{-- Sentence case. Screen 02 sets this in tracked
                             caps; the ruling in CLAUDE.md excludes section
                             eyebrows and wins. Everything else the screen
                             sets is kept: 13px, weight 600, --c-green-light,
                             margin-bottom var(--s-5). Disclosed variation,
                             logged in docs/OPEN_ITEMS.md. --}}
                        <p class="b-hero__eyebrow" @if ($slide['eyebrow']->isFallback()) lang="{{ $slide['eyebrow']->locale }}" @endif>
                            {{ $slide['eyebrow']->value }}
                        </p>
                    @endif

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

                @if (! $image && $hasPanel($slide['panel']))
                    {{-- The screen's own answer to a slide with no
                         photograph. Screen 02 fills the second column with:

                           <div style="min-width:0;background:var(--c-navy-mid);
                                       border:1px solid #2C3A55;
                                       border-radius:var(--r-md);padding:var(--s-6)">

                         Leaving it empty was leaving out the thing that
                         stops 640px of flat navy reading as a container
                         waiting for content, and it read thinnest at laptop
                         widths where the copy has stopped filling the row.

                         Still no placeholder box. A grey panel saying
                         "photograph to follow" on the first screen of a
                         treaty body's site tells a regulator the site is
                         unfinished. This one carries figures instead. --}}
                    @php $panel = $panels['figures']; @endphp

                    @if ($slide['panel'] === 'figures')
                        <div class="b-hero__panel">
                            {{-- Screen 02 sets this in tracked caps. The
                                 ruling in CLAUDE.md excludes eyebrows and
                                 wins, exactly as it does for the hero
                                 eyebrow above. Everything else the screen
                                 sets is kept. Logged in docs/OPEN_ITEMS.md. --}}
                            <p class="b-hero__panel-title">{{ __('blocks.hero_panel_title') }}</p>
                            <p class="b-hero__panel-note">{{ __('blocks.hero_panel_note') }}</p>

                            <div class="b-hero__bars">
                                @foreach ($panel['bars'] as $bar)
                                    <div class="b-hero__bar">
                                        <div class="b-hero__bar-head">
                                            <span class="b-hero__bar-name">{{ $bar['name'] }}</span>
                                            {{-- The figure as text. A bar a
                                                 reader cannot read a number
                                                 off is a picture of a
                                                 proportion. --}}
                                            <span class="b-hero__bar-value">{{ $bar['value'] }}</span>
                                        </div>
                                        <div class="b-hero__bar-track">
                                            <span class="b-hero__bar-fill" style="width: {{ $bar['percent'] }}%"></span>
                                        </div>
                                        <span class="c-sr">{{ __('blocks.hero_bar_detail', [
                                            'sector' => $bar['name'],
                                            'filed' => $bar['filed'],
                                            'expected' => $bar['expected'],
                                        ]) }}</span>
                                    </div>
                                @endforeach
                            </div>

                            @if ($panel['counts'] !== [])
                                <dl class="b-hero__counts">
                                    @foreach ($panel['counts'] as $count)
                                        <div>
                                            <dd>
                                                @if ($count['url'])
                                                    <a href="{{ $count['url'] }}">{{ $count['value'] }}</a>
                                                @else
                                                    {{ $count['value'] }}
                                                @endif
                                            </dd>
                                            <dt>{{ $count['label'] }}</dt>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif

                            @if ($panel['sample'])
                                {{-- Screen 02 draws this band inside the
                                     panel: rgba(194,113,12,.14) ground, a
                                     4px amber left edge. One of three such
                                     edges on the platform, and each one
                                     stops work. --}}
                                <p class="b-hero__panel-notice">
                                    <strong>{{ __('indicators.demo_title') }}</strong>
                                    {{ __('blocks.hero_panel_demo') }}
                                </p>
                            @endif
                        </div>
                    @elseif ($slide['panel'] === 'comparison')
                        @php $comparison = $panels['comparison']; @endphp
                        {{-- The same panel shell as the figures slide: the
                             screen draws one panel, not three. What changes
                             inside it is the content. --}}
                        <div class="b-hero__panel">
                            <p class="b-hero__panel-title">{{ __('blocks.hero_comparison_title') }}</p>
                            <p class="b-hero__panel-note">{{ $comparison['indicator'] }}@if ($comparison['period']), {{ $comparison['period'] }}@endif</p>

                            <table class="b-hero__table">
                                <caption class="c-sr">{{ $comparison['indicator'] }}</caption>
                                <tbody>
                                    @foreach ($comparison['rows'] as $row)
                                        <tr>
                                            <th scope="row">
                                                <span class="b-hero__code" aria-hidden="true">{{ $row['code'] }}</span>
                                                {{ $row['country'] }}
                                            </th>
                                            <td>{{ $row['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <p class="b-hero__panel-action">
                                <a href="{{ $comparison['url'] }}">{{ __('blocks.hero_comparison_action') }}</a>
                            </p>

                            @if ($comparison['sample'])
                                <p class="b-hero__panel-notice">
                                    <strong>{{ __('indicators.demo_title') }}</strong>
                                    {{ __('blocks.hero_panel_demo') }}
                                </p>
                            @endif
                        </div>
                    @elseif ($slide['panel'] === 'publication')
                        @php $latest = $panels['publication']; @endphp
                        <div class="b-hero__panel">
                            <p class="b-hero__panel-title">{{ __('blocks.hero_publication_title') }}</p>

                            <p class="b-hero__latest-kind">{{ $latest['kind'] }}</p>
                            <p class="b-hero__latest-title">{{ $latest['title'] }}</p>
                            <p class="b-hero__panel-note">
                                {{ collect([$latest['year'], $latest['format'], $latest['size']])->filter()->implode(', ') }}
                            </p>

                            <p class="b-hero__panel-action">
                                <a href="{{ $latest['url'] }}">{{ __('blocks.hero_publication_action') }}</a>
                            </p>

                            @if ($latest['sample'])
                                <p class="b-hero__panel-notice">
                                    <strong>{{ __('indicators.demo_title') }}</strong>
                                    {{ __('blocks.hero_panel_demo') }}
                                </p>
                            @endif
                        </div>
                    @endif
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
                        aria-label="{{ __('blocks.hero_slide_of', [
                            'index' => $index + 1,
                            'total' => $rendered->count(),
                            'name' => __('blocks.hero_panel_'.($slide['panel'] ?? 'figures').'_name'),
                        ]) }}"
                    >{{ $index + 1 }}</button>
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

            {{-- Screen 02 closes the bar with a counter in --f-admin at
                 13.5px and a note on the state, pushed right by
                 margin-left:auto. The dots say which slide is current by
                 shape; the counter says it in figures, which is what a
                 reader glances at rather than counts. --}}
            <p class="b-hero__counter" data-hero-counter aria-hidden="true">1 / {{ $rendered->count() }}</p>
            <p class="b-hero__state" data-hero-state
               data-label-rotating="{{ __('blocks.hero_state_rotating') }}"
               data-label-paused="{{ __('blocks.hero_state_paused') }}"
               data-label-still="{{ __('blocks.hero_state_still') }}"
            >{{ __('blocks.hero_state_rotating') }}</p>
        </div>

        {{-- Slide changes are announced here, politely, so a screen reader
             is told what happened without being interrupted. --}}
        <div class="c-sr" aria-live="polite" data-hero-live></div>
    @endif
</section>
