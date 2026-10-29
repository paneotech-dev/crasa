@extends('layouts.public')

@section('title', __('d3_people.done_title'))

@php
    $locale = app()->getLocale();
    $st = $registration->status ?: 'filed';
    $fmt = fn ($d) => $d?->copy()->locale($locale)->isoFormat('D MMMM YYYY');
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/d3-people-public.css') }}">
@endpush

@section('content')
    <div class="c-section c-section--title">
        <div class="c-page">
            <p class="b__eyebrow">{{ $meeting->title }}</p>
            <h1>{{ __('d3_people.done_title') }}</h1>
            <p class="c-lead">{{ __('d3_people.done_lead', ['status' => __('d3_people.reg_'.$st)]) }}</p>
        </div>
    </div>

    <div class="c-section">
        <div class="c-page">
            @if (session('status'))
                <div class="u-success" role="status">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <h2 class="d3p-reg__h">{{ __('d3_people.done_sent') }}</h2>
            <dl class="u-facts d3p-done">
                <div><dt>{{ __('delegates.col_name') }}</dt><dd>{{ $registration->delegate_name }}</dd></div>
                <div><dt>{{ __('delegates.col_email') }}</dt><dd>{{ $registration->delegate_email }}</dd></div>
                @if ($registration->delegate_role)<div><dt>{{ __('delegates.col_role') }}</dt><dd>{{ $registration->delegate_role }}</dd></div>@endif
                @if ($registration->country)<div><dt>{{ __('delegates.col_country') }}</dt><dd>{{ $registration->country->name }}</dd></div>@endif
                @if ($registration->authority)<div><dt>{{ __('d3_people.authority') }}</dt><dd>{{ $registration->authority->acronym }}</dd></div>@endif
                <div><dt>{{ __('delegates.arrival') }}</dt><dd>{{ collect([$fmt($registration->arrival_on), $registration->arrival_at ? substr((string) $registration->arrival_at, 0, 5) : null, $registration->arrival_point, $registration->arrival_reference])->filter()->implode(', ') }}</dd></div>
                <div><dt>{{ __('delegates.departure') }}</dt><dd>{{ collect([$fmt($registration->departure_on), $registration->departure_at ? substr((string) $registration->departure_at, 0, 5) : null, $registration->departure_reference])->filter()->implode(', ') }}</dd></div>
                @if ($registration->notes)<div><dt>{{ __('delegates.col_notes') }}</dt><dd>{{ $registration->notes }}</dd></div>@endif
                <div><dt>{{ __('d3_people.status') }}</dt><dd>{{ __('d3_people.reg_'.$st) }}</dd></div>
            </dl>

            <h2 class="d3p-reg__h">{{ __('d3_people.reg_next_title') }}</h2>
            <ul class="d3p-reg__list">
                <li>{{ __('d3_people.reg_next_copy') }}</li>
                <li>{{ __('d3_people.reg_next_review', ['date' => $fmt($meeting->registrationReplyBy())]) }}</li>
                <li>{{ $st === 'confirmed' ? __('d3_people.done_confirmed') : __('d3_people.done_edit') }}</li>
            </ul>

            <div class="u-actions">
                @if ($st !== 'confirmed' && $meeting->registrationIsOpen())
                    <a class="u-btn u-btn--primary" href="{{ route('events.register', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('d3_people.reg_edit') }}</a>
                @endif
                <a class="u-btn u-btn--secondary" href="{{ route('events.show', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('d3_people.back_to_event') }}</a>
            </div>
        </div>
    </div>
@endsection
