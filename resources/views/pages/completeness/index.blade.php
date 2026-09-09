{{--
    The completeness matrix. Fourteen member states against the indicator
    categories.

    The gaps are the point. A cell is empty because nothing has been
    reported, and it says so plainly rather than showing a zero, because a
    zero is a figure and a gap is not.
--}}
@extends('layouts.public')

@section('title', __('indicators.completeness_title'))

@php
    /*
     * Sorting. Eighty-four cells in member-state order answers "how is
     * Zambia doing"; it does not answer "who is behind", which is the
     * question the Secretariat opens this grid to ask. So the total and the
     * freshness column sort, and a sorted view has its own URL.
     *
     * Server-rendered links with aria-sort. No script, and pasting the
     * sorted view into an email shows the recipient the same order.
     */
    $sort = request()->string('sort')->value() ?: 'state';
    $descending = request()->string('dir')->value() === 'desc';

    $sorted = $matrix['countries']->sortBy(function ($country) use ($matrix, $sort) {
        return match ($sort) {
            'total' => $matrix['totals'][$country->id]['percent'] ?? 0,
            // Never reported sorts last on the way up and first on the way
            // down, which is where a Secretariat looking for gaps wants it.
            'latest' => $matrix['freshness'][$country->id] ?? '',
            default => mb_strtolower($country->name),
        };
    })->values();

    if ($descending) {
        $sorted = $sorted->reverse()->values();
    }

    $sortLink = function (string $column) use ($sort, $descending) {
        $next = $sort === $column && ! $descending ? 'desc' : 'asc';

        return request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next]);
    };

    $ariaSort = fn (string $column) => $sort === $column ? ($descending ? 'descending' : 'ascending') : 'none';
    $mark = fn (string $column) => $sort === $column ? ($descending ? '▼' : '▲') : '';
@endphp

