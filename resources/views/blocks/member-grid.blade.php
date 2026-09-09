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
             component can appear on the homepage without it. --}}
        {{--
            The region map is withdrawn, 9 September 2026.

            As drawn it is wrong in ways a reader sees immediately: the
            landmasses render solid black rather than in the palette,
            Madagascar and Seychelles are drawn and labelled although
            neither is a CRASA member, Seychelles is a four-pixel dot
            carrying a label, and the shapes spread down far more vertical
            space than the layout allows for. Comoros and Mauritius carry no
            path at all, so the caption promising labelled insets describes
            something that is not there.

            A broken map is worse than fourteen cards, so it comes off the
            page rather than being noticed at the workshop. The cards below
            carry the same information and always did.

            It goes back when it renders fourteen member shapes inside one
            viewBox, in the palette, with the two island members as labelled
            insets and no non-member labelled. Those conditions are tests in
            RegionMapTest, and the tests are the gate.
        --}}

        @if ($countries->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.members_empty_title'),
                'body' => __('blocks.members_empty_body'),
            ])
        @else
            <ul class="b-members" role="list">
                @foreach ($countries as $country)
                    <li class="b-member" id="member-{{ $country->iso_code }}" data-span="{{ $country->cardSpan() }}">
                        <span class="b-member__name">{{ $country->name }}</span>
                        @if ($country->authority)
                            <span class="b-member__authority">{{ $country->authority->name }}</span>
                        @endif
                        {{-- Only when there is a real seat to report. An
                             invented committee count reads as fabricated
                             data, and every state showing the same number
                             reads as fabricated data loudly. --}}
                        @if ($country->committees->isNotEmpty())
                            <span class="b-member__committees">
                                {{ trans_choice('blocks.committee_count', $country->committees->count(), ['count' => $country->committees->count()]) }}
                            </span>
                        @endif

                        @if ($country->authority?->sectors->isNotEmpty())
                            <span class="b-member__sectors">
                                {{ $country->authority->sectors->pluck('name')->implode(', ') }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
