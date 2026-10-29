<?php

namespace App\Support;

use App\Models\Meeting;

/**
 * The body that meets, as the calendar shows it: a short code, a name and a
 * colour. The code is printed on every chip, so colour is never the only
 * thing that tells two committees apart (WCAG 1.4.1). Every colour carries
 * white text at 4.5:1 or better (lowest: green, 4.54).
 *
 * Keyed by meetings.committee_code. CRASA's standing committees are more
 * than the committee records drawn for the member area, so the code is the
 * body named in the event's own title, and the committee record is linked
 * as well where one exists.
 */
final class EventGroup
{
    /** code => [name, colour] */
    public const GROUPS = [
        'AGM' => ['Annual General Meeting', '#0B5F3E'],
        'EXCO' => ['Executive Committee', '#2F4F8F'],
        'PC' => ['Postal Committee', '#9A4E06'],
        'ECC' => ['Electronic Communications Committee', '#1F6F9A'],
        'SFMC' => ['Spectrum and Frequency Management Committee', '#0E6F7A'],
        'UASC' => ['Universal Access and Service Committee', '#0F8757'],
        'LPC' => ['Legal and Policy Committee', '#9B2226'],
        'CC' => ['Consumer Committee', '#A3306A'],
        'ERC' => ['Economic Regulation Committee', '#5C3404'],
        'HRDC' => ['Human Resources Development Committee', '#6B4E16'],
        'FAC' => ['Finance and Audit Committee', '#3F5264'],
        'ITSC' => ['Information Technology Steering Committee', '#2B5B6B'],
        'CSPC' => ['Strategic Planning Committee', '#4A5A1F'],
        'CRASA' => ['CRASA-wide events and workshops', '#25384A'],
    ];

    private function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $colour,
    ) {}

    public static function for(Meeting $meeting): self
    {
        return self::code($meeting->committee_code ?: 'CRASA');
    }

    public static function code(string $code): self
    {
        [$name, $colour] = self::GROUPS[$code] ?? self::GROUPS['CRASA'];

        return new self(isset(self::GROUPS[$code]) ? $code : 'CRASA', $name, $colour);
    }

    /** The name in the reader's language where the lang file has one. */
    public function label(): string
    {
        $key = 'events_e.group_'.strtolower($this->code);

        return __($key) !== $key ? __($key) : $this->name;
    }

    /** @return array<int, self> the groups that have at least one of these meetings, in palette order */
    public static function present(iterable $meetings): array
    {
        $codes = [];
        foreach ($meetings as $meeting) {
            $codes[self::for($meeting)->code] = true;
        }

        return array_values(array_map(
            fn (string $code) => self::code($code),
            array_filter(array_keys(self::GROUPS), fn ($code) => isset($codes[$code]))
        ));
    }
}
