@extends('layouts.admin')

{{--
    Member states' proposed changes to their own records.

    The same act as the Lilongwe confirmation session, arriving one at a
    time rather than fourteen in a room.
--}}

@section('title', __('authority.review_title'))
@section('note', __('authority.review_note'))

@section('crumbs')
    <li aria-current="page">{{ __('authority.review_title') }}</li>
@endsection

@section('content')
    <section class="a-card a-card--flush">
        <div class="a-card__head">
            <h2>{{ __('authority.awaiting') }}</h2>
            <span class="a-card__meta">{{ $open->count() }}</span>
        </div>

        @if ($open->isEmpty())
            <div class="a-empty">
                <div class="a-empty__title">{{ __('authority.review_empty_title') }}</div>
                <div class="a-empty__body">{{ __('authority.review_empty_body') }}</div>
            </div>
        @else
            <ul class="a-rows" role="list">
                @foreach ($open as $change)
                    <li class="a-row">
                        <span class="a-row__grow">
                            <a href="{{ route('admin.authority-changes.show', ['locale' => app()->getLocale(), 'change' => $change->id]) }}">{{ $change->country->name }}</a>
                            <span class="a-row__who">{{ $change->payload['name'] ?? '' }}</span>
                        </span>
                        <span class="a-row__time">{{ $change->submitted_at?->isoFormat('D MMMM YYYY') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($decided->isNotEmpty())
        <section class="a-card a-card--flush">
            <div class="a-card__head">
                <h2>{{ __('authority.decided') }}</h2>
            </div>

            <ul class="a-rows" role="list">
                @foreach ($decided as $change)
                    <li class="a-row">
                        <span class="a-row__grow">{{ $change->country->name }}</span>
                        <span class="a-pill" data-status="{{ $change->status === 'approved' ? 'validated' : 'returned' }}">{{ __('authority.status_'.$change->status) }}</span>
                        <span class="a-row__time">{{ $change->reviewed_at?->isoFormat('D MMMM YYYY') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
