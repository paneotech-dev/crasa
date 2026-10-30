<?php

// Jour 3, point 1e : ajouts à l'inscription des délégués.
return [
    // Formulaire
    'language_legend' => 'Langue',
    'preferred_language' => 'Langue préférée',
    'interpretation' => 'Interprétation',
    'interpretation_hint' => 'Choisissez une langue si vous avez besoin de l\'interprétation des travaux. Le Secrétariat confirme ce qui peut être assuré.',
    'interp_none' => 'Pas d\'interprétation',
    'interp_into_en' => 'Vers l\'anglais',
    'interp_into_fr' => 'Vers le français',
    'interp_into_pt' => 'Vers le portugais',
    'lang_en' => 'Anglais',
    'lang_fr' => 'Français',
    'lang_pt' => 'Portugais',

    // Liste de l'administration
    'all_countries' => 'Tous les pays',
    'language_col' => 'Langue',
    'bulk_label' => 'Inscriptions sélectionnées',
    'select' => 'Sélectionner',
    'select_all' => 'Tout sélectionner',
    'select_one' => 'Sélectionner :name',
    'confirm_selected' => 'Confirmer la sélection',
    'decline_selected' => 'Refuser la sélection',
    'bulk_none_selected' => 'Aucune inscription n\'est sélectionnée. Cochez au moins une ligne, puis confirmez ou refusez.',
    'bulk_done_confirmed' => '{0} Aucune inscription modifiée : celles sélectionnées étaient déjà confirmées.|{1} 1 inscription confirmée.|[2,*] :count inscriptions confirmées.',
    'bulk_done_declined' => '{0} Aucune inscription modifiée : celles sélectionnées étaient déjà refusées.|{1} 1 inscription refusée.|[2,*] :count inscriptions refusées.',

    // Paramètres
    'settings_group_registrations' => 'Inscriptions des délégués',
    'settings_group_registrations_note' => 'Où va la copie de chaque inscription.',
    'setting_registrations_mailbox' => 'Boîte des inscriptions',
    'setting_registrations_mailbox_hint' => 'Reçoit une copie de chaque inscription de délégué. Si le champ est vide, la copie va à l\'adresse électronique de contact.',

    // Copie pour la boîte
    'copy_subject' => 'Inscription : :name pour :meeting',
    'copy_intro' => 'Un délégué s\'est inscrit sur le site de la CRASA. Voici la copie pour le Secrétariat.',
    'copy_where' => 'Confirmez-la ou refusez-la dans la liste des inscriptions des délégués :',

    // Statut sur la page publique
    'postponed_new_date' => 'Reportée, nouvelle date : :date',

    // Avis aux délégués inscrits
    'change_subject_cancelled' => 'Annulée : :meeting',
    'change_subject_postponed' => 'Reportée : :meeting',
    'change_subject_new_date' => 'Nouvelle date : :meeting',
    'change_greeting' => 'Bonjour :name,',
    'change_body_cancelled' => 'La réunion à laquelle vous êtes inscrit a été annulée.',
    'change_body_postponed' => 'La réunion à laquelle vous êtes inscrit a été reportée. Les nouvelles dates seront publiées sur la page de l\'événement.',
    'change_body_new_date' => 'La réunion à laquelle vous êtes inscrit a une nouvelle date : :date. Votre inscription reste valable pour cette date.',
    'change_page' => 'La page de l\'événement :',
];
