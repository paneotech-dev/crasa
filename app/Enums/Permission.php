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
    // Day 2: moderated publishing. Reading the back office lists without
    // editing them, approving someone else's submission, and publishing
    // directly where a type's rule asks for an approval.
    case ReadContent = 'content.read';
    // Evening pass: "approve" is held per content type. ApproveContent is
    // no longer granted on its own: it answers "may this account approve
    // anything at all" (the inbox, the sidebar item, the back-office door)
    // and is true when any of the five type permissions below is held.
    case ApproveContent = 'content.approve';
    case ApprovePublications = 'content.approve_publications';
    case ApproveNews = 'content.approve_news';
    case ApproveEvents = 'content.approve_events';
    case ApprovePages = 'content.approve_pages';
    case ApproveSocial = 'content.approve_social';
    case PublishWithoutApproval = 'content.publish_without_approval';

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

    /**
     * The approve permission for one moderated content type, by the key
     * App\Support\Approvals::TYPES uses.
     */
    public static function approveFor(string $type): ?self
    {
        return match ($type) {
            'publications' => self::ApprovePublications,
            'news' => self::ApproveNews,
            'events' => self::ApproveEvents,
            'pages' => self::ApprovePages,
            'social' => self::ApproveSocial,
            default => null,
        };
    }

    /** @return list<self> */
    public static function approveTypes(): array
    {
        return [self::ApprovePublications, self::ApproveNews, self::ApproveEvents, self::ApprovePages, self::ApproveSocial];
    }

    /**
     * What an administrator reads on screen 13. The code stays the stored
     * value; it used to be the only thing shown, so the matrix read
     * "users.manage", "audit.read" in every language.
     */
    public function label(): string
    {
        return __('permissions.'.str_replace('.', '_', $this->value));
    }

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
            self::ApproveContent,
            self::ApprovePublications,
            self::ApproveNews,
            self::ApproveEvents,
            self::ApprovePages,
            self::ApproveSocial,
            self::PublishWithoutApproval,
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
