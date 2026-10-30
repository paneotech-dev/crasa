<?php

/*
 * Content > Taxonomies, and the photograph on the news form (Day 3, taxo).
 */
return [
    'nav' => 'Taxonomies',
    'title' => 'Taxonomies',
    'note' => 'The lists the newsroom and the library filter by. Changes show on the public site at once, in all three languages.',

    'kind_categories' => 'News categories',
    'kind_publication-types' => 'Publication types',
    'kind_sectors' => 'Sectors',
    'kind_tags' => 'Tags',

    'new_categories' => 'New news category',
    'new_publication-types' => 'New publication type',
    'new_sectors' => 'New sector',
    'new_tags' => 'New tag',

    'used_by_categories' => 'Each news item has one category. The newsroom filter and the badge on each item follow this list and its order.',
    'used_by_publication-types' => 'Each publication has one type. The library filter and the label on each publication follow this list and its order.',
    'used_by_sectors' => 'Telecommunications, broadcasting and postal. Committees, instruments, indicators, publications and news items point at a sector.',
    'used_by_tags' => 'News items carry any number of tags. The newsroom tag filter follows this list and its order.',

    'empty_title' => 'Nothing in this list yet',
    'empty_body' => 'Add the first entry with the button above.',

    'col_order' => 'Order',
    'col_name' => 'Name',
    'col_slug' => 'Slug',
    'col_badge' => 'Badge',
    'col_used' => 'Used by',

    'field_names' => 'Name',
    'field_name_in' => 'Name in :language',
    'field_slug' => 'Slug',
    'slug_help' => 'Lower case letters, numbers, hyphens and underscores. It appears in the addresses of filtered pages.',
    'slug_help_existing' => 'Changing it changes the addresses of filtered pages: links saved with the old slug stop filtering. The items using it follow the change.',
    'slug_locked' => 'A sector\'s slug names its icon and the addresses the home page links to, so it cannot be changed.',
    'field_order' => 'Order',
    'order_help' => 'Lower numbers come first in the public filter.',
    'field_colour' => 'Badge colour',
    'colour_help' => 'Pairs from the site palette. Each one keeps the text at 4.5:1 contrast or more.',
    'colour_navy' => 'Navy',
    'colour_green' => 'Green',
    'colour_amber' => 'Amber',
    'colour_red' => 'Red',
    'colour_purple' => 'Purple',
    'colour_grey' => 'Grey',
    'contrast' => 'contrast :ratio to 1',

    'create' => 'Create entry',
    'save' => 'Save entry',
    'created' => ':name created.',
    'saved' => ':name saved.',
    'deleted' => ':name deleted.',

    'delete_title' => 'Delete',
    'delete' => 'Delete entry',
    'delete_unused' => 'Nothing uses this entry. Deleting it removes it from the public filters.',
    'delete_confirm' => 'Delete :name? This cannot be undone.',
    'in_use' => '{1} :name is used by 1 item and cannot be deleted. Move that item to another entry first.|[2,*] :name is used by :count items and cannot be deleted. Move those items to another entry first.',

    'photo' => 'Photograph',
    'photo_choose' => 'Choose from the media library',
    'photo_none' => 'No photograph',
    'photo_upload' => 'Upload a photo',
    'photo_upload_help' => 'JPG, PNG or WebP, up to 10 MB. It is added to the media library. Alt text in English, French and Portuguese is required.',
    'photo_alt_in' => 'Alt text in :language',
    'focal' => 'Focal point',
    'focal_help' => 'The point the photograph is cropped around on the cards and the article page. Click the photograph, or focus the marker and use the arrow keys (Shift for larger steps). Leave both empty for the usual framing.',
    'focal_marker' => 'Focal point marker',
    'focal_x' => 'From the left (%)',
    'focal_y' => 'From the top (%)',
];
