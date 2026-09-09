{{--
    The delegate's own copy. Plain text through the platform's mail layout,
    because a delegate reads this on a telephone in an airport.

    It states what was recorded and how to change it. It does not remind,
    chase or ask for anything: this is the collecting half.
--}}
@php
    $none = __('delegates.confirmation_none');
    $meeting = $registration->meeting;
@endphp

<p>{{ __('delegates.confirmation_intro') }}</p>

<p>
    <strong>{{ $meeting->title }}</strong><br>
    {{ $meeting->starts_on->isoFormat('D MMMM YYYY') }}@if ($meeting->ends_on && ! $meeting->ends_on->isSameDay($meeting->starts_on)) to {{ $meeting->ends_on->isoFormat('D MMMM YYYY') }}@endif
    @if ($meeting->location)<br>{{ $meeting->location }}@endif
</p>

<p>
    <strong>{{ __('delegates.col_name') }}:</strong> {{ $registration->delegate_name }}<br>
    <strong>{{ __('delegates.col_email') }}:</strong> {{ $registration->delegate_email }}<br>
    @if ($registration->delegate_role)<strong>{{ __('delegates.col_role') }}:</strong> {{ $registration->delegate_role }}<br>@endif
    @if ($registration->country)<strong>{{ __('delegates.col_country') }}:</strong> {{ $registration->country->name }}<br>@endif
</p>

<p>
    <strong>{{ __('delegates.arrival') }}</strong><br>
    {{ __('delegates.col_arrival_on') }}: {{ $registration->arrival_on?->isoFormat('D MMMM YYYY') ?? $none }}<br>
    {{ __('delegates.col_arrival_at') }}: {{ $registration->arrival_at ?? $none }}<br>
    {{ __('delegates.col_arrival_point') }}: {{ $registration->arrival_point ?? $none }}<br>
    {{ __('delegates.col_arrival_reference') }}: {{ $registration->arrival_reference ?? $none }}
</p>

<p>
    <strong>{{ __('delegates.departure') }}</strong><br>
    {{ __('delegates.col_departure_on') }}: {{ $registration->departure_on?->isoFormat('D MMMM YYYY') ?? $none }}<br>
    {{ __('delegates.col_departure_at') }}: {{ $registration->departure_at ?? $none }}<br>
    {{ __('delegates.col_departure_reference') }}: {{ $registration->departure_reference ?? $none }}
</p>

@if ($registration->notes)
    <p><strong>{{ __('delegates.col_notes') }}</strong><br>{{ $registration->notes }}</p>
@endif

<p>{{ __('delegates.confirmation_amend') }}</p>
<p>{{ __('delegates.confirmation_boundary') }}</p>
