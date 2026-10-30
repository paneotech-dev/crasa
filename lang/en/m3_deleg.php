<?php

// Day 3 item 1e: delegate registration additions.
return [
    // Form
    'language_legend' => 'Language',
    'preferred_language' => 'Preferred language',
    'interpretation' => 'Interpretation',
    'interpretation_hint' => 'Choose a language if you need the proceedings interpreted. The Secretariat confirms what can be provided.',
    'interp_none' => 'No interpretation needed',
    'interp_into_en' => 'Into English',
    'interp_into_fr' => 'Into French',
    'interp_into_pt' => 'Into Portuguese',
    'lang_en' => 'English',
    'lang_fr' => 'French',
    'lang_pt' => 'Portuguese',

    // Back office list
    'all_countries' => 'All countries',
    'language_col' => 'Language',
    'bulk_label' => 'Selected registrations',
    'select' => 'Select',
    'select_all' => 'Select all',
    'select_one' => 'Select :name',
    'confirm_selected' => 'Confirm selected',
    'decline_selected' => 'Decline selected',
    'bulk_none_selected' => 'No registration is selected. Tick at least one row, then confirm or decline.',
    'bulk_done_confirmed' => '{0} No registration changed: the selected ones were already confirmed.|{1} 1 registration confirmed.|[2,*] :count registrations confirmed.',
    'bulk_done_declined' => '{0} No registration changed: the selected ones were already declined.|{1} 1 registration declined.|[2,*] :count registrations declined.',

    // Settings
    'settings_group_registrations' => 'Delegate registrations',
    'settings_group_registrations_note' => 'Where the copy of each registration goes.',
    'setting_registrations_mailbox' => 'Registrations mailbox',
    'setting_registrations_mailbox_hint' => 'Receives a copy of every delegate registration. Left empty, the copy goes to the contact email address.',

    // Mailbox copy
    'copy_subject' => 'Registration: :name for :meeting',
    'copy_intro' => 'A delegate registered on the CRASA website. This is the copy for the Secretariat.',
    'copy_where' => 'Confirm or decline it on the delegate registrations list:',

    // Status on the public page
    'postponed_new_date' => 'Postponed, new date :date',

    // Change notice to registered delegates
    'change_subject_cancelled' => 'Cancelled: :meeting',
    'change_subject_postponed' => 'Postponed: :meeting',
    'change_subject_new_date' => 'New date: :meeting',
    'change_greeting' => 'Dear :name,',
    'change_body_cancelled' => 'The meeting you registered for has been cancelled.',
    'change_body_postponed' => 'The meeting you registered for has been postponed. The new dates will be published on the event page.',
    'change_body_new_date' => 'The meeting you registered for has a new date: :date. Your registration stands for the new date.',
    'change_page' => 'The event page:',
];