@section('content')
    <x-section-band
        :eyebrow="__('indicators.band_eyebrow')"
        :title="__('indicators.completeness_title')"
        :lead="__('indicators.completeness_intro')"
        :counts="\App\Support\SectionCounts::comparator()"
    />

    <div class="c-section">
        <div class="c-page">

            {{-- The demonstration notice was here. Removed on the ruling
                 of 9 September 2026, which named the admin dashboard as the
                 only surface that carries it.

                 This page is the one place that removal costs something.
                 It is behind authentication and it is the data-quality
                 view, read by member state representatives who cannot see
                 the admin dashboard and so have no signal at all that the
                 matrix is built on unconfirmed figures. Recorded in
                 OPEN_ITEMS.md with a recommendation to put it back here
                 alone; restoring it is reverting this hunk. --}}

            {{-- The answer first. A reader who opens this grid wants the
                 regional figure and who is behind on it; the eighty-four
                 cells beneath are the evidence for both. --}}
            <dl class="c-tiles">
                <div class="c-tile">
                    <dd>{{ $matrix['overall'] }}%</dd>
                    <dt>{{ __('indicators.tile_overall') }}</dt>
                </div>
                <div class="c-tile">
                    <dd>{{ collect($matrix['totals'])->filter(fn ($t) => $t['percent'] === 100)->count() }}</dd>
                    <dt>{{ __('indicators.tile_complete') }}</dt>
                </div>
                <div class="c-tile">
                    <dd>{{ collect($matrix['totals'])->filter(fn ($t) => $t['reported'] === 0)->count() }}</dd>
                    <dt>{{ __('indicators.tile_nothing') }}</dt>
                </div>
                <div class="c-tile">
                    <dd>{{ collect($matrix['freshness'])->filter()->max() ?: __('indicators.never') }}</dd>
                    <dt>{{ __('indicators.tile_latest') }}</dt>
                </div>
            </dl>

            <p class="c-results" role="status">
                {{ __('indicators.overall', ['percent' => $matrix['overall']]) }}
            </p>

            <div class="c-scroll">
                <table class="c-table c-completeness">
                    <caption class="c-sr">{{ __('indicators.completeness_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" aria-sort="{{ $ariaSort('state') }}">
                                <a class="c-sort" href="{{ $sortLink('state') }}">
                                    {{ __('blocks.member_state') }}
                                    <span class="c-sort__mark" aria-hidden="true">{{ $mark('state') }}</span>
                                </a>
                            </th>
                            @foreach ($matrix['categories'] as $category => $expected)
                                <th scope="col">
                                    {{ __('indicators.category_'.$category) }}
                                    <span class="c-table__sub">{{ trans_choice('indicators.indicator_count', $expected, ['count' => $expected]) }}</span>
                                </th>
                            @endforeach
                            <th scope="col" aria-sort="{{ $ariaSort('total') }}">
                                <a class="c-sort" href="{{ $sortLink('total') }}">
                                    {{ __('indicators.total') }}
                                    <span class="c-sort__mark" aria-hidden="true">{{ $mark('total') }}</span>
                                </a>
                            </th>
                            <th scope="col" aria-sort="{{ $ariaSort('latest') }}">
                                <a class="c-sort" href="{{ $sortLink('latest') }}">
                                    {{ __('indicators.latest') }}
                                    <span class="c-sort__mark" aria-hidden="true">{{ $mark('latest') }}</span>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sorted as $country)
                            @php $total = $matrix['totals'][$country->id]; @endphp
                            <tr>
                                {{-- The name of a member state on a grid
                                     about that member state's filing goes
                                     to that member state. --}}
                                <th scope="row">
                                    <x-flag :iso="$country->iso_code" />
                                    <a href="{{ route('members.show', ['locale' => app()->getLocale(), 'iso' => $country->iso_code]) }}">{{ $country->name }}</a>
                                </th>

                                @foreach ($matrix['categories'] as $category => $expected)
                                    @php $cell = $matrix['cells'][$country->id][$category]; @endphp
                                    <td class="c-completeness__cell" data-state="{{ $cell['reported'] === 0 ? 'empty' : ($cell['percent'] === 100 ? 'complete' : 'partial') }}">
                                        @if ($cell['reported'] === 0)
                                            {{-- A gap, not a zero. --}}
                                            <span class="c-completeness__gap">{{ __('indicators.not_reported') }}</span>
                                        @else
                                            <span class="c-num">{{ $cell['reported'] }}/{{ $cell['expected'] }}</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="c-completeness__total" data-state="{{ $total['percent'] === 0 ? 'empty' : ($total['percent'] === 100 ? 'complete' : 'partial') }}">
                                    <span class="c-num">{{ $total['percent'] }}%</span>
                                </td>

                                <td>
                                    @php $fresh = \App\Support\CompletenessMatrix::freshnessOf($matrix['freshness'][$country->id] ?? null); @endphp
                                    <span class="c-pill {{ ['current' => 'c-pill--validated', 'ageing' => 'c-pill--pending', 'stale' => 'c-pill--error', 'none' => 'c-pill--archived'][$fresh] }}">
                                        {{ $matrix['freshness'][$country->id] ?? __('indicators.never') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h2>{{ __('indicators.taxonomy_title') }}</h2>
            <p class="c-small">{{ __('indicators.taxonomy_intro') }}</p>

            <div class="b-rows">
                @foreach ($indicators as $indicator)
                    <div class="b-row">
                        <div class="b-row__main">
                            <div class="b-row__title">{{ $indicator->name }}</div>
                            <p class="c-small">{{ $indicator->definition }}</p>
                            <div class="b-row__meta">
                                <span>{{ $indicator->categoryLabel() }}</span>
                                @if ($indicator->sector)<span>{{ $indicator->sector->name }}</span>@endif
                                <span>{{ $indicator->unitLabel() }}</span>
                            </div>
                            {{-- Every definition cites where it came from. An
                                 indicator without a source does not belong in
                                 a taxonomy fourteen regulators report against. --}}
                            <p class="c-source">
                                @if ($indicator->source_url)
                                    <a href="{{ $indicator->source_url }}" rel="noopener">{{ $indicator->source_standard }}</a>
                                @else
                                    {{ $indicator->source_standard }}
                                @endif
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
