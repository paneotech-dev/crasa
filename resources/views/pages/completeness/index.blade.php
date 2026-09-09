{{--
    The completeness matrix. Fourteen member states against the indicator
    categories.

    The gaps are the point. A cell is empty because nothing has been
    reported, and it says so plainly rather than showing a zero, because a
    zero is a figure and a gap is not.
--}}
@extends('layouts.public')

@section('title', __('indicators.completeness_title'))

@section('content')
    <div class="c-section">
        <div class="c-page">
            <h1>{{ __('indicators.completeness_title') }}</h1>
            <p class="c-lead">{{ __('indicators.completeness_intro') }}</p>

            <div class="c-notice c-notice--pending" role="status">
                <svg class="c-notice__icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="var(--c-amber-dark)" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M12 9v4"></path><path d="M12 17h.01"></path>
                    <path d="M10.3 3.9L2.4 18a1.8 1.8 0 001.6 2.7h16a1.8 1.8 0 001.6-2.7L13.7 3.9a1.8 1.8 0 00-3.4 0z"></path>
                </svg>
                <div>
                    <div class="c-notice__title">{{ __('indicators.demo_title') }}</div>
                    <div class="c-notice__body">{{ __('indicators.demo_body') }}</div>
                </div>
            </div>

            <p class="c-results" role="status">
                {{ __('indicators.overall', ['percent' => $matrix['overall']]) }}
            </p>

            <div class="c-scroll">
                <table class="c-table c-completeness">
                    <caption class="c-sr">{{ __('indicators.completeness_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('blocks.member_state') }}</th>
                            @foreach ($matrix['categories'] as $category => $expected)
                                <th scope="col">
                                    {{ __('indicators.category_'.$category) }}
                                    <span class="c-table__sub">{{ trans_choice('indicators.indicator_count', $expected, ['count' => $expected]) }}</span>
                                </th>
                            @endforeach
                            <th scope="col">{{ __('indicators.total') }}</th>
                            <th scope="col">{{ __('indicators.latest') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix['countries'] as $country)
                            @php $total = $matrix['totals'][$country->id]; @endphp
                            <tr>
                                <th scope="row">{{ $country->name }}</th>

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
