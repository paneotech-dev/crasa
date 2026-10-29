<?php

/*
 * Per-screen help for document management, keyed by route name.
 */

return [
    'extranet.folder' => [
        'purpose' => 'The files in one folder, who may see them, and what has happened in the folder.',
        'actions' => ['Upload several files at once', 'Open a PDF in the browser or download a file', 'Open a file to rename, move, replace or delete it'],
    ],
    'extranet.document' => [
        'purpose' => 'One file: its versions with who added each and when, and its share link.',
        'actions' => ['Upload a new version', 'Copy the share link', 'Rename, move or send the file to the recycle bin'],
    ],
    'extranet.folders.create' => [
        'purpose' => 'Create a folder or subfolder, up to three levels deep.',
        'actions' => ['Name and describe the folder', 'Choose where it sits and its clearance', 'Set who may see, upload and validate'],
    ],
    'extranet.folders.edit' => [
        'purpose' => 'Change a folder: its name, description, place in the tree and access.',
        'actions' => ['Rename or describe the folder', 'Move it under another folder', 'Change who may see, upload and validate'],
    ],
    'extranet.documents.bin' => [
        'purpose' => 'Files deleted from folders you manage, kept for thirty days.',
        'actions' => ['Restore a file to its folder', 'See who deleted a file and when', 'See when a file is removed for good'],
    ],
    'admin.documents' => [
        'purpose' => 'Every member area folder, with who may see it and how many files it holds.',
        'actions' => ['Create a folder or subfolder', 'Edit a folder and its access', 'Delete an empty folder'],
    ],
    'admin.documents.create' => [
        'purpose' => 'Create a folder or subfolder, up to three levels deep.',
        'actions' => ['Name and describe the folder', 'Choose where it sits and its clearance', 'Set who may see, upload and validate'],
    ],
    'admin.documents.edit' => [
        'purpose' => 'Change a folder: its name, description, place in the tree and access.',
        'actions' => ['Rename or describe the folder', 'Move it under another folder', 'Change who may see, upload and validate'],
    ],
    'admin.documents.bin' => [
        'purpose' => 'Files deleted from the member area folders, kept for thirty days.',
        'actions' => ['Restore a file to its folder', 'See who deleted a file and when', 'See when a file is removed for good'],
    ],
];
