<?php

/*
 * Day 3: back-office create, edit and delete for member states,
 * authorities, committees, indicators and instruments.
 */
return [
    // Refusing a delete while other records point at the one asked for.
    'delete_refused' => ':thing has :list; move or delete them first.',
    'and' => 'and',
    'this_countries' => 'This member state',
    'this_authorities' => 'This authority',
    'this_committees' => 'This committee',
    'this_indicators' => 'This indicator',
    'this_instruments' => 'This instrument',

    'ref_authorities' => ':count authority|:count authorities',
    'ref_instruments' => ':count instrument|:count instruments',
    'ref_indicator_values' => ':count reported value|:count reported values',
    'ref_submissions' => ':count data return|:count data returns',
    'ref_committee_seats' => ':count member state seat|:count member state seats',
    'ref_people_seats' => ':count committee member|:count committee members',
    'ref_users' => ':count user account|:count user accounts',
    'ref_folders' => ':count workspace folder|:count workspace folders',
    'ref_documents' => ':count document|:count documents',
    'ref_publications' => ':count publication|:count publications',
    'ref_delegate_registrations' => ':count delegate registration|:count delegate registrations',
    'ref_enquiries' => ':count enquiry|:count enquiries',
    'ref_chaired_committees' => ':count committee it chairs|:count committees it chairs',
    'ref_contacts' => ':count contact|:count contacts',
    'ref_proposed_changes' => ':count proposed change|:count proposed changes',
    'ref_meetings' => ':count meeting|:count meetings',
    'ref_announcements' => ':count announcement|:count announcements',
    'ref_projects' => ':count project it leads|:count projects it leads',
    'ref_submission_values' => ':count figure in a data return|:count figures in data returns',
    'ref_submission_comments' => ':count review comment|:count review comments',
    'ref_superseded_instruments' => ':count instrument it supersedes|:count instruments it supersedes',

    'reference_data_note' => 'Other records point at these. A row can be corrected here, and deleted only while nothing points at it: the delete names what still does.',

    // Confirmation stays its own action.
    'confirm_action' => 'Confirm the record',
    'confirmed_by' => 'Confirmed by :name on :date',
    'not_confirmed' => 'Not confirmed yet. Use Confirm the record to record who vouched for it.',


    // French and Portuguese beside an English column.
    'localised_title' => 'French and Portuguese',
    'localised_note' => 'Empty uses the platform\'s own translation where there is one, then English.',
    'localised_from_file' => 'Empty uses:',

    // People on a committee.
    'people_title' => 'Committee members',
    'people_count' => '{0}No members recorded|{1}One member|[2,*]:count members',
    'people_empty_title' => 'No members recorded yet',
    'people_empty_body' => 'Add each person who sits on this committee from the platform\'s user accounts, and name the chair.',
    'people_col_person' => 'Person',
    'people_role_for' => 'Role for :name',
    'people_remove_confirm' => 'Remove :name from :committee?',
    'people_add' => 'Add a member',
    'people_add_action' => 'Add the member',
    'people_chair_hint' => 'A committee has one chair. Naming a second moves the first to member.',
    'member_added' => ':name added as :role.',
    'member_changed' => ':name is now :role.',
    'member_removed' => ':name removed from the committee.',
    'role_member' => 'Member',
    'role_chair' => 'Chair',
    'role_observer' => 'Observer',
];
