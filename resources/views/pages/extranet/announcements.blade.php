@extends('layouts.extranet')

{{--
    From the Secretariat: notices as a member state reads them. Extranet
    item 10, redrawn on Day 3 (8c) as a list grouped by month, each notice
    with its type, a two-line summary, its attachments and its read state.

    Members only, and the model's scope is what makes that true: an
    announcement narrowed to one committee is not listed here, not linked
    and not reachable by guessing a slug for anyone without that seat.
--}}

@section('title', __('announcements.title'))
@section('note', __('announcements.note'))

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/member-home.css') }}">
@endpush

@section('content')
    @if ($announcements->isEmpty())
        <div class="mh-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('announcements.none_title') }}</div>
                <div class="a-empty__body">{{ __('announcements.none_body') }}</div>
            </div>
        </div>
    @else
        <div class="mh mh-noticepage">
            <p class="mh-meta mh-noticepage__count">{{ trans_choice('d3_look.unread_count', $unread, ['count' => $unread]) }}</p>
            @foreach ($months as $month => $items)
                <section class="mh-card mh-month" aria-labelledby="mh-m-{{ $month }}">
                    <h2 class="mh-card__title" id="mh-m-{{ $month }}">
                        {{ $month !== '' ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $month.'-01')->locale(app()->getLocale())->isoFormat('MMMM YYYY') : __('d3_look.undated') }}
                    </h2>
                    @include('pages.extranet._notice-list', ['items' => $items])
                </section>
            @endforeach
        </div>

        {{ $announcements->links() }}
    @endif
@endsection
