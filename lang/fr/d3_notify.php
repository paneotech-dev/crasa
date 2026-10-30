<?php

/*
 * Day 3, notify: the bell list, the filters on the notifications page and
 * the help page per screen.
 */

return [
    'category_approval_request' => 'Demandes d\'approbation et de validation',
    'category_decision' => 'Renvois et approbations',
    'category_validation' => 'Résultats de validation',
    'category_document' => 'Nouveaux documents',
    'category_meeting' => 'Changements de réunion',
    'category_registration' => 'Inscriptions confirmées',
    'category_announcement' => 'Annonces',
    'category_other' => 'Autres avis',

    'submission_draft' => 'Le rapport :period de :country est de nouveau un brouillon.',
    'submission_submitted' => ':country a déposé son rapport :period pour validation.',
    'submission_validated' => 'Le rapport :period de :country a été validé.',
    'submission_returned' => 'Le rapport :period de :country a été renvoyé avec des commentaires.',
    'document_added' => "« :file » a été ajouté au dossier :folder.",
    'meeting_changed' => "« :title » a changé. La réunion a lieu le :date.",
    'meeting_cancelled' => "« :title » a été annulée.",
    'registration_confirmed' => 'L\'inscription de :name à :title est confirmée.',
    'registration_received_own' => 'Votre inscription à :title a été reçue.',
    'registration_received' => 'Une inscription de :name à :title a été reçue.',

    'bell_label' => '{0}Notifications, aucune non lue|{1}Notifications, une non lue|[2,*]Notifications, :count non lues',
    'bell_heading' => 'Notifications',
    'bell_empty' => 'Rien de nouveau. Les demandes d\'approbation, les renvois, les résultats de validation, les nouveaux documents et les changements de réunion apparaissent ici.',
    'mark_read' => 'Marquer comme lue',
    'mark_read_named' => 'Marquer comme lue : :title',
    'marked_read' => 'Notification marquée comme lue.',
    'view_all' => 'Toutes les notifications',

    'filter_type' => 'Type',
    'filter_all_types' => 'Tous les types',
    'filter_state' => 'Afficher',
    'state_all' => 'Lues et non lues',
    'state_unread' => 'Non lues seulement',
    'state_read' => 'Lues seulement',
    'apply_filters' => 'Appliquer les filtres',
    'filtered_empty_title' => 'Aucune notification ne correspond',
    'filtered_empty_body' => 'Choisissez un autre type, ou affichez les lues et non lues, pour en voir davantage.',
    'read' => 'Lue',

    'help_icon' => 'Aide pour cet écran',
    'help_title' => 'Aide : :screen',
    'help_general_title' => 'Aide',
    'help_purpose' => 'À quoi sert cet écran',
    'help_actions' => 'Actions principales',
    'help_general_note' => 'Cet écran n\'a pas encore d\'aide propre. Voici l\'aide de la section à laquelle il appartient.',
    'help_back' => 'Retour à l\'écran',
    'help_guide' => 'Ouvrir le guide de formation',
    'help_guide_note' => 'Le guide de formation présente chaque rôle étape par étape. Il s\'ouvre dans un nouvel onglet.',
    'help_new_tab' => '(s\'ouvre dans un nouvel onglet)',
    'help_section_back_office' => 'Administration',
    'help_section_member' => 'Espace membres',
    'help_introduction' => 'Lire la présentation de l\'administration',
];
