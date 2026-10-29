{{--
    "Add to my calendar": the event as an .ics file (the generator the
    committee calendars use) and as a Google Calendar link. Separate from
    filing arrival and departure, which is a different action for a
    different reader. Shown only for dated public events still to come.
--}}
@php
    $eventUrl = route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]);
    $calendarId = 'addcal-'.$meeting->id.($compact ?? false ? '-card' : '');
@endphp
@if (! $meeting->date_unknown && $meeting->starts_on && ! ($meeting->ends_on ?: $meeting->starts_on)->isPast())
    <div class="c-addcal @if ($compact ?? false) c-addcal--compact @endif" role="group" aria-labelledby="{{ $calendarId }}">
        <span class="c-addcal__label" id="{{ $calendarId }}">{{ __('events.add_to_calendar') }}</span>
        <a class="c-addcal__action" href="{{ route('events.ics', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}" download>{{ __('events.calendar_ics') }}<span class="c-sr"> {{ __('events.calendar_for', ['event' => $meeting->title]) }}</span></a>
        <a class="c-addcal__action" href="{{ app(\App\Services\CalendarFeed::class)->googleUrl($meeting, $eventUrl) }}" target="_blank" rel="noopener">{{ __('events.calendar_google') }}<span class="c-sr"> {{ __('events.calendar_for', ['event' => $meeting->title]) }} {{ __('events.new_tab') }}</span></a>
    </div>
@endif
