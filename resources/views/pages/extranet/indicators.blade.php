@extends('layouts.extranet')

{{--
    The indicator taxonomy, RP-11 and extranet item 11.

    DATA_MODEL: a definition has to be "precise enough that fourteen
    regulators report the same thing". The definitions were already stored
    and the only place they appeared was as a hint under a form field,
    truncated by the layout, where a regulator part way through a return
    cannot read one without losing their place.

    Every e-Form field links here, to its own indicator, so the answer is
    one click from the question.
--}}

@section('title', __('indicators.title'))
@section('note', __('indicators.note'))

@section('content')
    <nav class="a-filters" aria-label="{{ __('indicators.filter') }}">
        <a @class(['a-chip', 'is-current' => $category === null])
           href="{{ route('indicators.reference', ['locale' => app()->getLocale()]) }}">{{ __('indicators.all') }}</a>
        @foreach ($categories as $option)
            <a @class(['a-chip', 'is-current' => $category === $option])
               href="{{ route('indicators.reference', ['locale' => app()->getLocale(), 'category' => $option]) }}">{{ __('indicators.category_'.$option) }}</a>
        @endforeach
    </nav>

    @if ($indicators->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('indicators.empty_title') }}</div>
                <div class="a-empty__body">{{ __('indicators.empty_body') }}</div>
            </div>
        </div>
    @endif

    @foreach ($indicators as $group => $rows)
        <section class="a-card a-card--flush" id="{{ $group }}">
            <div class="a-card__head">
                <h2>{{ __('indicators.category_'.$group) }}</h2>
                <span class="a-card__meta">{{ trans_choice('indicators.count', $rows->count(), ['count' => $rows->count()]) }}</span>
            </div>

            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('indicators.col_indicator') }}</th>
                            <th scope="col">{{ __('indicators.col_definition') }}</th>
                            <th scope="col">{{ __('indicators.col_unit') }}</th>
                            <th scope="col">{{ __('indicators.col_source') }}</th>
                            <th scope="col">{{ __('indicators.col_periodicity') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $indicator)
                            {{-- The row is the anchor an e-Form field links
                                 to, by slug, so a link written into a form
                                 keeps working when the order changes. --}}
                            <tr id="i-{{ $indicator->slug }}">
                                <th scope="row">
                                    {{ $indicator->name }}
                                    @if ($indicator->sector)
                                        <span class="a-table__sub">{{ $indicator->sector->name }}</span>
                                    @endif
                                </th>
                                <td>{{ $indicator->definition ?: __('indicators.no_definition') }}</td>
                                <td>{{ $indicator->unitLabel() }}</td>
                                <td>{{ $indicator->source_standard ?: __('indicators.no_source') }}</td>
                                <td>{{ __('indicators.periodicity_'.$indicator->periodicity) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
