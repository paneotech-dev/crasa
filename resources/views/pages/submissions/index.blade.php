@extends('layouts.workspace')

{{--
    The annual return list, redrawn on Day 3 (8c). The period picker filters the
    returns; a member state representative starts a return from the same
    card. The returns are a table from 760px wide and one card per return
    on a phone (member-home.css). The country scope on Submission decides
    which returns are listed: a member state sees its own.
--}}

@php
    // 1f: a member state reading one period sees "Annual return, 2026, Zambia".
    $ownCountry = auth()->user()?->country?->displayName();
    $heading = $period
        ? ($ownCountry ? __('m3_retaud.return_heading', ['period' => $period, 'country' => $ownCountry]) : __('d3_look.returns_for', ['period' => $period]))
        : __('submissions.title');
@endphp
@section('title', $heading)
@section('note', __('submissions.index_note'))

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/member-home.css') }}">
@endpush

@section('content')
@php $locale = app()->getLocale(); @endphp
<div class="mh mh-main">
    <section class="mh-card" aria-labelledby="mh-period-h">
        <div class="mh-card__head">
            <h2 class="mh-card__title" id="mh-period-h">{{ __('d3_look.period_card') }}</h2>
        </div>
        <div class="mh-picker-row">
            <form class="mh-picker" method="GET" action="{{ route('submissions', ['locale' => $locale]) }}">
                <div class="a-field">
                    <label class="a-label" for="filter-period">{{ __('d3_look.show_for') }}</label>
                    <select class="a-select" id="filter-period" name="period">
                        <option value="">{{ __('d3_look.all_periods') }}</option>
                        @foreach ($knownPeriods as $p)
                            <option value="{{ $p }}" @selected($period === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="a-btn a-btn--secondary">{{ __('d3_look.show') }}</button>
            </form>

            @if ($canOpen)
                <form class="mh-picker" method="POST" action="{{ route('submissions.store', ['locale' => $locale]) }}">
                    @csrf
                    <div class="a-field">
                        <label class="a-label" for="period">{{ __('submissions.open_title') }}</label>
                        <select class="a-select" id="period" name="period" aria-describedby="period-hint">
                            @foreach ($periods as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="a-btn a-btn--primary">{{ __('submissions.open_action') }}</button>
                    <span class="mh-meta" id="period-hint">{{ __('submissions.period_hint') }}</span>
                </form>
            @endif
        </div>
    </section>

    <section class="mh-card" aria-labelledby="mh-returns-h">
        <div class="mh-card__head">
            <h2 class="mh-card__title" id="mh-returns-h">{{ $period ? $heading : __('d3_look.returns') }}</h2>
            <span class="mh-meta">{{ __('submissions.table_note') }}</span>
        </div>

        @if ($submissions->isEmpty())
            <div class="a-empty">
                <p class="a-empty__title">{{ __('submissions.empty_title') }}</p>
                <p class="a-empty__body">{{ __('submissions.empty_body') }}</p>
            </div>
        @else
            <table class="mh-returns">
                <caption class="c-sr">{{ $period ? $heading : __('d3_look.returns') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('submissions.period') }}</th>
                        <th scope="col">{{ __('admin.field_country') }}</th>
                        <th scope="col">{{ __('submissions.col_progress') }}</th>
                        <th scope="col">{{ __('admin.col_status') }}</th>
                        <th scope="col">{{ __('admin.col_updated') }}</th>
                        <th scope="col"><span class="c-sr">{{ __('d3_look.open_link') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($submissions as $s)
                        @php
                            $filled = $s->values->filter->isComplete()->count();
                            $pct = $indicatorCount > 0 ? (int) round($filled / $indicatorCount * 100) : 0;
                        @endphp
                        <tr>
                            <td class="mh-returns__lead" data-label="">{{ __('extranet.dash_return_for', ['period' => $s->period]) }}</td>
                            <td data-label="{{ __('admin.field_country') }}">
                                <span>@if ($s->country)<x-flag :iso="$s->country->iso_code" /> {{ $s->country->name }}@endif</span>
                            </td>
                            <td data-label="{{ __('submissions.col_progress') }}">
                                <span>
                                    <span class="mh-bar" aria-hidden="true"><span class="mh-bar__fill" style="width: {{ $pct }}%"></span></span>
                                    {{ $filled }} / {{ $indicatorCount }}
                                </span>
                            </td>
                            <td data-label="{{ __('admin.col_status') }}"><span class="a-pill" data-status="{{ $s->status->value }}">{{ __($s->status->labelKey()) }}</span></td>
                            <td data-label="{{ __('admin.col_updated') }}"><span>{{ $s->updated_at?->displayed()->isoFormat('D MMM YYYY') ?? '' }}</span></td>
                            <td data-label="">
                                <a href="{{ route('submissions.edit', ['locale' => $locale, 'submission' => $s->id]) }}">{{ __('d3_look.open_link') }}<span class="c-sr">: {{ __('extranet.dash_return_for', ['period' => $s->period]) }}{{ $s->country ? ', '.$s->country->name : '' }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $submissions->links() }}
        @endif
    </section>
</div>
@endsection
