{{--
    Step 3: the result for one indicator, in both modes. Chart, words,
    table with source, period and status per value, the reading note,
    exports and the link that reproduces it.
--}}
@php
    $locale = app()->getLocale();
    $isBrowse = $comparison->mode === \App\Support\Comparison::BROWSE;
    $indicator = $comparison->indicator;
    $cells = $comparison->ranked();
    $summary = $comparison->summary();
    $query = ['locale' => $locale] + $comparison->queryParameters();
    $bars = $cells->map(fn ($c) => [
        'name' => $c->country->name,
        'iso' => $c->country->iso_code,
        'value' => $c->isReported() && $c->value->value_numeric !== null ? (float) $c->value->value_numeric : null,
        'display' => $c->display(),
        'period' => $c->period(),
    ])->values()->all();
    $plain = trans()->has('d.plain.'.$indicator->slug) ? __('d.plain.'.$indicator->slug) : null;
    $pill = ['validated' => 'u-pill--ok', 'reported' => 'u-pill--notice', 'not_reported' => 'u-pill--muted'];
@endphp

<div class="c-cmp__result" data-cmp-result>
    <div class="c-cmp__result-head">
        <h3 class="c-cmp__result-title">{{ $indicator->name }}</h3>
        <p class="c-cmp__result-meta">
            {{ __('d.sector_'.($indicator->sector?->slug ?? 'other')) }}@if ($indicator->unitLabel()), {{ $indicator->unitLabel() }}@endif.
            @if ($plain) {{ $plain[1] }} @endif
        </p>
    </div>

    <div @class(['c-cmp__view', 'c-cmp__view--browse' => $isBrowse])>
        <figure class="c-cmp__chart u-panel">
            <figcaption class="c-cmp__chart-cap">{{ __('d.chart_title', ['indicator' => $plain[0] ?? $indicator->name, 'mode' => $isBrowse ? __('d.chart_browse') : __('d.chart_compare')]) }}</figcaption>
            <div class="c-cmp__chart-frame">
                @include('pages.comparator._chart', [
                    'bars' => $bars,
                    'max' => $comparison->scaleMax(),
                    'title' => $indicator->name,
                    'summary' => $summary,
                    'id' => 'cmp-chart',
                    'highlightFirst' => $isBrowse,
                ])
                @include('pages.comparator._chart', [
                    'bars' => $bars,
                    'max' => $comparison->scaleMax(),
                    'title' => $indicator->name,
                    'summary' => $summary,
                    'id' => 'cmp-chart',
                    'highlightFirst' => $isBrowse,
                    'narrow' => true,
                ])
            </div>
        </figure>

        @if ($isBrowse)
            <div class="c-cmp__map u-panel">
                <p class="c-cmp__chart-cap">{{ __('d.map_title') }}</p>
                <x-region-map :countries="$comparison->countries" :view="\App\Support\MapView::resolve($comparison->countries, $indicator->slug)" :static="true" />
            </div>
        @else
            <div class="c-cmp__words u-panel u-panel--tint">
                <p class="c-cmp__words-label">{{ __('d.summary_label') }}</p>
                <p class="c-cmp__words-text">{{ $summary }}</p>
                <x-indicator-note :indicators="$indicator" />
                @include('pages.comparator._share', ['query' => $query, 'comparison' => $comparison])
            </div>
        @endif
    </div>

    @if ($isBrowse)
        <div class="c-cmp__words c-cmp__words--row u-panel u-panel--tint">
            <div>
                <p class="c-cmp__words-label">{{ __('d.summary_label') }}</p>
                <p class="c-cmp__words-text">{{ $summary }}</p>
                <x-indicator-note :indicators="$indicator" />
            </div>
            @include('pages.comparator._share', ['query' => $query, 'comparison' => $comparison])
        </div>
    @endif

    <div class="u-table-frame c-cmp__tableframe">
        <table class="u-table c-cmp__datatable">
            <caption class="c-sr">{{ $summary }}</caption>
            <thead>
                <tr>
                    @if ($isBrowse)<th scope="col">{{ __('d.col_rank') }}</th>@endif
                    <th scope="col">{{ __('d.col_state') }}</th>
                    <th scope="col" class="c-cmp__num">{{ __('d.col_value') }}</th>
                    <th scope="col">{{ __('d.col_year') }}</th>
                    <th scope="col">{{ __('d.col_status') }}</th>
                    <th scope="col">{{ __('d.col_source') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cells as $i => $cell)
                    <tr @unless ($cell->isReported()) data-missing @endunless>
                        @if ($isBrowse)<td data-label="{{ __('d.col_rank') }}">{{ $cell->isReported() ? $i + 1 : '' }}</td>@endif
                        <th scope="row" data-label="{{ __('d.col_state') }}">
                            <a class="c-cmp__state-link" href="{{ route('members.show', ['locale' => $locale, 'iso' => $cell->country->iso_code]) }}"><x-flag :iso="$cell->country->iso_code" /> {{ $cell->country->name }}</a>
                        </th>
                        <td class="c-cmp__num" data-label="{{ __('d.col_value') }}">{{ $cell->display() }}</td>
                        <td data-label="{{ __('d.col_year') }}">{{ $cell->period() }}</td>
                        <td data-label="{{ __('d.col_status') }}"><span class="u-pill {{ $pill[$cell->provenance()] ?? 'u-pill--muted' }}">{{ __('comparator.provenance_'.$cell->provenance()) }}</span></td>
                        <td class="c-cmp__src" data-label="{{ __('d.col_source') }}">{{ $cell->source() ?? ($cell->isReported() ? __('d.no_source') : '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="c-cmp__foot">
        @if ($indicator->source_standard)<span><strong>{{ __('d.source') }}</strong> {{ $indicator->source_standard }}</span>@endif
        @if ($comparison->latestUpdate())<span><strong>{{ __('comparator.foot_updated') }}</strong> {{ $comparison->latestUpdate()->isoFormat('D MMMM YYYY') }}</span>@endif
    </p>
</div>
