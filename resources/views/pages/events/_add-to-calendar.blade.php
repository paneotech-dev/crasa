{{--
    "Add to my calendar": Google, Outlook and an .ics file for any other
    calendar. The three options live on the event page (anchored
    #add-to-calendar); a card or a summary passes 'link' => true and gets
    one "Add to calendar" link to them instead. Shown only for dated events
    the reader may open that have not ended and are not cancelled. The event title is in each link's
    accessible name, so a list of cards does not read as "Google, Google".
--}}
@php
    $eventUrl = route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]);
    $calendarId = ($idPrefix ?? '').'addcal-'.$meeting->id.($compact ?? false ? '-card' : '');
    $feed = app(\App\Services\CalendarFeed::class);
@endphp
@if (! $meeting->date_unknown && $meeting->starts_on && ! $meeting->hasEnded() && ! $meeting->isCancelled())
    @if ($link ?? false)
    <a class="e-addcal__link" href="{{ $eventUrl }}#add-to-calendar">{{ __('d3_polish.add_to_calendar') }}<span class="c-sr"> {{ __('events.calendar_for', ['event' => $meeting->title]) }}</span></a>
    @else
    <div @if ($anchor ?? false) id="add-to-calendar" tabindex="-1" @endif class="e-addcal @if ($compact ?? false) e-addcal--compact @endif" role="group" aria-labelledby="{{ $calendarId }}">
        <span class="e-addcal__label" id="{{ $calendarId }}">{{ __('events.add_to_calendar') }}</span>
        <span class="e-addcal__links">
            <a class="e-addcal__action" href="{{ $feed->googleUrl($meeting, $eventUrl) }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>Google<span class="c-sr"> {{ __('events.calendar_for', ['event' => $meeting->title]) }} {{ __('events.new_tab') }}</span></a>
            <a class="e-addcal__action" href="{{ $feed->outlookUrl($meeting, $eventUrl) }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>Outlook<span class="c-sr"> {{ __('events.calendar_for', ['event' => $meeting->title]) }} {{ __('events.new_tab') }}</span></a>
            <a class="e-addcal__action" href="{{ route('events.ics', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}" download>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>.ics<span class="c-sr"> {{ __('e.ics_file') }} {{ __('events.calendar_for', ['event' => $meeting->title]) }}</span></a>
        </span>
    </div>
    @endif
@endif
