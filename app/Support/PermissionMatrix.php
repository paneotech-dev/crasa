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

        return [
            // Content
            Permission::ViewPublishedContent->value => [$a => self::Y, $e => self::Y, $r => self::Y, $c => self::Y, $v => self::Y],
            Permission::ManageContent->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N],
            Permission::EditTranslations->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N],
            Permission::PublishContent->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N],
            Permission::EditHomepage->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N],
            Permission::DeleteContent->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],

            // Regulatory data
            Permission::ViewPublishedIndicators->value => [$a => self::Y, $e => self::Y, $r => self::Y, $c => self::Y, $v => self::Y],
            Permission::SubmitCountryReturn->value => [$a => self::N, $e => self::N, $r => self::C, $c => self::N, $v => self::N],
            Permission::ValidateSubmission->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],
            Permission::RestoreVersion->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],
            Permission::ExportReports->value => [$a => self::Y, $e => self::Y, $r => self::C, $c => self::N, $v => self::N],

            // Extranet
            Permission::ReachCommitteeWorkspace->value => [$a => self::Y, $e => self::Y, $r => self::C, $c => self::C, $v => self::N],
            Permission::OpenMemberDocuments->value => [$a => self::Y, $e => self::Y, $r => self::C, $c => self::C, $v => self::N],
            Permission::OpenConfidentialDocuments->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],
            Permission::OpenSecretariatDocuments->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],
            Permission::UploadToCountryFolder->value => [$a => self::Y, $e => self::N, $r => self::C, $c => self::N, $v => self::N],
            Permission::ManageMeetings->value => [$a => self::Y, $e => self::Y, $r => self::N, $c => self::N, $v => self::N],

            // Users and security
            Permission::ManageUsers->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],
            Permission::AssignRoles->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],
            Permission::ReadAuditLog->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],
            Permission::ManageSettings->value => [$a => self::Y, $e => self::N, $r => self::N, $c => self::N, $v => self::N],
        ];
    }

    public static function grant(Role $role, Permission $permission): Grant
    {
        $row = self::table()[$permission->value] ?? [];

        return Grant::from($row[$role->value] ?? self::N);
    }

    /** @return list<Permission> */
    public static function permissions(): array
    {
        return Permission::cases();
    }
}
