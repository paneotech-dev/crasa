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

<section class="b">
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif
        @if ($intro)
            <p class="b__intro">{{ $intro->value }}</p>
        @endif

        @if ($countries->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.members_empty_title'),
                'body' => __('blocks.members_empty_body'),
            ])
        @else
            <ul class="b-members" role="list">
                @foreach ($countries as $country)
                    <li class="b-member" data-span="{{ $country->cardSpan() }}">
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
