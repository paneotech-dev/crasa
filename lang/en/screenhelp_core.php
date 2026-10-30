<?php

/*
 * Help per screen for the back office and the member area, keyed by route
 * name. Each entry: a title, one sentence on what the screen is for, and its
 * three main actions. Read by App\Support\ScreenHelp with every other
 * screenhelp_*.php file. _back_office and _member are each section's general
 * help, shown for a screen that has no entry of its own.
 */

return [
    '_back_office' => [
        'title' => 'Back office',
        'purpose' => 'The back office is where the Secretariat publishes content, moderates it, validates the member states\' returns and manages accounts.',
        'actions' => [
            'Choose a section in the sidebar: what you see depends on your role.',
            'Use the search box in the top bar to find any record in the administration.',
            'Open the bell for approval requests, returns and validation results waiting for you.',
        ],
    ],
    '_member' => [
        'title' => 'Member area',
        'purpose' => 'The member area is where the fourteen member states and the committees share documents, file data returns and follow meetings.',
        'actions' => [
            'Choose a section in the sidebar: your committees, folders and returns.',
            'Use the search box in the top bar to search the member area.',
            'Open the bell for new documents, meeting changes and validation results.',
        ],
    ],

    /* Help itself ---------------------------------------------------------- */

    'screen-help' => [
        'title' => 'Help',
        'purpose' => 'This page explains the screen you came from: what it is for and its three main actions.',
        'actions' => [
            'Read what the screen is for and its main actions.',
            'Go back to the screen with Back to the screen.',
            'Open the training guide for your role for step-by-step instructions.',
        ],
    ],

    /* Account and notifications ------------------------------------------------ */

    'account' => [
        'title' => 'Your account',
        'purpose' => 'Your own details: name, language, password and how the platform contacts you.',
        'actions' => [
            'Change your interface language and display preferences.',
            'Change your password.',
            'Sign out of the platform.',
        ],
    ],
    'two-factor.enrol' => [
        'title' => 'Two-factor sign-in',
        'purpose' => 'Sets up a second step at sign-in with an authenticator app, which protects accounts that administer the platform.',
        'actions' => [
            'Scan the code with an authenticator app on your phone.',
            'Enter the six-digit code the app shows to confirm.',
            'Keep the recovery codes somewhere safe.',
        ],
    ],
    'my-dashboard' => [
        'title' => 'My dashboard',
        'purpose' => 'Your starting page: the latest published items and a way into the comparator.',
        'actions' => [
            'Open the latest publications, news and regulatory updates.',
            'Go to the comparator to compare indicators between member states.',
            'Open your account to change your language or password.',
        ],
    ],
    'notifications' => [
        'title' => 'Notifications',
        'purpose' => 'Everything the platform has told you: approval requests, returns, validation results, new documents, meeting changes and confirmed registrations.',
        'actions' => [
            'Filter the list by type, or show only unread notices.',
            'Open a notice to go to its item; it is marked as read on the way.',
            'Mark one notice, or all of them, as read.',
        ],
    ],

    /* Back office ------------------------------------------------------------- */

    'admin.dashboard' => [
        'title' => 'Back office dashboard',
        'purpose' => 'The overview of the work waiting for your role: approvals, returns to validate, recent activity and sample data still in the database.',
        'actions' => [
            'Open an item waiting for your decision from the dashboard blocks.',
            'Follow recent activity on the platform.',
            'Go to a section from the sidebar.',
        ],
    ],
    'admin.search' => [
        'title' => 'Search the administration',
        'purpose' => 'Finds pages, publications, news, events, users and other records across the back office.',
        'actions' => [
            'Type a word or a reference in the search box.',
            'Narrow the results by type.',
            'Open a result to edit it.',
        ],
    ],
    'admin.help' => [
        'title' => 'Back office introduction',
        'purpose' => 'A short introduction to the back office and where each section starts, which you can read again at any time.',
        'actions' => [
            'Read where each section starts.',
            'Restore the hints you dismissed on other screens.',
            'Open the training guide for your role.',
        ],
    ],
    'admin.approvals' => [
        'title' => 'Approvals',
        'purpose' => 'The items sent for approval that wait for a decision, limited to the content types you may approve.',
        'actions' => [
            'Open an item to read it and its changes.',
            'Approve it to publish, or return it to the author with a comment.',
            'Filter the list by content type.',
        ],
    ],
    'admin.approvals.show' => [
        'title' => 'Approval request',
        'purpose' => 'One item sent for approval: its content as submitted, what changed and the author\'s note.',
        'actions' => [
            'Read the preview and the list of changes.',
            'Approve the item to publish it.',
            'Return it to the author with a comment saying what to change.',
        ],
    ],
    'admin.approvals.rules' => [
        'title' => 'Approval rules',
        'purpose' => 'Sets, per content type, whether publishing needs an approval and who may skip it.',
        'actions' => [
            'Choose the rule for each content type.',
            'Save the rules.',
            'Check the roles that hold the approve permission on the roles screen.',
        ],
    ],
    'admin.audit' => [
        'title' => 'Audit log',
        'purpose' => 'The record of who changed what and when: every publication, approval, account change and data return transition.',
        'actions' => [
            'Filter the log by user, object, action or date.',
            'Read each change as field, value before and value after.',
            'Export the filtered log to CSV.',
        ],
    ],
    'admin.announcements' => [
        'title' => 'Announcements',
        'purpose' => 'Notices from the Secretariat to the membership, sometimes narrowed to one sector or one committee.',
        'actions' => [
            'Write a new announcement.',
            'Edit or publish a draft.',
            'Unpublish an announcement that no longer applies.',
        ],
    ],
    'admin.announcements.create' => [
        'title' => 'New announcement',
        'purpose' => 'Writes a notice for the membership in English, French and Portuguese.',
        'actions' => [
            'Write the title and text, at least in English.',
            'Choose the sector or committee it is for, if any.',
            'Save it as a draft, then publish it.',
        ],
    ],
    'admin.announcements.edit' => [
        'title' => 'Edit announcement',
        'purpose' => 'Changes an announcement and publishes it; members are notified when it is published.',
        'actions' => [
            'Change the text in each language.',
            'Publish the announcement, which notifies the members it is for.',
            'Unpublish it when it no longer applies.',
        ],
    ],
    'admin.authority-changes' => [
        'title' => 'Authority record changes',
        'purpose' => 'Changes to a regulator\'s record proposed by its member state, waiting for the Secretariat.',
        'actions' => [
            'Open a proposal to compare it with the current record.',
            'Approve it to update the public record.',
            'Decline it with a note to the member state.',
        ],
    ],
    'admin.authority-changes.show' => [
        'title' => 'Proposed record change',
        'purpose' => 'One member state\'s proposed change to its regulator\'s record, side by side with the current record.',
        'actions' => [
            'Compare each proposed field with the current value.',
            'Approve the change to apply it.',
            'Decline it with a note.',
        ],
    ],
    'admin.committees.seats' => [
        'title' => 'Committee seats',
        'purpose' => 'Records which member states hold seats on a committee, and in what role.',
        'actions' => [
            'Add a member state to the committee.',
            'Set its role, such as chair or member.',
            'Remove a seat that no longer applies.',
        ],
    ],
    'admin.confirmations' => [
        'title' => 'Member state confirmations',
        'purpose' => 'The fourteen member states\' records and whether each has been confirmed by its own regulator.',
        'actions' => [
            'Open a member state to review its record.',
            'See which records are still unverified.',
            'Record who confirmed a record.',
        ],
    ],
    'admin.confirmations.edit' => [
        'title' => 'Confirm a member state record',
        'purpose' => 'One member state\'s regulator record, editable in place, with a confirm action that records who vouched for it.',
        'actions' => [
            'Correct the name, acronym, website or mandate.',
            'Record the person who confirmed the details.',
            'Confirm the record.',
        ],
    ],
    'admin.contributions' => [
        'title' => 'Contributed instruments',
        'purpose' => 'National instruments sent in by member states for the harmonised register, waiting for the Secretariat.',
        'actions' => [
            'Open a contribution to review it.',
            'Validate it into the register.',
            'Return it to the member state with comments.',
        ],
    ],
    'admin.contributions.show' => [
        'title' => 'Review a contribution',
        'purpose' => 'One contributed instrument: its text, documents and history.',
        'actions' => [
            'Read the instrument and its attached documents.',
            'Validate it into the register.',
            'Return it with a comment saying what to change.',
        ],
    ],
    'admin.data' => [
        'title' => 'Content',
        'purpose' => 'The list of content types you may manage: publications, news, instruments, projects and the other records.',
        'actions' => [
            'Choose a content type to see its records.',
            'Start a new record of that type.',
            'See how many records each type holds.',
        ],
    ],
    'admin.resources' => [
        'title' => 'Records',
        'purpose' => 'The records of one content type, with their status and language coverage.',
        'actions' => [
            'Search and filter the records.',
            'Open a record to edit it.',
            'Add a new record.',
        ],
    ],
    'admin.resources.create' => [
        'title' => 'New record',
        'purpose' => 'Creates a record of this content type.',
        'actions' => [
            'Fill in the fields, at least in English.',
            'Save it as a draft.',
            'Send it for approval or publish it, as your role allows.',
        ],
    ],
    'admin.resources.edit' => [
        'title' => 'Edit record',
        'purpose' => 'Changes one record; every change is recorded with its author and time.',
        'actions' => [
            'Change the fields and save.',
            'Send the record for approval or publish it, as your role allows.',
            'Delete the record if it should not exist.',
        ],
    ],
    'admin.instruments.versions' => [
        'title' => 'Instrument versions',
        'purpose' => 'The version history of a regulatory instrument: who changed it and when.',
        'actions' => [
            'Read the list of versions.',
            'Compare a version with the one before it.',
            'Restore an earlier version where your role allows.',
        ],
    ],
    'admin.instruments.diff' => [
        'title' => 'Compare versions',
        'purpose' => 'Shows what changed between two versions of an instrument, field by field.',
        'actions' => [
            'Read the removed and added text.',
            'Choose other versions to compare.',
            'Go back to the version history.',
        ],
    ],
    'admin.media' => [
        'title' => 'Media library',
        'purpose' => 'The images and files used on the public site, with where each is used.',
        'actions' => [
            'Upload a new image or file.',
            'Open an item to edit its description and credit.',
            'See where an item is used before replacing it.',
        ],
    ],
    'admin.media.create' => [
        'title' => 'Upload media',
        'purpose' => 'Adds an image or file to the media library.',
        'actions' => [
            'Choose the file to upload.',
            'Write its description for screen readers and its credit.',
            'Save it to the library.',
        ],
    ],
    'admin.media.edit' => [
        'title' => 'Edit media',
        'purpose' => 'Changes one image or file\'s description, credit and crop.',
        'actions' => [
            'Change the description and credit.',
            'See the pages that use it.',
            'Replace or delete it.',
        ],
    ],
    'admin.meetings' => [
        'title' => 'Meetings',
        'purpose' => 'The meetings and events in the calendar, upcoming and past, with their registrations.',
        'actions' => [
            'Add a meeting.',
            'Open a meeting to change its date, place or status.',
            'Open the list of people registered for a meeting.',
        ],
    ],
    'admin.meetings.create' => [
        'title' => 'New meeting',
        'purpose' => 'Adds a meeting or event to the calendar.',
        'actions' => [
            'Enter the title, dates and place.',
            'Choose the committee and who may see it.',
            'Save it, or send it for approval.',
        ],
    ],
    'admin.meetings.edit' => [
        'title' => 'Edit meeting',
        'purpose' => 'Changes a meeting; committee members and registered people are notified when it moves or is cancelled.',
        'actions' => [
            'Change the dates, place or online link.',
            'Set the status to postponed or cancelled.',
            'Save the change.',
        ],
    ],
    'admin.meetings.delete' => [
        'title' => 'Delete meeting',
        'purpose' => 'Confirms that a meeting should be removed from the calendar, with its documents.',
        'actions' => [
            'Check that this is the meeting to remove.',
            'Consider setting it to cancelled instead, which keeps the record.',
            'Confirm the deletion.',
        ],
    ],
    'admin.delegates' => [
        'title' => 'Registrations',
        'purpose' => 'The people registered for one meeting, with the status of each registration.',
        'actions' => [
            'Sort and read the list of registrations.',
            'Confirm or decline a registration.',
            'Download the list as a spreadsheet.',
        ],
    ],
    'admin.menus' => [
        'title' => 'Menus',
        'purpose' => 'The public site\'s menus, with their order and labels in each language.',
        'actions' => [
            'Add an item to a menu.',
            'Change the order of the items.',
            'Change an item\'s label in English, French and Portuguese.',
        ],
    ],
    'admin.pages' => [
        'title' => 'Pages',
        'purpose' => 'The public site\'s pages, with their status and language coverage.',
        'actions' => [
            'Open a page to edit it.',
            'Add a new page.',
            'Filter the pages by status.',
        ],
    ],
    'admin.pages.create' => [
        'title' => 'New page',
        'purpose' => 'Creates a page on the public site.',
        'actions' => [
            'Enter the title and address.',
            'Choose where it sits in the site.',
            'Save it as a draft and add its blocks.',
        ],
    ],
    'admin.pages.edit' => [
        'title' => 'Edit page',
        'purpose' => 'Changes a page\'s text and blocks; an item in review opens read only.',
        'actions' => [
            'Change the text and the order of the blocks.',
            'Preview the page.',
            'Send it for approval or publish it, as your role allows.',
        ],
    ],
    'admin.pages.preview' => [
        'title' => 'Page preview',
        'purpose' => 'Shows the page as it will look on the public site, before it is published.',
        'actions' => [
            'Read the page as a visitor will see it.',
            'Check it at phone width.',
            'Go back to the editor to change it.',
        ],
    ],
    'admin.pages.blocks.create' => [
        'title' => 'Add a block',
        'purpose' => 'Adds a block, such as text, an image or a list, to a page.',
        'actions' => [
            'Choose the type of block.',
            'Fill in its content.',
            'Save it to the page.',
        ],
    ],
    'admin.pages.blocks.edit' => [
        'title' => 'Edit a block',
        'purpose' => 'Changes one block on a page.',
        'actions' => [
            'Change the block\'s content.',
            'Save the block.',
            'Remove the block from the page.',
        ],
    ],
    'admin.reports' => [
        'title' => 'Regional briefs',
        'purpose' => 'Briefs generated from the indicators, by member state or by indicator, stored so they can be found again.',
        'actions' => [
            'Generate a brief for a member state or an indicator.',
            'Open a stored brief.',
            'Print or download a brief as PDF.',
        ],
    ],
    'admin.reports.show' => [
        'title' => 'Brief',
        'purpose' => 'One stored brief, with the figures it was generated from.',
        'actions' => [
            'Read the brief.',
            'Download it as PDF.',
            'Go back to the list of briefs.',
        ],
    ],
    'admin.roles' => [
        'title' => 'Roles',
        'purpose' => 'The roles on the platform and what each may do.',
        'actions' => [
            'Read the permissions of each role.',
            'Open a role to change its permissions.',
            'Create a new role.',
        ],
    ],
    'admin.roles.create' => [
        'title' => 'New role',
        'purpose' => 'Creates a role with its own set of permissions.',
        'actions' => [
            'Name the role.',
            'Tick the permissions it holds.',
            'Save the role.',
        ],
    ],
    'admin.roles.edit' => [
        'title' => 'Edit role',
        'purpose' => 'Changes what a role may do; other administrators are notified.',
        'actions' => [
            'Tick or untick permissions.',
            'Save the change.',
            'Check who holds the role on the users screen.',
        ],
    ],
    'admin.samples' => [
        'title' => 'Sample data',
        'purpose' => 'What demonstration data is still in the database, table by table, before it is removed for go-live.',
        'actions' => [
            'Read how many sample rows each table holds.',
            'Check which records are still marked as sample.',
            'Ask the technical team to run the removal once it is safe.',
        ],
    ],
    'admin.settings' => [
        'title' => 'Settings',
        'purpose' => 'Platform-wide settings such as contact details, mail and social accounts.',
        'actions' => [
            'Change a setting.',
            'Check whether mail is switched on.',
            'Save the settings.',
        ],
    ],
    'admin.social' => [
        'title' => 'Social posts',
        'purpose' => 'The social media posts shown on the public site.',
        'actions' => [
            'Add a post.',
            'Open a post to edit it.',
            'Send a post for approval or publish it.',
        ],
    ],
    'admin.social.create' => [
        'title' => 'New social post',
        'purpose' => 'Adds a social media post to show on the public site.',
        'actions' => [
            'Paste the post\'s address.',
            'Write the caption.',
            'Save it, or send it for approval.',
        ],
    ],
    'admin.social.edit' => [
        'title' => 'Edit social post',
        'purpose' => 'Changes a social media post shown on the public site.',
        'actions' => [
            'Change the caption or address.',
            'Save the change.',
            'Delete the post.',
        ],
    ],
    'admin.submissions' => [
        'title' => 'Annual returns',
        'purpose' => 'The member states\' data returns and their state: draft, submitted, validated or returned.',
        'actions' => [
            'Filter the returns by member state, period or state.',
            'Open a submitted return to review it.',
            'See which member states have not yet filed.',
        ],
    ],
    'admin.submissions.show' => [
        'title' => 'Review a data return',
        'purpose' => 'One member state\'s return, indicator by indicator, with comments against fields.',
        'actions' => [
            'Read each figure and its source.',
            'Comment on a field that needs changing.',
            'Validate the return, or return it to the member state.',
        ],
    ],
    'admin.translations' => [
        'title' => 'Translations',
        'purpose' => 'Which content is missing its French or Portuguese text.',
        'actions' => [
            'Filter by content type or language.',
            'Open an item to translate it.',
            'Preview a translation before saving.',
        ],
    ],
    'admin.translations.edit' => [
        'title' => 'Translate',
        'purpose' => 'The English text beside the French or Portuguese, field by field.',
        'actions' => [
            'Write or correct the translation of each field.',
            'Preview the translated page.',
            'Save the translation.',
        ],
    ],
    'admin.translations.preview' => [
        'title' => 'Translation preview',
        'purpose' => 'Shows the translated item as a reader of that language will see it.',
        'actions' => [
            'Read the translated item.',
            'Check the layout with the longer text.',
            'Go back to the translation to correct it.',
        ],
    ],
    'admin.users' => [
        'title' => 'Users',
        'purpose' => 'The platform\'s accounts, their roles, member states and status.',
        'actions' => [
            'Invite a new user.',
            'Change a user\'s role or member state.',
            'Suspend or reactivate an account.',
        ],
    ],
    'admin.users.form' => [
        'title' => 'Invite a user',
        'purpose' => 'Creates an account and records the invitation for its holder.',
        'actions' => [
            'Enter the name and email address.',
            'Choose the role and member state.',
            'Send the invitation.',
        ],
    ],

    /* Member area ------------------------------------------------------------- */

    'extranet.dashboard' => [
        'title' => 'Member area dashboard',
        'purpose' => 'Your starting page in the member area: your committees, folders, returns and what has changed.',
        'actions' => [
            'Open a committee or a folder.',
            'See the state of your member state\'s data return.',
            'Read the latest announcements and notices.',
        ],
    ],
    'extranet.folders' => [
        'title' => 'Folders',
        'purpose' => 'The document folders you may open, by committee and by member state.',
        'actions' => [
            'Open a folder to see its documents.',
            'See each folder\'s clearance.',
            'Search for a document across your folders.',
        ],
    ],
    'extranet.folder' => [
        'title' => 'Folder',
        'purpose' => 'The documents in one folder, at the clearance your role and seat allow.',
        'actions' => [
            'Open or download a document.',
            'Upload a document, where your role allows.',
            'Go back to all folders.',
        ],
    ],
    'extranet.committee' => [
        'title' => 'Committee workspace',
        'purpose' => 'One committee\'s members, documents and meetings.',
        'actions' => [
            'Read the committee\'s latest documents.',
            'See its upcoming meetings.',
            'See which member states hold seats.',
        ],
    ],
    'extranet.calendar' => [
        'title' => 'Calendar',
        'purpose' => 'The meetings of your committees and the region, month by month.',
        'actions' => [
            'Move between months.',
            'Open a meeting for its details.',
            'Add the calendar to your own calendar application.',
        ],
    ],
    'extranet.search' => [
        'title' => 'Search the member area',
        'purpose' => 'Finds documents, folders and records in the member area that you may open.',
        'actions' => [
            'Type a word or a reference.',
            'Narrow the results by type.',
            'Open a result.',
        ],
    ],
    'announcements' => [
        'title' => 'Announcements',
        'purpose' => 'Notices from the Secretariat to the membership.',
        'actions' => [
            'Open an announcement to read it.',
            'See which sector or committee it is for.',
            'Go back to your dashboard.',
        ],
    ],
    'announcements.show' => [
        'title' => 'Announcement',
        'purpose' => 'One notice from the Secretariat.',
        'actions' => [
            'Read the announcement.',
            'Open any document it links to.',
            'Go back to all announcements.',
        ],
    ],
    'authority' => [
        'title' => 'Your authority',
        'purpose' => 'Your member state\'s regulator record as the public sees it, and a way to propose corrections.',
        'actions' => [
            'Check the record shown to the public.',
            'Propose a correction to a field.',
            'Send the proposal to the Secretariat.',
        ],
    ],
    'completeness' => [
        'title' => 'Data completeness',
        'purpose' => 'Which member states have reported which indicators, period by period.',
        'actions' => [
            'Read the matrix of member states and indicators.',
            'Choose a period.',
            'Open the table version of the figures.',
        ],
    ],
    'contributions' => [
        'title' => 'Your contributions',
        'purpose' => 'National instruments your member state has sent, or is preparing, for the harmonised register.',
        'actions' => [
            'Start a new contribution.',
            'Open a draft to continue it.',
            'See the Secretariat\'s decision on each one.',
        ],
    ],
    'contributions.create' => [
        'title' => 'New contribution',
        'purpose' => 'Describes a national instrument for the harmonised register.',
        'actions' => [
            'Enter the reference, type, sector and status.',
            'Write the title and summary.',
            'Save it as a draft.',
        ],
    ],
    'contributions.edit' => [
        'title' => 'Edit contribution',
        'purpose' => 'Changes a contribution and sends it to the Secretariat.',
        'actions' => [
            'Correct the details and attach the document.',
            'Save the draft.',
            'Submit it to the Secretariat for validation.',
        ],
    ],
    'directory' => [
        'title' => 'Members directory',
        'purpose' => 'Contacts at the fourteen regulators who have agreed to be listed, for members only.',
        'actions' => [
            'Search by name, authority or sector.',
            'Add a contact for your authority.',
            'Correct or remove a contact you are responsible for.',
        ],
    ],
    'indicators.reference' => [
        'title' => 'Indicator definitions',
        'purpose' => 'What each indicator in the data return means and how to report it.',
        'actions' => [
            'Read an indicator\'s definition and unit.',
            'Filter by category.',
            'Go to your data return.',
        ],
    ],
    'register' => [
        'title' => 'Harmonised register',
        'purpose' => 'The regulatory instruments of all fourteen member states, readable by every member.',
        'actions' => [
            'Search and filter by member state, sector or type.',
            'Open an instrument.',
            'Compare instruments between member states.',
        ],
    ],
    'register.show' => [
        'title' => 'Instrument',
        'purpose' => 'One instrument in the harmonised register, with its documents and related instruments.',
        'actions' => [
            'Read the instrument\'s details.',
            'Open its documents.',
            'Open a related instrument.',
        ],
    ],
    'submissions' => [
        'title' => 'Annual return',
        'purpose' => 'Your member state\'s annual returns, period by period, and their state.',
        'actions' => [
            'Submit a new return for a period.',
            'Open a draft or returned return to complete it.',
            'Export a return as CSV or JSON.',
        ],
    ],
    'submissions.edit' => [
        'title' => 'Annual return, period, member state',
        'purpose' => 'One annual return, indicator by indicator, with any comments from the Secretariat.',
        'actions' => [
            'Enter or correct each figure with its source.',
            'Read the Secretariat\'s comments on returned fields.',
            'Submit the return for validation.',
        ],
    ],
    'submissions.import' => [
        'title' => 'Import a data return',
        'purpose' => 'Fills a return from a spreadsheet, in two steps: inspect, then commit.',
        'actions' => [
            'Download the template and fill it in.',
            'Upload the file and read the inspection report.',
            'Commit the import, which writes everything or nothing.',
        ],
    ],
];
