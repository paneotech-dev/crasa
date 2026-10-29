<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Nightly integrity check.
 *
 * A purge rehearsal left 337 orphaned translation rows and four reseeds in
 * one afternoon made another 525, and every one of them was invisible:
 * nothing errored and no page broke. An orphan matters because ids are
 * reused, so it becomes the translation of the next record to take its id.
 *
 * Reports rather than deletes. A scheduled task that removes rows on its own
 * is a scheduled task nobody wants running unattended, and the report is
 * written to the log at warning level so it is found without anyone watching
 * the output.
 *
 * This needs the cron entry that runs `schedule:run`, which this application
 * does not yet have. See docs/BACKUP_SETUP.md and docs/PRE_WORKSHOP.md item 2.
 */
Schedule::command('data:check')->dailyAt('03:20')->withoutOverlapping();

/*
 * The notification digest, RP-05.
 *
 * Once a day at 06:40, which is before the working day in every one of the
 * fourteen member states' time zones and after any overnight import. An
 * account set to weekly is only included when its own week is up, tracked
 * per account rather than on a fixed weekday, so fourteen regulators do not
 * all receive mail in the same minute.
 *
 * Same caveat as the check above: this needs the cron entry that runs
 * `schedule:run`, which this application does not yet have.
 */
Schedule::command('notifications:digest')->dailyAt('06:40')->withoutOverlapping();

/*
 * Day 3: docs. The recycle bin of the member area's folders keeps a
 * deleted file for thirty days; after that it is removed with its
 * versions. Same caveat: this needs the cron entry that runs schedule:run.
 */
Schedule::command('documents:purge-bin')->dailyAt('03:40')->withoutOverlapping();
