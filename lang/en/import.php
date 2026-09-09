<?php

/*
 * Bulk import and export, RP-08 and BUILD_SPEC 6.2.
 *
 * Errors here name the row, the column and the reason. CLAUDE.md's own
 * example of a good error is exactly that shape.
 */

return [
    'title' => 'Bulk import',
    'note' => 'A spreadsheet in, checked before anything is written.',
    'step_template' => 'Download a template',
    'step_template_note' => 'Pre-filled with the indicator names, slugs and units.',
    'template_all' => 'All indicators',
    'template_hint' => 'Fill in the value and source columns. Leave a row empty where you have nothing to report: an empty row is not an error, it publishes as Not reported.',
    'step_upload' => 'Upload the file',
    'step_upload_note' => 'Nothing is written yet. The file is read and reported on first.',
    'file' => 'CSV file',
    'file_hint' => 'The template you downloaded, filled in. Up to 2 MB.',
    'check_action' => 'Check the file',
    'step_report' => 'What the file contains',
    'step_export' => 'Export this return',
    'step_export_note' => 'The figures as they stand, in CSV or JSON. RP-08.',
    'export_csv' => 'Export CSV',
    'export_json' => 'Export JSON',
    'col_indicator' => 'Indicator',
    'col_slug' => 'Reference',
    'col_unit' => 'Unit',
    'col_value' => 'Value',
    'col_source_note' => 'Source',
    'col_row' => 'Row',
    'col_column' => 'Column',
    'col_reason' => 'Reason',
    'errors_found' => '{1} One row cannot be imported.|[2,*] :count rows cannot be imported.',
    'errors_body' => 'Nothing has been written. Correct the file and upload it again: a half-imported dataset is worse than a rejected one, because nobody can tell which half is real.',
    'error_no_slug' => 'No indicator reference in this row.',
    'error_unknown_slug' => 'No indicator has the reference :slug. Use the template rather than typing a reference.',
    'error_not_a_number' => 'Must be a number in :unit. Found ":found".',
    'error_not_a_boolean' => 'Must be yes or no. Found ":found".',
    'error_no_source' => 'A figure needs a source. Say where it came from, so it can be defended when a member state queries it.',
    'ready' => '{1} One row is ready to import.|[2,*] :count rows are ready to import.',
    'commit_hint' => 'Every row is written or none is. Existing figures for the same indicators are replaced.',
    'commit_action' => 'Import these rows',
    'committed' => '{1} One figure imported.|[2,*] :count figures imported.',
    'has_errors' => 'This file has errors, so nothing was written. Correct it and upload it again.',
    'expired' => 'The checked file is no longer in this session. Upload it again.',
];
