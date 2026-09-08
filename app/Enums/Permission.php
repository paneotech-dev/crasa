<?php

namespace App\Enums;

/**
 * Every permission the platform grants, transcribed from the matrix in
 * .design/screens/13-users-roles-audit.dc.html.
 *
 * The matrix is the contracted statement of who may do what. Keeping it as
 * an enum with a single grant table means a permission question is answered
 * in one place rather than re-decided per controller.
 */
enum Permission: string
{
    // Content
    case ViewPublishedContent = 'content.view_published';
    case ManageContent = 'content.manage';
    case EditTranslations = 'content.translate';
    case PublishContent = 'content.publish';
    case EditHomepage = 'content.edit_homepage';
    case DeleteContent = 'content.delete';

    // Regulatory data
    case ViewPublishedIndicators = 'data.view_published';
    case SubmitCountryReturn = 'data.submit_return';
    case ValidateSubmission = 'data.validate_submission';
    case RestoreVersion = 'data.restore_version';
    case ExportReports = 'data.export_reports';

    // Extranet
    case ReachCommitteeWorkspace = 'extranet.committee_workspace';
    case OpenMemberDocuments = 'documents.open_member';
    case OpenConfidentialDocuments = 'documents.open_confidential';
    case OpenSecretariatDocuments = 'documents.open_secretariat';
    case UploadToCountryFolder = 'documents.upload_country_folder';
    case ManageMeetings = 'meetings.manage';

    // Users and security
    case ManageUsers = 'users.manage';
    case AssignRoles = 'users.assign_roles';
    case ReadAuditLog = 'audit.read';
    case ManageSettings = 'settings.manage';

    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'content.') => 'Content',
            str_starts_with($this->value, 'data.') => 'Regulatory data',
            str_starts_with($this->value, 'users.'),
            str_starts_with($this->value, 'audit.'),
            str_starts_with($this->value, 'settings.') => 'Users and security',
            default => 'Extranet',
        };
    }

    /**
     * Abilities an administrator loses until two-factor enrolment is
     * confirmed. Screen 13: a non-enrolled administrator "keeps read access
     * but cannot publish, validate a submission or change a role".
     *
     * @return list<self>
     */
    public static function withheldUntilTwoFactorConfirmed(): array
    {
        return [
            self::ManageContent,
            self::PublishContent,
            self::DeleteContent,
            self::EditHomepage,
            self::EditTranslations,
            self::ValidateSubmission,
            self::RestoreVersion,
            self::ManageUsers,
            self::AssignRoles,
            self::ManageSettings,
            self::UploadToCountryFolder,
            self::ManageMeetings,
        ];
    }
}
