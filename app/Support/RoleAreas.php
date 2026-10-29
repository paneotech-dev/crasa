<?php

namespace App\Support;

use App\Enums\Permission;

/**
 * The roles screen's grid (F3): areas in rows; view, create and edit,
 * delete, approve and other in columns. Each cell holds the permissions
 * that answer that question for that area. A permission sits in one cell
 * only, so the grid is the whole matrix and nothing is set twice.
 */
final class RoleAreas
{
    public const COLUMNS = ['view', 'edit', 'delete', 'approve', 'other'];

    /** @return array<string, array<string, list<Permission>>> */
    public static function grid(): array
    {
        return [
            'public' => ['view' => [Permission::ViewPublishedContent, Permission::ViewPublishedIndicators]],
            'content' => [
                'view' => [Permission::ReadContent],
                'edit' => [Permission::ManageContent, Permission::EditTranslations, Permission::EditHomepage],
                'delete' => [Permission::DeleteContent],
                'approve' => [Permission::ApprovePublications, Permission::ApproveNews, Permission::ApproveEvents, Permission::ApprovePages, Permission::ApproveSocial, Permission::PublishContent, Permission::PublishWithoutApproval],
            ],
            'data' => [
                'edit' => [Permission::SubmitCountryReturn],
                'approve' => [Permission::ValidateSubmission],
                'other' => [Permission::RestoreVersion, Permission::ExportReports],
            ],
            'extranet' => [
                'view' => [Permission::ReachCommitteeWorkspace, Permission::OpenMemberDocuments, Permission::OpenConfidentialDocuments, Permission::OpenSecretariatDocuments],
                'edit' => [Permission::UploadToCountryFolder, Permission::ManageMeetings],
            ],
            'users' => [
                'edit' => [Permission::ManageUsers],
                'approve' => [Permission::AssignRoles],
            ],
            'audit' => ['view' => [Permission::ReadAuditLog]],
            'settings' => ['edit' => [Permission::ManageSettings]],
        ];
    }
}
