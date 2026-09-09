{{--
    The fourteen member states.

    Card widths and heights follow content. A member state with four
    committees carries more text than one with a single committee, and the
    grid absorbs that rather than forcing both into the same box.
--}}
@php
    $heading = $block->translate('heading');
    $intro = $block->translate('intro');
    $countries = $countries ?? \App\Models\Country::with(['authority.sectors', 'committees'])->orderBy('name')->get();
    // Asked once for the whole grid, not once per card. See
    // Committee::seatsRecorded() for why the count is withheld at all.
    $seatsKnown = \App\Models\Committee::seatsRecorded();
@endphp

<section class="b" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif
        @if ($intro)
            <p class="b__intro">{{ $intro->value }}</p>
        @endif

        {{-- The map is the page's one bold moment; the list below it stays
             quiet. It renders only when the block asks for it, so the same
             component can appear on the homepage without it.

             Comoros and Mauritius are inset chips rather than mainland
             shapes: at region scale each is a few pixels across, too small
             to see, label or touch. A member state omitted from a
             membership map is a worse outcome than an inset. --}}
        @php
            /*
             * Screen 08's layout, on the page whose subject is the members.
             *
             * "display:flex;flex-wrap:wrap;gap:var(--s-7);align-items:
             *  flex-start", the map in a white card at flex:1 1 520px and
             * the directory beside it at flex:1 1 400px. The map was
             * floating on the bare section ground with a grid of cards
             * under it, which is neither what screen 08 draws nor a
             * composition that tells a reader the two are alternatives.
             *
             * A setting rather than a guess: the homepage carries the same
             * block as a summary and keeps its card grid.
             */
            $directory = (bool) $block->setting('directory', false);

            /*
             * The homepage's own composition, screen 02 line 604. Two
             * columns: the claim and a small static map on the left, a grid
             * of fourteen tiles on the right.
             *
             * It was rendering the members page's full apparatus here --
             * two selects, the interactive map, the legend, the export
             * buttons and a grid of cards -- which is a page's worth of
             * controls inside a homepage summary.
             */
            $summary = (bool) $block->setting('summary', false);
        @endphp

        <div @class(['b-members__layout' => $directory, 'b-members__summary' => $summary])>

        @if ($summary && $countries->isNotEmpty())
            {{-- LEFT. "font-size:13px;font-weight:600;letter-spacing:.14em;
                 color:var(--c-green-dark)" for the eyebrow, the h2 at
                 clamp(27px,4vw,40px), the lead at 17px/1.65 max-width 44ch,
                 then the map at max-width 320px and a 13px note. --}}
            <div class="b-members__claim">
                @if ($intro)
                    <p class="b-members__lead">{{ $intro->value }}</p>
                @endif

                {{-- The real RegionMap at 320px, static: no controls, no
                     legend, no exports, nothing to operate. A summary that
                     hands a reader a dataset selector is asking them to do
                     work the page below is for. Disclosed variation --
                     screen 02 draws a decorative graphic here rather than
                     the component. --}}
                <div class="b-members__map-small">
                    <x-region-map :countries="$countries" :view="\App\Support\MapView::resolve($countries, null)" :static="true" />
                </div>

                <p class="b-members__note">{{ __('blocks.members_map_note') }}</p>
            </div>

            {{-- RIGHT. Fourteen tiles, "minmax(min(100%,160px),1fr);
                 gap:var(--s-3)", each cream with a 1px border, --r-sm,
                 --s-3 padding and a 34px mark beside two lines. --}}
            <ul class="b-members__tiles" role="list">
                @foreach ($countries as $country)
                    <li class="b-members__tile">
                        <a class="b-members__tile-link" href="{{ route('members.show', ['locale' => app()->getLocale(), 'iso' => $country->iso_code]) }}">
                            {{-- The flag replaces the screen's ISO mark. --}}
                            <span class="b-members__tile-mark"><x-flag :iso="$country->iso_code" /></span>
                            <span class="b-members__tile-names">
                                <span class="b-members__tile-acronym">{{ $country->authority?->acronym ?: $country->name }}</span>
                                <span class="b-members__tile-country">{{ $country->name }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @elseif ($block->setting('show_map') && $countries->isNotEmpty())
            <div @class(['b-members__map' => $directory])>
            @if ($directory)
                <div class="b-members__head">
                    <h3 class="b-members__head-title">{{ __('members.region_heading') }}</h3>
                    <span class="b-members__head-note">{{ __('members.region_note') }}</span>
                </div>
            @endif
            {{-- The dataset selector answers without reloading. It is a GET
                 form underneath, so with no script the map still shades,
                 still deep-links and still prints. --}}
            @php $mapView = \App\Support\MapView::resolve($countries, request()->query('indicator')); @endphp
            <div data-live-filters
                 data-live-swap="#members-map"
                 data-live-status="#members-map-status">
                {{-- Outside the swapped region, so its text change is
                     announced rather than silently rebuilt. --}}
                <p class="c-results" id="members-map-status" role="status">{{ $mapView->summary() }}</p>
                <div id="members-map">
                    <x-region-map :countries="$countries" :view="$mapView" />
                </div>
            </div>
            </div>
        @endif

        @if ($countries->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.members_empty_title'),
                'body' => __('blocks.members_empty_body'),
            ])
        @elseif ($summary)
            {{-- Nothing more. The tiles above are the summary. --}}
        @elseif ($directory)
            {{-- The directory. Screen 08 draws it as rows in one white
                 card, alphabetical by country, not as a grid of cards: it
                 is a list you scan for a name, and the map beside it is the
                 other way in to the same fourteen. --}}
            <div class="b-members__list">
                <div class="b-members__head">
                    <h3 class="b-members__head-title">{{ __('members.directory_heading') }}</h3>
                    <span class="b-members__head-note">{{ trans_choice('members.directory_note', $countries->count(), ['count' => $countries->count()]) }}</span>
                </div>

                <ul class="b-directory" role="list">
                    @foreach ($countries as $country)
                        <li class="b-directory__row" id="member-{{ $country->iso_code }}">
                            {{-- Screen 08 puts an ISO code in this slot. The
                                 flag goes here instead: it is the same
                                 mark in the same place and a reader
                                 recognises it faster than two letters.
                                 Decorative either way -- the country's name
                                 is two columns to the right and is what
                                 carries the meaning. --}}
                            <span class="b-directory__mark">
                                <x-flag :iso="$country->iso_code" />
                            </span>
                            <span class="b-directory__names">
                                <span class="b-directory__acronym">{{ $country->authority?->acronym ?: $country->name }}</span>
                                @if ($country->authority)
                                    <span class="b-directory__full">{{ $country->authority->name }}</span>
                                @endif
                            </span>
                            <span class="b-directory__side">
                                <span class="b-directory__country">{{ $country->name }}</span>
                                <a class="b-directory__link" href="{{ route('members.show', ['locale' => app()->getLocale(), 'iso' => $country->iso_code]) }}">{{ __('members.directory_open') }}</a>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <ul class="b-members" role="list">
                @foreach ($countries as $country)
                    @php $tally = \App\Support\SectionCounts::forCountry($country); @endphp
                    <li class="b-member" id="member-{{ $country->iso_code }}" data-span="{{ $country->cardSpan() }}">
                        {{-- The card is a bordered white box and reads as
                             one thing a reader can open, so it opens. The
                             name carries the link and a stretched hit area
                             makes the whole card the target: a screen
                             reader hears one link called "Botswana", and a
                             thumb gets the card. --}}
                        <span class="b-member__id">
                            {{-- Decorative: the name beside it is what
                                 carries the meaning, and a flag alone is
                                 not an identifier a screen reader or a
                                 reader who does not know fourteen Southern
                                 African flags can use. --}}
                            <x-flag :iso="$country->iso_code" />
                            <a class="b-member__name" href="{{ route('members.show', ['locale' => app()->getLocale(), 'iso' => $country->iso_code]) }}">{{ $country->name }}</a>
                        </span>
                        @if ($country->authority)
                            <span class="b-member__authority">{{ $country->authority->name }}</span>
                        @endif
                        {{-- A counted seat, including none. The original
                             rule here was to hide a zero, on the reasoning
                             that an invented committee count reads as
                             fabricated data. That reasoning still holds for
                             invented numbers and not for counted ones: this
                             figure is a query, and a member state sitting on
                             no committee is a fact a reader of a membership
                             page is entitled to. --}}
                        @if ($seatsKnown)
                            <span class="b-member__committees">
                                {{ trans_choice('blocks.committee_count', $country->committees->count(), ['count' => $country->committees->count()]) }}
                            </span>
                        @endif

                        @if ($country->authority?->sectors->isNotEmpty())
                            <span class="b-member__sectors">
                                {{ $country->authority->sectors->pluck('name')->implode(', ') }}
                            </span>
                        @endif

                        {{-- How much this member state has on the platform.
                             A reader scanning fourteen cards should be able
                             to see which states have filed and which have
                             not without opening any of them.

                             Zero is shown. A state with no instruments has
                             filed none, and hiding that turns a fact about
                             the data into a gap in the interface. --}}
                        <dl class="b-member__tally">
                            @foreach ([
                                'instruments' => 'members.tally_instruments',
                                'publications' => 'members.tally_publications',
                                'indicators' => 'members.tally_indicators',
                            ] as $field => $key)
                                <div>
                                    <dt>{{ trans_choice($key, $tally[$field], ['count' => $tally[$field]]) }}</dt>
                                    <dd>{{ $tally[$field] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </li>
                @endforeach
            </ul>
        @endif

        </div>{{-- b-members__layout --}}
    </div>
</section>
