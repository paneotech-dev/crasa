<?php

namespace App\Support;

use App\Enums\Grant;
use App\Enums\Permission;
use App\Enums\Role;

/**
 * The permission matrix from screen 13, as data.
 *
 * Y granted. C granted for the user's own country or authority only.
 * N not granted.
 *
 * committee_member is not a column on the screen's matrix. It reads what a
 * viewer reads and adds committee workspace and member-graded documents at
 * its own committee's clearance, which is expressed here as conditional.
 */
final class PermissionMatrix
{
    private const Y = 'granted';

    private const C = 'conditional';

    private const N = 'denied';

    /**
     * @return array<string, array<string, string>> permission value => role value => grant
     */
    private static function table(): array
    {
        $a = Role::Administrator->value;
        $e = Role::Editor->value;
        $r = Role::MemberStateRep->value;
        $c = Role::CommitteeMember->value;
        $v = Role::Viewer->value;
        $m = Role::Moderator->value;
        $au = Role::Auditor->value;

        return [
            // Content
            Permission::ViewPublishedContent->value => [$a => self::Y, $e => self::Y, $r => self::Y, $c => self::Y, $v => self::Y, $m => self::Y, $au => self::Y],
            Permission::ManageContent->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::EditTranslations->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::PublishContent->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::EditHomepage->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::ReadContent->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N, $m => self::Y, $au => self::Y],
            Permission::ApproveContent->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::Y, $au => self::N],
            Permission::ApprovePublications->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::Y, $au => self::N],
            Permission::ApproveNews->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::Y, $au => self::N],
            Permission::ApproveEvents->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::Y, $au => self::N],
            Permission::ApprovePages->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::Y, $au => self::N],
            Permission::ApproveSocial->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::Y, $au => self::N],
            Permission::PublishWithoutApproval->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::DeleteContent->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],

            // Regulatory data
            Permission::ViewPublishedIndicators->value => [$a => self::Y, $e => self::Y, $r => self::Y, $c => self::Y, $v => self::Y, $m => self::Y, $au => self::Y],
            Permission::SubmitCountryReturn->value => [$a => self::N, $e => self::N, $r => self::C, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::ValidateSubmission->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::RestoreVersion->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::ExportReports->value => [$a => self::Y, $e => self::Y, $r => self::C, $c => self::N, $v => self::N, $m => self::N, $au => self::N],

            // Extranet
            Permission::ReachCommitteeWorkspace->value => [$a => self::Y, $e => self::Y, $r => self::C, $c => self::C, $v => self::N, $m => self::N, $au => self::N],
            Permission::OpenMemberDocuments->value => [$a => self::Y, $e => self::Y, $r => self::C, $c => self::C, $v => self::N, $m => self::N, $au => self::N],
            Permission::OpenConfidentialDocuments->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::OpenSecretariatDocuments->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::UploadToCountryFolder->value => [$a => self::Y, $e => self::N, $r => self::C, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::ManageMeetings->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],

            // Users and security
            Permission::ManageUsers->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::AssignRoles->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
            Permission::ReadAuditLog->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::Y],
            Permission::ManageSettings->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N, $m => self::N, $au => self::N],
        ];
    }

    public static function grant(Role $role, Permission $permission): Grant
    {
        $stored = self::stored()[$role->value] ?? null;

        if ($stored !== null) {
            return isset($stored[$permission->value])
                ? Grant::from($stored[$permission->value])
                : self::defaultGrant($role->value, $permission);
        }

        return self::defaultGrant($role->value, $permission);
    }

    /**
     * The grant as shipped in code, before anyone edits a role on the roles
     * screen. The presets are seeded from this, and it is the answer while
     * the roles table is empty or missing (a fresh install mid-migration).
     */
    public static function defaultGrant(string $role, Permission $permission): Grant
    {
        $row = self::table()[$permission->value] ?? [];

        return Grant::from($row[$role] ?? self::N);
    }

    /**
     * The grant for a role kept in the roles table, by its key. Used for a
     * role created on the roles screen, which has no enum case.
     */
    public static function grantForKey(string $key, Permission $permission): Grant
    {
        $stored = self::stored()[$key] ?? null;

        if ($stored === null) {
            return self::defaultGrant($key, $permission);
        }

        return isset($stored[$permission->value])
            ? Grant::from($stored[$permission->value])
            : self::defaultGrant($key, $permission);
    }

    /**
     * Every role's grid from the roles table, read once per request.
     *
     * @return array<string, array<string, string>> role key => permission => grant
     */
    private static function stored(): array
    {
        $app = app();

        if ($app->bound('crasa.role-grid')) {
            return $app->make('crasa.role-grid');
        }

        $grid = [];

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('role_permissions')) {
                $rows = \Illuminate\Support\Facades\DB::table('role_permissions')
                    ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
                    ->get(['roles.key', 'role_permissions.permission', 'role_permissions.grant']);

                foreach ($rows as $row) {
                    $grid[$row->key][$row->permission] = $row->grant;
                }
            }
        } catch (\Throwable) {
            $grid = [];
        }

        $app->instance('crasa.role-grid', $grid);

        return $grid;
    }

    /** Forget the grid read for this request, after the roles screen saves. */
    public static function flush(): void
    {
        app()->forgetInstance('crasa.role-grid');
    }

    /** @return list<Permission> */
    public static function permissions(): array
    {
        return Permission::cases();
    }
}
