@extends('layouts.admin')

{{--
    The Secretariat's review queue for member contributions. RP-06.

    The same shape as the data-return queue, because it is the same job done
    by the same three people. Waiting-for-you first, decided below it.
--}}

@section('title', __('contributions.review_title'))
@section('note', __('contributions.review_note'))

@section('crumbs')
    <li aria-current="page">{{ __('contributions.review_title') }}</li>
@endsection

@section('content')
    <section class="a-card a-card--flush">
        <div class="a-card__head">
            <h2>{{ __('contributions.awaiting') }}</h2>
            <span class="a-card__meta">{{ $awaiting->count() }}</span>
        </div>

        @if ($awaiting->isEmpty())
            <div class="a-empty">
                <div class="a-empty__title">{{ __('contributions.review_empty_title') }}</div>
                <div class="a-empty__body">{{ __('contributions.review_empty_body') }}</div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('contributions.col_instrument') }}</th>
                            <th scope="col">{{ __('contributions.col_country') }}</th>
                            <th scope="col">{{ __('contributions.col_sector') }}</th>
                            <th scope="col">{{ __('contributions.col_submitted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($awaiting as $instrument)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('admin.contributions.show', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">{{ $instrument->translated('title', $instrument->reference_number ?? $instrument->slug) }}</a>
                                    <span class="a-table__sub">{{ $instrument->reference_number }}</span>
                                </th>
                                <td>{{ $instrument->country?->name }}</td>
                                <td>{{ $instrument->sector?->name }}</td>
                                <td>{{ $instrument->submitted_at?->displayed()->isoFormat('D MMMM YYYY') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($recent->isNotEmpty())
        <section class="a-card a-card--flush">
            <div class="a-card__head">
                <h2>{{ __('contributions.recent_decisions') }}</h2>
            </div>

            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('contributions.col_instrument') }}</th>
                            <th scope="col">{{ __('contributions.col_country') }}</th>
                            <th scope="col">{{ __('contributions.col_state') }}</th>
                            <th scope="col">{{ __('contributions.col_updated') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $instrument)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('admin.contributions.show', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">{{ $instrument->translated('title', $instrument->reference_number ?? $instrument->slug) }}</a>
                                </th>
                                <td>{{ $instrument->country?->name }}</td>
                                <td><span class="a-pill" data-status="{{ $instrument->contribution_state?->value }}">{{ __($instrument->contribution_state->labelKey()) }}</span></td>
                                <td>{{ $instrument->reviewed_at?->displayed()->isoFormat('D MMMM YYYY') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
