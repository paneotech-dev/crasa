<?php

/*
 * Onboarding. Plain language, no jargon, and every sentence about this
 * platform rather than about content management in general.
 */

return [
    'title' => 'Help',
    'note' => 'What each part of the back office does, and the rules the platform holds to.',
    'welcome_title' => 'Welcome, :name',
    'welcome_body' => 'This is where the CRASA website is edited. Six sections, each doing one thing. Nothing here needs a developer and nothing here needs a deployment.',
    'welcome_foot' => 'This panel goes away when you dismiss it. Everything in it stays at',
    'open_help' => 'the help page',
    'dismiss' => 'Dismiss',
    'restore_hints' => 'Show the introduction again',
    'sections_title' => 'The six sections',
    'blocks_title' => 'What each block does',
    'blocks_note' => 'A page is built from blocks. These are the twelve you can add.',
    'rules_title' => 'Rules the platform holds to',
    'step_content_title' => 'Content',
    'step_content_body' => 'Every page on the public site. A page is a list of blocks, and you add, reorder, hide and edit them here. Nothing goes live until you publish it, and you can preview it first.',
    'step_translations_title' => 'Translations',
    'step_translations_body' => 'English, French and Portuguese side by side, with a coverage figure for each record. A record may publish in English alone; requiring all three would stop everything.',
    'step_media_title' => 'Media',
    'step_media_body' => 'Every image the site uses. Upload once and the served sizes are generated for you. An image needs alt text in a language before it appears on that language pages.',
    'step_data_title' => 'Regulatory data',
    'step_data_body' => 'Member states, authorities, committees, indicators, instruments and publications. The reference tables can be corrected but never deleted, because everything else points at them.',
    'step_users_title' => 'Users',
    'step_users_body' => 'Who holds an account and what they may do. The permission matrix at the foot of that page is the whole answer, in one table.',
    'step_audit_title' => 'Audit log',
    'step_audit_body' => 'Every change, who made it and when. It is append only: nothing here can be edited or deleted, including by an administrator.',
    'rule_languages_title' => 'A record may exist in English alone',
    'rule_languages_body' => 'When a translation is missing the English is shown in its place, marked as English so a screen reader switches voice, with a notice above it. It is never left blank, never hidden, and never machine translated on the fly.',
    'rule_publishing_title' => 'Publishing is a separate act from editing',
    'rule_publishing_body' => 'A page moves draft, review, published. Adding a block to a live page does not put it on the site: a new block arrives hidden, so nothing appears in front of fourteen regulators before you have filled it in.',
    'rule_alt_text_title' => 'An image without alt text does not appear',
    'rule_alt_text_body' => 'Alt text is per language. An image with English alt text and no Portuguese does not render on the Portuguese pages at all, rather than rendering with an empty alt attribute or an English sentence in the middle of a Portuguese page.',
    'rule_deleting_title' => 'Deleting and taking down are different',
    'rule_deleting_body' => 'Content soft deletes and can be restored, with its translations. Reference data cannot be deleted at all. An image in use on a page cannot be deleted until it is removed from that page.',
    'rule_audit_title' => 'Every change is recorded',
    'rule_audit_body' => 'Content changes, role changes, sign-ins and document downloads all write a row. The log cannot be edited or deleted by anyone, which is what makes it worth reading.',
    'rule_placeholders_title' => 'Missing content is marked, not invented',
    'rule_placeholders_body' => 'Where CRASA still has to supply copy, the block is flagged and says so on the page. Run php artisan content:placeholders for the full list of what is outstanding and what each one needs.',
];
