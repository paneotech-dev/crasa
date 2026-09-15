<?php

/*
 * A member state contributing a national instrument. RP-03 and RP-06.
 *
 * This is how the register fills: from fourteen members, not from a
 * Secretariat of five typing in fourteen countries' legislation.
 */

return [
    'title' => 'Instruments you have contributed',
    'note' => 'National acts, regulations and guidelines your authority has filed to the regional register.',
    'add' => 'Contribute an instrument',
    'add_note' => 'English is required. French and Portuguese can follow.',
    'edit_title' => 'Edit the contribution',
    'save' => 'Save the contribution',
    'saved' => 'The contribution is saved. It stays with you until you send it.',
    'submit_action' => 'Send it to the Secretariat',
    'submitted' => 'Sent. The Secretariat will validate it or send it back with comments.',
    'locked' => 'This contribution is with the Secretariat. It can be edited again if it comes back.',
    'with_secretariat' => 'The Secretariat has this. You will be told when it is validated or returned.',
    'empty_title' => 'Nothing contributed yet',
    'empty_body' => 'File your national acts, regulations and licence frameworks here. The register is built from what the fourteen members contribute.',

    'state_heading' => 'Where this stands',
    'state_draft' => 'Draft',
    'state_submitted' => 'With the Secretariat',
    'state_validated' => 'In the register',
    'state_returned' => 'Returned to you',
    'returned_heading' => 'The Secretariat sent this back',
    'reviewed_on' => 'Reviewed :date',

    'col_instrument' => 'Instrument',
    'col_sector' => 'Sector',
    'col_state' => 'Where it stands',
    'col_updated' => 'Last changed',
    'col_country' => 'Member state',
    'col_submitted' => 'Sent',

    'what_it_is' => 'What it is',
    'field_reference' => 'Official citation',
    'reference_hint' => 'How the instrument is cited in your jurisdiction, exactly as it is written there.',
    'field_type' => 'Type',
    'field_sector' => 'Sector',
    'field_status' => 'Standing in law',
    'status_hint' => 'Whether it is in force, has been amended or has been repealed. This is separate from whether the Secretariat has validated your contribution.',
    'field_enacted' => 'Enacted',
    'field_effective' => 'In force from',
    'field_title' => 'Title',
    'field_summary' => 'Summary',
    'the_document' => 'The document',
    'field_file' => 'The instrument as a PDF',
    'file_hint' => 'Up to 25 MB. It is served through the platform and never by a filesystem link.',
    'related' => 'Related instruments',
    'field_supersedes' => 'This one replaces',
    'supersedes_none' => 'It replaces nothing',
    'supersedes_hint' => 'Only your own member state\'s instruments already in the register are offered.',

    'transition_invalid' => 'A contribution cannot go from :from to :to.',
    'return_needs_reason' => 'Say what has to change. A contribution sent back with no reason makes the member state guess.',
    'version_created' => 'Contribution created.',
    'version_edited' => 'Edited by the contributor.',
    'version_submitted' => 'Sent to the Secretariat from :from.',
    'version_validated' => 'Validated by the Secretariat.',
    'version_returned' => 'Returned to the contributor.',

    'notify_submitted' => 'Your contribution :title has been sent.',
    'notify_validated' => ':title is now in the regional register.',
    'notify_returned' => ':title has been sent back with comments.',
    'notify_register' => ':title from :country is now in the regional register.',

    'review_title' => 'Contributions from the member states',
    'review_note' => 'National instruments filed by the fourteen, waiting to be validated.',
    'awaiting' => 'Waiting for you',
    'recent_decisions' => 'Recently decided',
    'review_empty_title' => 'Nothing waiting',
    'review_empty_body' => 'Instruments contributed by the member states arrive here when they are sent.',
    'validate_action' => 'Validate and publish to the register',
    'return_action' => 'Send it back',
    'validated_notice' => 'Validated. It is in the register and the sector has been told.',
    'returned_notice' => 'Sent back with your comments.',
    'review_note_label' => 'What has to change',
    'review_note_hint' => 'The contributor sees this. Say what is wrong and what would fix it.',
    'open_contribution' => 'Open the contribution',
    'contributed_by' => 'Contributed by',
    'history' => 'History',
];
