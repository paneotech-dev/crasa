<?php

/*
 * Stream G: the accessibility statement (G5). The three files carry the
 * same keys; AccessibilityStatementController renders them.
 */
return [
    'slug' => 'accessibility',
    'statement' => [
        'title' => 'Accessibility statement',
        'eyebrow' => 'Accessibility',
        'lead' => 'This platform is built so that everyone can read, search and use it: with a keyboard, a screen reader, a magnifier, a phone or a slow connection, in English, French and Portuguese.',
        'meta_description' => 'How the CRASA platform meets WCAG 2.1 Level AA, what is known not to meet it yet, and how to report a barrier.',
        'fig_standard' => 'WCAG 2.1 Level AA',
        'fig_standard_label' => 'Standard we build to',
        'fig_review' => '29 October 2026',
        'fig_review_label' => 'Last review',
        'toc' => 'On this page',
        'sections' => [
            'standard' => [
                'title' => 'The standard we follow',
                'body' => [
                    'This platform is built to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA, the level most public bodies adopt. It covers the public website, the member area and the back office, in English, French and Portuguese.',
                    'Level A is the minimum, without which some people cannot use a page at all. Level AA, our target, removes the most common barriers for people with low vision, colour blindness, motor, hearing and cognitive disabilities. Level AAA is the highest level and is not required of a whole site; we meet parts of it where we can.',
                ],
            ],
            'done' => [
                'title' => 'What we have done',
                'items' => [
                    'Every page opens with a "Skip to content" link, has one main heading, headings in order and a language set on the page.',
                    'Everything works with a keyboard: menus, the language switcher, the slider, tabs, filters, maps, the comparator, forms and dialogs. A visible focus ring shows where you are.',
                    'Text has a contrast of at least 4.5:1 with its background, and controls 3:1. The values are measured by a script, not judged by eye.',
                    'Images carry a text description, or are marked as decoration when they add nothing. Charts and maps come with a table or a list giving the same information.',
                    'Colour is never the only signal: status labels carry a word and an icon, and calendar entries a letter.',
                    'Forms have labels above the fields, required fields are marked in words, and errors are written next to the field and read out by screen readers.',
                    'Pages reflow to a 320 pixel wide screen without scrolling sideways, and stay usable at 200% zoom.',
                    'Moving content can be paused, and nothing moves if your device asks for reduced motion.',
                ],
            ],
            'options' => [
                'title' => 'Display options',
                'body' => [
                    'The Display options button at the top of every page lets you change text size (four steps), widen line spacing, use reading spacing (wider letters and words, no italics, which helps many readers with dyslexia), switch to high contrast, underline every link and pause animations.',
                    'Your choices are kept in this browser and applied on every page until you reset them. Nothing is sent to CRASA.',
                ],
            ],
            'tested' => [
                'title' => 'How we test',
                'items' => [
                    'Automated checks with axe-core on every public page and every type of back-office screen, in the three languages.',
                    'A full keyboard pass on each page type, at 200% zoom and at 320 pixels wide.',
                    'Screen reader checks with NVDA on Windows and VoiceOver on macOS and iPhone.',
                    'HTML validation of the page templates.',
                ],
            ],
            'limits' => [
                'title' => 'Known exceptions and limitations',
                'intro' => 'These parts do not yet fully meet the standard. Each one has a date it was found and a date for the next step.',
                'items' => [
                    [
                        'what' => 'Older PDF documents',
                        'detail' => 'Some guidelines and reports imported from the previous website are not tagged for screen readers. Ask us and we will send the content in an accessible format.',
                        'found' => '29 October 2026',
                        'next' => 'Most downloaded documents reviewed by 31 January 2027',
                    ],
                    [
                        'what' => 'French and Portuguese text',
                        'detail' => 'Part of the French and Portuguese text was translated by machine and is being reviewed by CRASA. The English version is one click away in the language switcher.',
                        'found' => '1 October 2026',
                        'next' => 'Review in progress, next check 31 January 2027',
                    ],
                    [
                        'what' => 'Embedded social media posts',
                        'detail' => 'Posts from X, Facebook and LinkedIn are drawn by those platforms, and their accessibility is theirs. Each post also has a plain link to open it.',
                        'found' => '29 October 2026',
                        'next' => 'Checked at each review',
                    ],
                    [
                        'what' => 'Older image descriptions',
                        'detail' => 'Some photographs imported from the previous website have short or generic descriptions. New images cannot be published without one.',
                        'found' => '29 October 2026',
                        'next' => 'Newsroom images reviewed by 31 January 2027',
                    ],
                    [
                        'what' => 'Two colours changed to meet the standard (1.4.11)',
                        'detail' => 'Two colours in the approved design did not reach the 3:1 contrast that success criterion 1.4.11 requires for the edge of a control. The delivered values differ from the design so that they meet it.',
                        'found' => '9 September 2026',
                        'next' => 'Resolved in the delivered values',
                    ],
                    [
                        'what' => 'Disabled text (1.4.3)',
                        'detail' => 'The colour of disabled text is below 4.5:1 on purpose: success criterion 1.4.3 exempts text that is part of an inactive control.',
                        'found' => '9 September 2026',
                        'next' => 'No change needed',
                    ],
                ],
                'col_what' => 'What',
                'col_found' => 'Found',
                'col_next' => 'Next step',
            ],
            'report' => [
                'title' => 'Report a barrier or ask for another format',
                'body' => [
                    'If something on this platform is hard or impossible for you to use, tell the Secretariat which page and what happened. If you need a document in another format, say which one.',
                ],
                'email' => 'Email',
                'phone' => 'Telephone',
                'form' => 'Use the contact form',
                'reply' => 'We reply in writing and tell you what will be fixed and when.',
            ],
            'review' => [
                'title' => 'Review of this statement',
                'body' => [
                    'This statement was prepared on 29 October 2026 after a full check of the platform, and was last reviewed on 29 October 2026. It is reviewed after every major change and at least once a year. The next review is planned for 31 January 2027.',
                ],
            ],
        ],
        'cta_title' => 'Something not working for you?',
        'cta_text' => 'Tell the Secretariat which page and what happened.',
        'cta_button' => 'Contact the Secretariat',
    ],
];
