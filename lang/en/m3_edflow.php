<?php

/*
 * Day 3, editor flow (item 1a): the two save buttons, what happens next
 * after each, changes to a published item held for approval, and the
 * moderator's inbox notice.
 */

return [
    'save_draft' => 'Save draft',
    'save_send' => 'Save and send for approval',
    'note_label' => 'Note for the moderator (optional)',
    'note_help' => 'What changed and anything the moderator should check. Sent only with Save and send for approval.',
    'actions_help' => 'Save draft keeps your work without sending it. Save and send for approval saves it and asks a moderator to publish it.',

    'saved_draft' => 'Draft saved. It is not on the public site. When it is ready, choose Save and send for approval.',
    'sent' => 'Sent for approval. A moderator will approve it or return it with a comment, and you will receive a notification either way. It cannot be edited until then. Once approved it is on the public site.',
    'saved_revision' => 'Your changes are saved as a draft. The published version stays online. When they are ready, choose Save and send for approval.',
    'sent_revision' => 'Your changes are waiting for approval; the published version stays online until a moderator approves them.',

    'revision_draft' => 'You are editing a draft of changes to a published item. The public site shows the published version until a moderator approves your changes.',
    'revision_waiting' => 'Your changes are waiting for approval; the published version stays online until a moderator approves them.',
    'revision_returned' => 'A moderator returned your changes. The published version is still online. Edit them and send them again.',
    'revision_label' => 'Changes to the published version',

    'inbox_title' => 'Waiting for your approval',
    'inbox_body' => '{1} :count item is waiting for your decision.|[2,*] :count items are waiting for your decision.',
    'inbox_open' => 'Open the approval inbox',

    'event_public_help' => 'Events you add start as members only. A moderator puts them on the public site when approving them.',
    'event_public_kept' => 'This event is on the public site and stays public. Your changes go live when a moderator approves them.',
    'event_files_held' => 'The documents of a public event cannot be changed in a change waiting for approval. Save the other changes first, then ask a moderator to add or remove the documents.',
    'status_refused' => 'Editors do not set a status. Use Save draft or Save and send for approval.',
    'moderator_revision' => 'These are changes to a published item. The published version stays online until you approve them; approving puts the changes on the public site. The table compares them with the published version.',
    'page_status_help' => 'A moderator publishes this page after you send it for approval.',
];
