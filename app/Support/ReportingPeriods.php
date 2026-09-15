<?php

namespace App\Support;

/**
 * Which reporting periods are open, and when each is due.
 *
 * WHY THIS IS ONE CLASS. The submission form, the dashboard and the
 * completeness matrix all need the same answer, and it was being derived
 * in the controller as "this year and the two before it" with no due date
 * anywhere. A dashboard that says a return is due has to say when.
 *
 * THE DUE DATE IS A CONVENTION, NOT A RULE FROM CRASA. The Secretariat has
 * not set one and this build must not invent a deadline and present it as
 * the association's. So the date carried here is stated as the end of the
 * quarter after the period closes and is labelled as provisional wherever
 * it is shown, and Question 11 in docs/QUESTIONS.md asks CRASA for the
 * real cycle.
 */
final class ReportingPeriods
{
    /** How many years back a return can still be opened. */
    public const YEARS_OPEN = 3;

    /**
     * The open annual periods, newest first.
     *
     * @return list<array{period: string, due_on: \Illuminate\Support\Carbon, provisional: bool}>
     */
    public static function open(): array
    {
        $periods = [];
        $year = (int) now()->year;

        for ($offset = 0; $offset < self::YEARS_OPEN; $offset++) {
            $periods[] = self::describe((string) ($year - $offset));
        }

        return $periods;
    }

    /** @return array{period: string, due_on: \Illuminate\Support\Carbon, provisional: bool} */
    public static function describe(string $period): array
    {
        return [
            'period' => $period,
            'due_on' => self::dueOn($period),
            'provisional' => true,
        ];
    }

    /**
     * The end of the first quarter after the period closes.
     *
     * An annual return for 2025 is due 31 March 2026. A quarterly one for
     * 2025-Q3 is due at the end of December.
     */
    public static function dueOn(string $period): \Illuminate\Support\Carbon
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $period, $match) === 1) {
            $quarter = (int) $match[2];

            return \Illuminate\Support\Carbon::create((int) $match[1], $quarter * 3, 1)
                ->addMonthsNoOverflow(3)
                ->endOfMonth()
                ->startOfDay();
        }

        return \Illuminate\Support\Carbon::create((int) $period + 1, 3, 31)->startOfDay();
    }

    public static function isOverdue(string $period): bool
    {
        return self::dueOn($period)->isPast();
    }
}
