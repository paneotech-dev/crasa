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
    'subject_page' => 'a page',
    'subject_pageblock' => 'a block',
    'subject_publication' => 'a publication',
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
];
