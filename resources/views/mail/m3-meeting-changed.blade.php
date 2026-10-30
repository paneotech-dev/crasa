{{--
    A registered delegate told that their meeting changed (client item 1e):
    cancelled, postponed, or given a new date. Sent once per change.
--}}
@php($meeting = $registration->meeting)

<p>{{ __('m3_deleg.change_greeting', ['name' => $registration->delegate_name]) }}</p>

<p><strong>{{ $meeting->title }}</strong></p>

<p>{{ __('m3_deleg.change_body_'.$change, ['date' => $meeting->date_unknown ? '' : $meeting->dateRange()]) }}</p>

@if ($meeting->status_note)
    <p>{{ $meeting->status_note }}</p>
@endif

<p>{{ __('m3_deleg.change_page') }}<br>{{ route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}</p>
