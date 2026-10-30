{{--
    The CRASA mailbox's copy of a delegate registration (client item 1e).
    What the delegate filed, in the Secretariat's words, and where to act
    on it: the back office list.
--}}
@php
    $none = __('delegates.confirmation_none');
    $meeting = $registration->meeting;
    $lang = fn ($code) => $code ? __('m3_deleg.lang_'.$code) : $none;
@endphp

<p>{{ __('m3_deleg.copy_intro') }}</p>

<p>
    <strong>{{ $meeting->title }}</strong><br>
    {{ $meeting->dateRange() }}
    @if ($meeting->location)<br>{{ $meeting->location }}@endif
</p>

<p>
    <strong>{{ __('delegates.col_name') }}:</strong> {{ $registration->delegate_name }}<br>
    <strong>{{ __('delegates.col_email') }}:</strong> {{ $registration->delegate_email }}<br>
    @if ($registration->delegate_role)<strong>{{ __('delegates.col_role') }}:</strong> {{ $registration->delegate_role }}<br>@endif
    @if ($registration->country)<strong>{{ __('delegates.col_country') }}:</strong> {{ $registration->country->name }}<br>@endif
    @if ($registration->authority)<strong>{{ __('d3_people.authority') }}:</strong> {{ $registration->authority->acronym }}<br>@endif
    <strong>{{ __('m3_deleg.preferred_language') }}:</strong> {{ $lang($registration->preferred_language) }}<br>
    <strong>{{ __('m3_deleg.interpretation') }}:</strong> {{ $registration->interpretation_into ? __('m3_deleg.interp_into_'.$registration->interpretation_into) : __('m3_deleg.interp_none') }}<br>
    <strong>{{ __('delegates.col_arrival_on') }}:</strong> {{ $registration->arrival_on?->isoFormat('D MMMM YYYY') ?? $none }}<br>
    <strong>{{ __('delegates.col_departure_on') }}:</strong> {{ $registration->departure_on?->isoFormat('D MMMM YYYY') ?? $none }}
</p>

@if ($registration->notes)
    <p><strong>{{ __('delegates.col_notes') }}</strong><br>{{ $registration->notes }}</p>
@endif

<p>{{ __('m3_deleg.copy_where') }}<br>{{ route('admin.registrations', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}</p>
