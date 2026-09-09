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

    /*
     * Sorting. The column headings looked sortable and were not, which is
     * the affordance failure in its plainest form: a table heading in a
     * data tool is a control everywhere else a regulator works.
     *
     * A regulator comparing fourteen member states wants to sort by value,
     * so value sorts, and so does the member state name. Server-rendered
     * links with aria-sort, so it works with no script and the sorted view
     * has its own URL: a regulator pastes "sorted by value, descending"
     * into an email and the recipient sees the same order.
     *
     * "Not reported" always sorts last, whichever direction is chosen. It
     * is not a low value and it must not be ordered as one.
     */
    $sort = request()->string('sort')->value() ?: 'state';
    $descending = request()->string('dir')->value() === 'desc';

    $ordered = $comparison->countries
        ->filter(fn ($c) => isset($row[$c->id]))
        ->sortBy(function ($country) use ($row, $sort) {
            $cell = $row[$country->id];

            if ($sort !== 'value') {
                return [0, mb_strtolower($country->name)];
            }

            return $cell->isReported()
                ? [0, (float) $cell->value->value_numeric]
                : [1, 0.0];
        })
        ->values();

    if ($descending) {
        // Reverse the reported block only, so unreported states stay at the
        // bottom rather than being promoted to the top by a reversal.
        $reported = $ordered->filter(fn ($c) => $row[$c->id]->isReported())->reverse()->values();
        $ordered = $reported->concat($ordered->reject(fn ($c) => $row[$c->id]->isReported()))->values();
    }

    $sortLink = function (string $column) use ($sort, $descending) {
        $next = $sort === $column && ! $descending ? 'desc' : 'asc';

        return request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next, 'page' => null]);
    };

    $ariaSort = fn (string $column) => $sort === $column ? ($descending ? 'descending' : 'ascending') : 'none';
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
                        <th scope="col" aria-sort="{{ $ariaSort('state') }}">
                            <a class="c-sort" href="{{ $sortLink('state') }}">
                                {{ __('comparator.col_state') }}
                                <span class="c-sort__mark" aria-hidden="true">{{ $sort === 'state' ? ($descending ? '▼' : '▲') : '' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="c-cmp__num" aria-sort="{{ $ariaSort('value') }}">
                            <a class="c-sort" href="{{ $sortLink('value') }}">
                                {{ __('comparator.col_value') }}
                                <span class="c-sort__mark" aria-hidden="true">{{ $sort === 'value' ? ($descending ? '▼' : '▲') : '' }}</span>
                            </a>
                        </th>
                        {{-- Provenance is a category with four values and no
                             order. Sorting by it would put "reported" above
                             "validated" for no reason a reader could name,
                             so this heading is not a control and does not
                             look like one. --}}
                        <th scope="col">{{ __('comparator.col_provenance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ordered as $country)
                        @php $cell = $row[$country->id]; @endphp
                        <tr @unless ($cell->isReported()) data-missing @endunless>
                            <th scope="row">
                                <span class="c-cmp__state-cell">
                                    <x-flag :iso="$country->iso_code" />
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
