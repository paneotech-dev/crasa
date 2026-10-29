<?php

namespace App\Services;

use App\Models\Meeting;
use Illuminate\Support\Collection;

/**
 * The meetings calendar as an iCalendar file.
 *
 * Screen 12 draws an export beside the month grid and states the rule the
 * feed has to keep: "The feed carries only the meetings your category can
 * see." The caller filters through the Gate; this only formats.
 *
 * Written by hand rather than with a package. RFC 5545 is a short
 * specification for what this needs — VEVENT, a UID, a stamp, dates — and
 * the two things it is easy to get wrong are both cheap to get right: CRLF
 * line endings, which some clients require absolutely, and escaping commas,
 * semicolons and newlines inside a text value.
 *
 * A meeting a regulator cannot put in their own calendar is a meeting they
 * will miss.
 */
class CalendarFeed
{
    /** @param Collection<int, Meeting> $meetings */
    public function ics(Collection $meetings): string
    {
        return $this->calendar(__('extranet.calendar'), $meetings->flatMap(fn (Meeting $meeting) => $this->event($meeting))->all());
    }

    /**
     * One public event, for "Add to my calendar" on the events pages.
     *
     * The description is the committee and the event's own page rather
     * than the agenda: a public agenda can carry markup and placeholder
     * text, and the page is where the current version of it lives.
     */
    public function single(Meeting $meeting, string $url): string
    {
        return $this->calendar($meeting->title, $this->event($meeting, $url));
    }

    /**
     * The same event as a Google Calendar template link. Same dates as the
     * file, the end exclusive, so the two never disagree.
     */
    public function googleUrl(Meeting $meeting, string $url): string
    {
        [$start, $end] = $this->dates($meeting);

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $meeting->title,
            'dates' => $start->format('Ymd').'/'.$end->format('Ymd'),
            'details' => $this->publicDetails($meeting, $url),
            'location' => (string) $meeting->location,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The same event for Outlook on the web (personal and work accounts
     * both open this compose link). All-day, end exclusive, like the file.
     */
    public function outlookUrl(Meeting $meeting, string $url): string
    {
        [$start, $end] = $this->dates($meeting);

        return 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query([
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'allday' => 'true',
            'subject' => $meeting->title,
            'startdt' => $start->format('Y-m-d'),
            'enddt' => $end->format('Y-m-d'),
            'body' => $this->publicDetails($meeting, $url),
            'location' => (string) $meeting->location,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<int, string>  $events
     */
    private function calendar(string $name, array $events): string
    {
        $lines = array_merge([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CRASA//Digital Anchor Ecosystem//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
        ], $events);

        $lines[] = 'END:VCALENDAR';

        // CRLF, which some clients require absolutely and which is easy to
        // lose to a stray implode("\n").
        return implode("\r\n", $lines)."\r\n";
    }

    /** @return array{0: \Carbon\CarbonInterface, 1: \Carbon\CarbonInterface} */
    private function dates(Meeting $meeting): array
    {
        $start = $meeting->starts_on;

        // DTEND is exclusive for an all-day event, so a one-day meeting ends
        // the following day. Getting this wrong shows a meeting a day short.
        return [$start, ($meeting->ends_on ?: $start)->copy()->addDay()];
    }

    private function publicDetails(Meeting $meeting, string $url): string
    {
        return collect([$meeting->committee?->name, $url])->filter()->implode("\n");
    }

    /** @return array<int, string> */
    private function event(Meeting $meeting, ?string $url = null): array
    {
        [$start, $end] = $this->dates($meeting);

        $lines = [
            'BEGIN:VEVENT',
            'UID:meeting-'.$meeting->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
            'DTEND;VALUE=DATE:'.$end->format('Ymd'),
            'SUMMARY:'.$this->escape($meeting->title),
        ];

        if ($meeting->location) {
            $lines[] = 'LOCATION:'.$this->escape($meeting->location);
        }

        $description = $url !== null
            ? $this->publicDetails($meeting, $url)
            : collect([$meeting->committee?->name, $meeting->agenda])->filter()->implode("\n");

        if ($url !== null) {
            $lines[] = 'URL:'.$url;
        }

        if ($description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escape($description);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /** RFC 5545 section 3.3.11. Backslash first, or it escapes its own escapes. */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\;'],
            $value
        );
    }
}
