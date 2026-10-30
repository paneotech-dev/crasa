<?php

/*
 * The mega menu panels (S3, 29 October 2026).
 *
 * The header's items, their order and their labels come from the menu
 * records (Navigation::primary()). This file adds what a record cannot
 * hold: for the sections that open a panel, the section links with a line
 * each, the related links and which featured card the panel shows.
 *
 * Keyed by the last segment of the item's own address, so renaming a menu
 * item in the back office keeps its panel. An item whose address matches
 * nothing here is a plain link. Labels are keys in lang/{en,fr,pt}/chome.php.
 *
 * A link with 'page' => slug is kept only while that page is published.
 *
 * feature: publication (latest with a file), news (latest), event (next
 * public meeting) or comparator.
 */

return [
    'about' => [
        'links' => [
            ['label' => 'chome.m.about_overview', 'desc' => 'chome.m.about_overview_d', 'path' => 'about'],
            ['label' => 'chome.m.about_vmh', 'desc' => 'chome.m.about_vmh_d', 'path' => 'about#vision-mission-history'],
            ['label' => 'chome.m.about_governance', 'desc' => 'chome.m.about_governance_d', 'path' => 'about#governance'],
            ['label' => 'chome.m.about_exco', 'desc' => 'chome.m.about_exco_d', 'path' => 'about#executive-committee'],
            // Day 3: themes
            ['label' => 'd3_themes.menu', 'desc' => 'd3_themes.menu_d', 'path' => 'themes'],
            // A page record: listed only while it is published (HomeData).
            ['label' => 'd3_partner.menu', 'desc' => 'd3_partner.menu_d', 'path' => 'partner-with-us', 'page' => 'partner-with-us'],
        ],
        'related' => [
            ['label' => 'chome.m.members', 'path' => 'members'],
            ['label' => 'chome.m.committees', 'path' => 'committees'],
            ['label' => 'chome.m.secretariat', 'path' => 'about#secretariat'],
        ],
        'feature' => 'news',
    ],
    'publications' => [
        'links' => [
            ['label' => 'chome.m.pubs_annual', 'desc' => 'chome.m.pubs_annual_d', 'path' => 'publications?type[]=annual_report'],
            ['label' => 'chome.m.pubs_guidelines', 'desc' => 'chome.m.pubs_guidelines_d', 'path' => 'publications?type[]=guideline'],
            ['label' => 'chome.m.pubs_studies', 'desc' => 'chome.m.pubs_studies_d', 'path' => 'publications?type[]=study'],
            ['label' => 'chome.m.pubs_communiques', 'desc' => 'chome.m.pubs_communiques_d', 'path' => 'publications?type[]=communique'],
        ],
        'related' => [
            ['label' => 'chome.m.pubs_library', 'path' => 'publications'],
            ['label' => 'chome.m.committees', 'path' => 'committees'],
        ],
        'feature' => 'publication',
    ],
    'projects' => [
        'links' => [
            ['label' => 'chome.m.proj_all', 'desc' => 'chome.m.proj_all_d', 'path' => 'projects'],
            ['label' => 'chome.m.proj_current', 'desc' => 'chome.m.proj_current_d', 'path' => 'projects?state=current'],
            ['label' => 'chome.m.proj_past', 'desc' => 'chome.m.proj_past_d', 'path' => 'projects?state=past'],
        ],
        'related' => [
            ['label' => 'chome.m.committees', 'path' => 'committees'],
            ['label' => 'chome.m.publications', 'path' => 'publications'],
        ],
        'feature' => 'publication',
    ],
    'newsroom' => [
        'links' => [
            ['label' => 'chome.m.news_all', 'desc' => 'chome.m.news_all_d', 'path' => 'newsroom'],
            ['label' => 'chome.m.news_communiques', 'desc' => 'chome.m.news_communiques_d', 'path' => 'publications?type[]=communique'],
            ['label' => 'chome.m.news_social', 'desc' => 'chome.m.news_social_d', 'path' => 'newsroom#social'],
        ],
        'related' => [
            ['label' => 'chome.m.events', 'path' => 'events'],
            ['label' => 'chome.m.publications', 'path' => 'publications'],
        ],
        'feature' => 'news',
    ],
    'events' => [
        'links' => [
            ['label' => 'chome.m.events_upcoming', 'desc' => 'chome.m.events_upcoming_d', 'path' => 'events#upcoming'],
            ['label' => 'chome.m.events_past', 'desc' => 'chome.m.events_past_d', 'path' => 'events#past'],
            ['label' => 'chome.m.events_all', 'desc' => 'chome.m.events_all_d', 'path' => 'events'],
        ],
        'related' => [
            ['label' => 'chome.m.committees', 'path' => 'committees'],
            ['label' => 'chome.m.newsroom', 'path' => 'newsroom'],
        ],
        'feature' => 'event',
    ],
    'comparator' => [
        'links' => [
            ['label' => 'chome.m.cmp_open', 'desc' => 'chome.m.cmp_open_d', 'path' => 'comparator'],
            ['label' => 'chome.m.cmp_members', 'desc' => 'chome.m.cmp_members_d', 'path' => 'members'],
        ],
        'related' => [
            ['label' => 'chome.m.members', 'path' => 'members'],
            ['label' => 'chome.m.publications', 'path' => 'publications'],
        ],
        'feature' => 'comparator',
    ],
];
