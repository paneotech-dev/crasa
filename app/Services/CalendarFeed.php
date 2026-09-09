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
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CRASA//Digital Anchor Ecosystem//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape(__('extranet.calendar')),
        ];

        foreach ($meetings as $meeting) {
            $lines = array_merge($lines, $this->event($meeting));
        }

        $lines[] = 'END:VCALENDAR';

        // CRLF, which some clients require absolutely and which is easy to
        // lose to a stray implode("\n").
        return implode("\r\n", $lines)."\r\n";
    }

    /** @return array<int, string> */
    private function event(Meeting $meeting): array
    {
        $start = $meeting->starts_on;
        // DTEND is exclusive for an all-day event, so a one-day meeting ends
        // the following day. Getting this wrong shows a meeting a day short.
        $end = ($meeting->ends_on ?: $start)->copy()->addDay();

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

        $description = collect([
            $meeting->committee?->name,
            $meeting->agenda,
        ])->filter()->implode("\n");

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
