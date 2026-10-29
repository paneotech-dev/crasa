<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The platform's clock as a reader in Gaborone sees it.
 *
 * Storage stays UTC (app.timezone and the database session are both pinned
 * to UTC, see config/database.php). What a reader is shown, and what "today"
 * means when the calendar or the meeting lists decide what is upcoming, is
 * the Secretariat's zone, config('crasa.timezone'). Computed in UTC, "today"
 * was one day behind every evening between ten and midnight in Gaborone.
 *
 * Views use the Carbon macro ->displayed() (registered in
 * AppServiceProvider) to show a stored instant in that zone.
 */
final class LocalTime
{
    public static function zone(): string
    {
        return (string) config('crasa.timezone', 'Africa/Gaborone');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    /** Today's date in the display zone, as Y-m-d. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /** A stored instant shown in the display zone. */
    public static function display(CarbonInterface $instant): CarbonInterface
    {
        return $instant->copy()->setTimezone(self::zone());
    }
}
