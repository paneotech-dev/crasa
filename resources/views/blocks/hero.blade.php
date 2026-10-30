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
        'caption' => $block->translate('caption'),
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
            'caption' => $block->translate('slide_'.$i.'_caption'),
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
                            <a class="c-btn c-btn--primary" href="{{ $slide['cta_url'] }}">{{ $slide['cta_label']->value }}</a>
                        </div>
                    @endif
                </div>

                {{-- The photograph sits in a frame beside the text, never
                     behind it: no tint, no scrim, nothing laid over a
                     picture of the people CRASA works with. Only the first
                     slide's image is fetched with the page; the others are
                     lazy inside hidden slides, so they load when shown. --}}
                @if ($image)
                    <figure class="b-hero__frame">
                        <div class="b-hero__photo">
                            @if ($isUpload)
                                <x-media-image
                                    :reference="$slide['image']"
                                    sizes="(min-width: 992px) 560px, 100vw"
                                    :width="1600"
                                    :loading="$index === 0 ? 'eager' : 'lazy'"
                                    :fetchpriority="$index === 0 ? 'high' : null"
                                    class="c-img--cover"
                                />
                            @else
                                <x-responsive-image
                                    :name="$slide['image']"
                                    :alt="__('images.'.$slide['image'])"
                                    sizes="(min-width: 992px) 560px, 100vw"
                                    :loading="$index === 0 ? 'eager' : 'lazy'"
                                    class="c-img--cover"
                                />
                            @endif
                        </div>
                        @if ($slide['caption'])
                            <figcaption class="b-hero__caption" @if ($slide['caption']->isFallback()) lang="{{ $slide['caption']->locale }}" @endif>{{ $slide['caption']->value }}</figcaption>
                        @endif
                    </figure>
                @endif

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

                            {{-- The demonstration notice was here. Screen 02
                                 draws it inside the panel on an amber
                                 ground with a 4px left edge. Removed on a
                                 ruling of 9 September 2026: flagged in the
                                 database, disclosed verbally, not badged on
                                 the page. OPEN_ITEMS.md. --}}
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
                                {{-- Column headers for assistive technology; the panel
                                     title and note above say the same to sight. --}}
                                <thead class="b-hero__table-head">
                                    <tr>
                                        <th scope="col"><span class="c-sr">{{ __('blocks.member_state') }}</span></th>
                                        <th scope="col"><span class="c-sr">{{ __('blocks.value') }}</span></th>
                                    </tr>
                                </thead>
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
                    'editorOnly' => true,
                'title' => __('blocks.hero_empty_title'),
                    'body' => __('blocks.hero_empty_body'),
                ])
            </div>
        </div>
    @endif

    @if ($multiple)
        {{-- THE CONTROLS ARE AN OVERLAY, NOT A STRIP.

             Screen 02 line 152 draws them as a full-width bar across the
             bottom of the band, "background:rgba(11,21,34,.86);
             border-top:1px solid #2C3A55". Overruled on 9 September 2026
             and logged as a disclosed variation: the strip put a fourth
             horizontal band in front of the reader before any content
             began, and it cut the hero off from the figures band it
             belongs with. Every control the screen draws is kept. They
             move onto the hero as one compact group.

             Bottom left, aligned to the content column's left edge, so the
             group sits under the headline rather than floating in the
             corner. That is also where the scrim is at its darkest
             (rgba(15,27,45,.94) at 0%), which is what makes the pill
             behind them work over an unknown photograph. --}}
        <div class="b-hero__controls">
            <div class="b-hero__pill">
                <button type="button" class="b-hero__ctl" data-hero-prev aria-label="{{ __('blocks.hero_previous') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M15 6l-6 6 6 6"></path>
                    </svg>
                </button>

                {{-- No visible numbers. The dot's shape says which slide is
                     current and its accessible name says which slide it is
                     and what is on it: "Slide 2 of 3: featured comparison".
                     A dot labelled by its number alone tells a reader how
                     far along they are and nothing about where they would
                     land. --}}
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
                        ></button>
                    @endforeach
                </div>

                <button type="button" class="b-hero__ctl" data-hero-next aria-label="{{ __('blocks.hero_next') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M9 6l6 6-6 6"></path>
                    </svg>
                </button>

                {{-- Icon only, so there is no visible label and SC 2.5.3 has
                     nothing to match: the accessible name is free to say
                     what is being paused rather than repeating one word.
                     aria-pressed carries the state, and the icon follows it
                     in CSS rather than in script, so the two cannot drift.

                     The counter that closed the screen's bar is gone with
                     the bar. Three dots already say which of three slides
                     is current, and a figure beside them says it twice. --}}
                <button type="button" class="b-hero__ctl b-hero__pause" data-hero-pause aria-pressed="false"
                        aria-label="{{ __('blocks.hero_pause_action') }}"
                        data-aria-pause="{{ __('blocks.hero_pause_action') }}"
                        data-aria-play="{{ __('blocks.hero_play_action') }}">
                    <svg class="b-hero__icon--pause" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <rect x="7" y="5" width="3.5" height="14" rx="1"></rect>
                        <rect x="13.5" y="5" width="3.5" height="14" rx="1"></rect>
                    </svg>
                    <svg class="b-hero__icon--play" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M8 5.5l11 6.5-11 6.5z"></path>
                    </svg>
                </button>
            </div>
        </div>
        {{-- Slide changes are announced here, politely, so a screen reader
             is told what happened without being interrupted. --}}
        <div class="c-sr" aria-live="polite" data-hero-live></div>
    @endif
</section>
