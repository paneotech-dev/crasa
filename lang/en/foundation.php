<?php

return [
    'title' => 'Foundation layer',
    'heading' => 'The foundation layer is in place',
    'body' => 'The token layer, the base layout, the trilingual switcher and the English fallback rule are built and running in all three languages. No page content exists yet. The fourteen member states, the regulatory library and the comparator follow the build order in the data model.',
    'checklist_heading' => 'What this page proves',
    'check_tokens' => 'Colour, type, spacing, radius and elevation come from one token file. Nothing on this page carries a hard-coded value.',
    'check_chrome' => 'The header and footer are included from partials, not rebuilt per screen.',
    'check_switcher' => 'The switcher changes language without JavaScript and keeps you on the page you were reading.',
    'check_fallback' => 'A record with no text in the requested language is shown in English with the notice below, and the gap is logged.',
    'notice_absent' => 'You are reading English, the fallback language, so no notice appears here. Switch to French or Portuguese to see it.',
    'notice_heading' => 'The fallback notice, as it appears above a record',
];
