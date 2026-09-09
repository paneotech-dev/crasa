{{--
    Browse mode, screen 03: one indicator across all fourteen member states,
    as a table beside a regional view.

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(430px,1fr))">

    The bars are DOM elements that each state their own figure, exactly as
    the screen draws them, rather than a canvas. That is what lets the
    screen "read correctly as a printed page and under a screen reader",
    and it means the chart needs no separate table equivalent because it is
    already text.
--}}
@php
    $indicator = $comparison->indicator;
    $row = $cells[$indicator->id] ?? [];
    $numbers = collect($row)->filter->isReported()
        ->map(fn ($c) => (float) $c->value->value_numeric)->filter(fn ($n) => $n !== null);
    $min = $numbers->min() ?? 0.0;
    $max = $numbers->max() ?? 1.0;
@endphp

<section class="c-cmp__result">
    <div class="c-cmp__result-head">
        <p class="c-cmp__eyebrow">{{ __('comparator.category_'.$indicator->category) }} — {{ __('comparator.mode_browse') }}</p>
        <h2>{{ $indicator->name }}</h2>
        <p class="c-cmp__result-meta">{{ $indicator->unitLabel() }}{{ $indicator->definition ? ' — '.$indicator->definition : '' }}</p>
    </div>

    <div class="c-cmp__split">
        <div class="c-cmp__tablewrap">
            <table class="c-cmp__table">
                <caption class="c-sr">{{ __('comparator.browse_caption', ['indicator' => $indicator->name]) }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('comparator.col_state') }}</th>
                        <th scope="col" class="c-cmp__num">{{ __('comparator.col_value') }}</th>
                        <th scope="col">{{ __('comparator.col_provenance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comparison->countries as $country)
                        @php $cell = $row[$country->id] ?? null; @endphp
                        @continue($cell === null)
                        <tr @unless ($cell->isReported()) data-missing @endunless>
                            <th scope="row">
                                <span class="c-cmp__state-cell">
                                    <span class="c-cmp__code">{{ strtoupper($country->iso_code) }}</span>
                                    {{ $country->name }}
                                </span>
                            </th>
                            <td class="c-cmp__num" @unless ($cell->isReported()) data-missing @endunless>{{ $cell->display() }}</td>
                            <td>
                                <span class="c-cmp__pill" data-provenance="{{ $cell->provenance() }}">{{ __('comparator.provenance_'.$cell->provenance()) }}</span>
                                @if ($cell->source())
                                    <span class="c-cmp__source">{{ $cell->source() }}{{ $cell->period() ? ', '.$cell->period() : '' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="c-cmp__regional">
            <p class="c-cmp__eyebrow">{{ __('comparator.regional_view') }}</p>

            {{-- Every bar states its count, so the chart reads without
                 colour. Screen 03 draws exactly this. --}}
            <div class="c-cmp__bars">
                @foreach ($comparison->countries as $country)
                    @php
                        $cell = $row[$country->id] ?? null;
                        $share = $cell?->share($min, $max);
                    @endphp
                    @continue($cell === null)
                    <div class="c-cmp__bar-row">
                        <span class="c-cmp__bar-code">{{ strtoupper($country->iso_code) }}</span>
                        <span class="c-cmp__bar-track">
                            @if ($share !== null)
                                <span class="c-cmp__bar-fill" style="width:{{ max(2, round($share * 100)) }}%"></span>
                            @else
                                <span class="c-cmp__bar-missing" aria-hidden="true"></span>
                            @endif
                        </span>
                        <span class="c-cmp__bar-value" @unless ($cell->isReported()) data-missing @endunless>{{ $cell->display() }}</span>
                    </div>
                @endforeach
            </div>

            <p class="c-cmp__note">{{ __('comparator.bars_note') }}</p>
        </div>
    </div>

    <div class="c-cmp__foot">
        <span><strong>{{ __('comparator.foot_source') }}</strong> {{ $indicator->source_standard ?: __('comparator.foot_source_unknown') }}</span>
        @if ($comparison->latestUpdate())
            <span><strong>{{ __('comparator.foot_updated') }}</strong> {{ $comparison->latestUpdate()->isoFormat('D MMMM YYYY') }}</span>
        @endif
        @if ($indicator->source_url)
            <a href="{{ $indicator->source_url }}" rel="noopener">{{ __('comparator.foot_open_source') }}</a>
        @endif
    </div>
</section>
