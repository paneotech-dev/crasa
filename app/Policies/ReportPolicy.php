<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Country;
use App\Models\Report;
use App\Models\User;

/**
 * Who may generate and read a regional brief.
 *
 * Screen 13's matrix grants a member state representative ExportReports
 * CONDITIONALLY, not outright. The condition is the one that runs through
 * this whole platform: their own member state and no other.
 *
 * That makes two rules rather than one.
 *
 * A brief on a member state is allowed only for their own. And a brief on
 * an indicator is not allowed at all for a conditional holder, because an
 * indicator brief is by definition the figure across all fourteen states,
 * which is exactly what a conditional grant withholds. There is no version
 * of "your own country's row of a fourteen-country table" that is still
 * that table.
 */
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ExportReports);
    }

    public function view(User $user, Report $report): bool
    {
        return $this->onSubject($user, $report->subject_type, $report->subject_key);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ExportReports);
    }

    /** Whether this account may build a brief on this particular subject. */
    public function onSubject(User $user, string $type, string $key): bool
    {
        if (! $user->hasPermission(Permission::ExportReports)) {
            return false;
        }

        if (! $user->permissionIsConditional(Permission::ExportReports)) {
            return true;
        }

        if ($type !== Report::COUNTRY) {
            return false;
        }

        return Country::where('iso_code', $key)->value('id') === $user->country_id;
    }

    public function delete(User $user, Report $report): bool
    {
        return $this->view($user, $report);
    }
}
