<?php

/*
 * Plain-language lines for the audit trail. The action names are stable
 * strings; the verb lives here so a row reads the same whichever language
 * the Secretariat has the interface in.
 */

return [
    'action_created' => 'Created :subject',
    'action_updated' => 'Updated :subject',
    'action_deleted' => 'Deleted :subject',
    'action_login' => 'Signed in',
    'action_login_failed' => 'A sign-in attempt failed',
    'action_logout' => 'Signed out',
    'action_role_changed' => 'Changed a role',
    'action_document_downloaded' => 'Downloaded a document',
    'action_submission_transitioned' => 'Moved a submission to its next state',
    'action_two_factor_enrolled' => 'Enrolled in two-factor authentication',
    'action_two_factor_failed' => 'A two-factor code was rejected',
    'action_two_factor_disabled' => 'Turned off two-factor authentication',
    'action_recovery_code_used' => 'Used a recovery code',
    'action_password_changed' => 'Changed a password',
    'action_password_reset' => 'Reset a password',
    'action_suspended' => 'Suspended an account',
    'action_invitation_accepted' => "Accepted an invitation",
    'action_reactivated' => 'Reactivated an account',
    'action_registration_resent' => 'Resent a delegate their registration copy',
    'action_registrations_exported' => 'Exported delegate registrations',
    'action_audit_exported' => 'Exported the audit log',
    // Day 3: docs
    'action_document_added' => 'Added a file to a folder',
    'action_document_replaced' => 'Added a new version of a file',
    'action_document_renamed' => 'Renamed a file',
    'action_document_moved' => 'Moved a file',
    'action_document_binned' => 'Sent a file to the recycle bin',
    'action_document_restored' => 'Restored a file from the recycle bin',
    'action_document_validated' => 'Validated a file',
    'action_document_purged' => 'Removed a file from the recycle bin for good',
    'action_folder_created' => 'Created a folder',
    'action_folder_changed' => 'Changed a folder',
    'action_folder_deleted' => 'Deleted a folder',
    // Day 3: auditdeny
    'action_access_refused' => 'Access refused',
    // end Day 3: docs
    // Day 3: prodprep
    'action_production_prepared' => 'Prepared the platform for production',
    'action_invitation_resent' => 'Sent a new invitation',
    // end Day 3: prodprep
    'subject_page' => 'a page',
    'subject_pageblock' => 'a block',
    'subject_publication' => 'a publication',
    'subject_socialpost' => 'a social media post',
    'subject_newsitem' => 'a news item',
    'subject_instrument' => 'a regulatory instrument',
    'subject_instrumentversion' => 'a version of an instrument',
    'subject_document' => 'a document',
    'subject_user' => 'a user account',
    'subject_indicator' => 'an indicator',
    'subject_indicatorvalue' => 'an indicator value',
    'subject_committee' => 'a committee',
    'subject_authority' => 'an authority',
    'subject_country' => 'a member state',
    'subject_translation' => 'a translation',
    'subject_enquiry' => 'an enquiry',
    'subject_tag' => 'a tag',
    'subject_sector' => 'a sector',
    'subject_newscategory' => 'a news category',
    'subject_publicationtype' => 'a publication type',

    // Record types added since the first list (19 October 2026).
    'subject_announcement' => 'an announcement',
    'subject_authoritychange' => 'a proposed change to an authority',
    'subject_authoritycontact' => 'an authority contact',
    'subject_delegateregistration' => 'a delegate registration',
    'subject_folder' => 'a folder',
    'subject_mediaasset' => 'an image',
    'subject_meeting' => 'a meeting',
    'subject_menuitem' => 'a menu item',
    'subject_project' => 'a project',
    'subject_report' => 'a report',
    'subject_submission' => 'a data return',
    'subject_submissionfieldcomment' => 'a comment on a data return',
    'subject_submissionvalue' => 'a figure in a data return',
    'subject_featureinterest' => 'a note of interest in a feature',
    'subject_documentversion' => 'a version of a document',
];
