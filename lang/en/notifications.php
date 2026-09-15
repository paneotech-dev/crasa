<?php

/*
 * The notification centre, RP-05 and extranet item 6.
 *
 * Every trigger below is about regulatory content. Nothing here is about a
 * journey, a room or a transfer: event and travel management is reserved
 * commercial scope and has no vocabulary in this file.
 */

return [
    'title' => 'Notifications',
    'note' => 'What the platform has told you, and what you want to be told about.',
    'recent' => 'Recent',
    'unread' => 'Unread',
    'unread_count' => '{0}All read|{1}One unread|[2,*]:count unread',
    'mark_all_read' => 'Mark all read',
    'all_read' => 'Everything is marked read.',
    'untitled' => 'Notification',
    'open' => 'Open it',
    'empty_title' => 'Nothing yet',
    'empty_body' => 'Decisions on your returns, documents filed into your committees and notices from the Secretariat appear here.',

    'preferences' => 'What you hear about',
    'tell_me_about' => 'Tell me about',
    'preferences_saved' => 'Your notification preferences are saved.',
    'save_preferences' => 'Save preferences',
    'by_email' => 'By email',
    'email_note' => 'A decision on your own return is emailed as it happens. Everything else waits for the digest, because one email per event is a channel people stop opening.',
    'digest_label' => 'Send the digest',
    'digest_daily' => 'Once a day',
    'digest_weekly' => 'Once a week',
    'digest_never' => 'Never, keep it in the platform',
    'digest_subject' => 'CRASA platform: :count items since your last digest',
    'digest_intro' => 'What has happened on the platform since your last digest.',
    'digest_action' => 'Open the notification centre',

    'trigger_sector_instrument' => 'A new instrument or guideline in my sector',
    'trigger_sector_instrument_note' => 'Published instruments and guidelines in the sectors your authority regulates.',
    'trigger_submission_decision' => 'My return is validated or returned',
    'trigger_submission_decision_note' => 'The Secretariat has accepted your data return or sent it back with comments. This one is emailed as it happens.',
    'trigger_committee_document' => 'A document added to my committee',
    'trigger_committee_document_note' => 'Papers filed into a committee you hold a seat on, at the clearance your seat carries.',
    'trigger_meeting_changed' => 'A meeting scheduled or changed',
    'trigger_meeting_changed_note' => 'When a meeting of your committee is put in the calendar, moved or cancelled.',
    'trigger_thread_comment' => 'A comment on a thread I am in',
    'trigger_thread_comment_note' => 'Replies on a document thread you have written in.',
    'trigger_announcement' => 'An announcement from the Secretariat',
    'trigger_announcement_note' => 'Notices to the membership, sometimes narrowed to one sector or one committee.',
    'filed' => "':file' was filed into :committee.",
    'meeting_moved' => "':title' is now :date.",
];
