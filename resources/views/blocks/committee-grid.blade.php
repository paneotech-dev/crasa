{{--
    The committees.

    DERIVED, NOT DRAWN. No screen draws a public committee list. Screen 04
    draws a single committee workspace behind authentication, and its own
    breadcrumb — "Committees / Spectrum and Frequency Management" — implies
    an index it does not draw. So the card is member_grid's, which is this
    platform's pattern for a set of institutional records: a bordered white
    card whose height follows its content, the name as the link, the
    supporting line beneath, and a counted tally on a rule at the foot.
    Logged as a disclosed variation in docs/OPEN_ITEMS.md.

    Screen 04 does settle two things and both are followed. Its meta line
    reads "ICT, Standing Committee, Strategic cycle 2023 to 2028" — sector
    first, then standing. And its empty state is written: "The Human and
    Institutional Capacity Committee has been constituted but has not yet
    scheduled its first meeting." A committee with nothing yet is a stage in
    its life, not a fault, and the card says which.
--}}
@php
    $heading = $block->translate('heading');
    $intro = $block->translate('intro');
    $committees = $committees ?? collect();
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

        @if ($committees->isEmpty())
            @include('blocks._empty', [
                'editorOnly' => true,
                'title' => __('blocks.committees_empty_title'),
                'body' => __('blocks.committees_empty_body'),
            ])
        @else
            <ul class="b-committees" role="list">
                @foreach ($committees as $committee)
                    @php
                        $seats = (int) ($committee->countries_count ?? 0);
                        $documents = (int) ($committee->publications_count ?? 0);
                        $meetings = (int) ($committee->meetings_count ?? 0);
                        $dormant = $meetings === 0 && $documents === 0;
                    @endphp
                    <li class="b-committee" id="committee-{{ $committee->slug }}">
                        <span class="b-committee__name">{{ $committee->name }}</span>

                        {{-- Screen 04's meta line: the sector, then whether
                             it is standing or cross-sector. Commas, not
                             middle dots, per CLAUDE.md. --}}
                        <span class="b-committee__meta">
                            {{ $committee->sector?->name ?: __('blocks.committee_cross_sector') }}
                        </span>

                        @if ($committee->translate('mandate'))
                            <p class="b-committee__mandate">{{ $committee->translated('mandate') }}</p>
                        @endif

                        @if ($committee->chairAuthority)
                            <span class="b-committee__chair">
                                {{ __('blocks.committee_chaired_by', ['authority' => $committee->chairAuthority->name]) }}
                            </span>
                        @endif

                        @if ($dormant)
                            {{-- Screen 04's own words for a committee that
                                 has been constituted and has not started. A
                                 zero tally here would report three absences
                                 where the truth is one fact. --}}
                            <p class="b-committee__dormant">{{ __('blocks.committee_dormant') }}</p>
                        @else
                            <dl class="b-committee__tally">
                                @foreach (array_filter([
                                    // Withheld while nobody has told us who
                                    // sits on these committees: see
                                    // Committee::seatsRecorded().
                                    $seatsKnown ? ['count' => $seats, 'key' => 'blocks.committee_seats', 'route' => 'members'] : null,
                                    ['count' => $documents, 'key' => 'blocks.committee_documents', 'route' => 'publications'],
                                    ['count' => $meetings, 'key' => 'blocks.committee_meetings', 'route' => 'events'],
                                ]) as $item)
                                    <div>
                                        <dd>
                                            @if ($item['count'] > 0)
                                                <a href="{{ route($item['route'], array_filter([
                                                    'locale' => app()->getLocale(),
                                                    'committee' => $item['route'] === 'publications' ? $committee->slug : null,
                                                ])) }}">{{ $item['count'] }}</a>
                                            @else
                                                {{ $item['count'] }}
                                            @endif
                                        </dd>
                                        <dt>{{ trans_choice($item['key'], $item['count'], ['count' => $item['count']]) }}</dt>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </li>
                @endforeach
            </ul>

            @unless ($seatsKnown)
                {{-- Said once, under the grid, rather than as fourteen
                     zeroes across it. Which member state sits on which
                     committee is one of the items on the Secretariat's
                     list, and a count of nought would report an absence
                     the platform has not measured. --}}
                <p class="b-committees__note">{{ __('blocks.committee_seats_pending') }}</p>
            @endunless
        @endif
    </div>
</section>
