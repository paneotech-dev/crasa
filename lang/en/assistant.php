<?php

/*
 * A proposal, not a feature.
 *
 * There is no chat input anywhere on this platform and nothing here can be
 * typed into. This file is the text of a card and a panel that describe
 * what an assistant would do, so the Secretariat can find out whether
 * member states want one before anybody is asked to pay for one.
 *
 * CLAUDE.md reserves conversational features for a separate commercial
 * addendum and forbids building them here. Describing a proposal and
 * counting who wants it is not building it, and the boundary is held by
 * test: no input, no textarea, no third-party request.
 *
 * No vendor is named. No model is named. No date is given.
 */

return [
    'title' => 'Regulatory assistant',
    'label' => 'Proposed extension — not part of the current platform',
    'framing' => 'Ask what any member state has done on any topic, in your own language, and get the clause with its source.',

    'card_action' => 'See what it would do',
    'open' => 'Read the proposal',

    'capabilities' => 'What it would do',

    'compare_title' => 'Compare across member states',
    'compare_example' => 'How do Botswana and Zambia differ on spectrum sharing?',

    'gap_title' => 'Find the gap',
    'gap_example' => 'Which member states have not adopted the 2021 roaming guidelines?',

    'draft_title' => 'Draft from the model text',
    'draft_example' => 'Draft a quality-of-service condition consistent with the SADC model framework.',

    'language_title' => 'Answer in your language',
    'language_example' => 'Ask in French or Portuguese about an English-only guideline; the answer comes back in your language with the source cited.',

    'return_title' => 'Help with your data return',
    'return_example' => 'What counts as an active mobile-broadband subscription?',

    'how' => 'How it would work',
    'how_sources' => "Answers come only from the platform's own instruments and documents, never from outside sources.",
    'how_cites' => 'Every answer cites the document and clause it came from.',
    'how_clearance' => 'It sees only what your account is cleared to see.',

    'status' => 'Under consideration by the Secretariat. Whether and when it is built depends on member interest and a separate agreement.',

    'interest' => 'I would use this',
    'noted' => 'Noted, thank you.',
    'close' => 'Close',
];
