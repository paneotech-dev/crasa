<?php

/*
 * Day 3, notify: the bell list, the filters on the notifications page and
 * the help page per screen.
 */

return [
    'category_approval_request' => 'Approval and validation requests',
    'category_decision' => 'Returns and approvals',
    'category_validation' => 'Validation results',
    'category_document' => 'New documents',
    'category_meeting' => 'Meeting changes',
    'category_registration' => 'Registrations confirmed',
    'category_announcement' => 'Announcements',
    'category_other' => 'Other notices',

    'submission_draft' => 'The :period return for :country is a draft again.',
    'submission_submitted' => ':country filed its :period return for validation.',
    'submission_validated' => 'The :period return for :country was validated.',
    'submission_returned' => 'The :period return for :country was returned with comments.',
    'document_added' => "':file' was added to the folder :folder.",
    'meeting_changed' => "':title' has changed. It is now :date.",
    'meeting_cancelled' => "':title' was cancelled.",
    'registration_confirmed' => 'The registration of :name for :title is confirmed.',
    'registration_received_own' => 'Your registration for :title was received.',
    'registration_received' => 'A registration from :name for :title was received.',

    'bell_label' => '{0}Notifications, none unread|{1}Notifications, one unread|[2,*]Notifications, :count unread',
    'bell_heading' => 'Notifications',
    'bell_empty' => 'Nothing new. Approval requests, returns, validation results, new documents and meeting changes appear here.',
    'mark_read' => 'Mark as read',
    'mark_read_named' => 'Mark as read: :title',
    'marked_read' => 'Marked as read.',
    'view_all' => 'All notifications',

    'filter_type' => 'Type',
    'filter_all_types' => 'All types',
    'filter_state' => 'Show',
    'state_all' => 'Read and unread',
    'state_unread' => 'Unread only',
    'state_read' => 'Read only',
    'apply_filters' => 'Apply filters',
    'filtered_empty_title' => 'No notifications match',
    'filtered_empty_body' => 'Choose another type, or show read and unread, to see more.',
    'read' => 'Read',

    'help_icon' => 'Help for this screen',
    'help_title' => 'Help: :screen',
    'help_general_title' => 'Help',
    'help_purpose' => 'What this screen is for',
    'help_actions' => 'Main actions',
    'help_general_note' => 'This screen has no help of its own yet. This is the help for the section it belongs to.',
    'help_back' => 'Back to the screen',
    'help_guide' => 'Open the training guide',
    'help_guide_note' => 'The training guide walks through each role step by step. It opens in a new tab.',
    'help_new_tab' => '(opens in a new tab)',
    'help_section_back_office' => 'Back office',
    'help_section_member' => 'Member area',
    'help_introduction' => 'Read the back office introduction',
];
