<?php

/*
 * Day 3: back-office create, edit and delete for member states,
 * authorities, committees, indicators and instruments.
 */
return [
    'delete_refused' => ':thing a :list ; déplacez ou supprimez ces éléments d\'abord.',
    'and' => 'et',
    'this_countries' => 'Cet État membre',
    'this_authorities' => 'Cette autorité',
    'this_committees' => 'Ce comité',
    'this_indicators' => 'Cet indicateur',
    'this_instruments' => 'Cet instrument',

    'ref_authorities' => ':count autorité|:count autorités',
    'ref_instruments' => ':count instrument|:count instruments',
    'ref_indicator_values' => ':count valeur déclarée|:count valeurs déclarées',
    'ref_submissions' => ':count déclaration de données|:count déclarations de données',
    'ref_committee_seats' => ':count siège d\'État membre|:count sièges d\'États membres',
    'ref_people_seats' => ':count membre du comité|:count membres du comité',
    'ref_users' => ':count compte utilisateur|:count comptes utilisateurs',
    'ref_folders' => ':count dossier de l\'espace de travail|:count dossiers de l\'espace de travail',
    'ref_documents' => ':count document|:count documents',
    'ref_publications' => ':count publication|:count publications',
    'ref_delegate_registrations' => ':count inscription de délégué|:count inscriptions de délégués',
    'ref_enquiries' => ':count demande|:count demandes',
    'ref_chaired_committees' => ':count comité qu\'elle préside|:count comités qu\'elle préside',
    'ref_contacts' => ':count contact|:count contacts',
    'ref_proposed_changes' => ':count modification proposée|:count modifications proposées',
    'ref_meetings' => ':count réunion|:count réunions',
    'ref_announcements' => ':count annonce|:count annonces',
    'ref_projects' => ':count projet qu\'il dirige|:count projets qu\'il dirige',
    'ref_submission_values' => ':count chiffre dans une déclaration|:count chiffres dans des déclarations',
    'ref_submission_comments' => ':count commentaire de vérification|:count commentaires de vérification',
    'ref_superseded_instruments' => ':count instrument qu\'il remplace|:count instruments qu\'il remplace',

    'reference_data_note' => 'D\'autres enregistrements pointent vers ceux-ci. Une ligne peut être corrigée ici, et supprimée seulement si plus rien n\'y renvoie : la suppression indique ce qui y renvoie encore.',

    'confirm_action' => 'Confirmer la fiche',
    'confirmed_by' => 'Confirmée par :name le :date',
    'not_confirmed' => 'Pas encore confirmée. Utilisez Confirmer la fiche pour enregistrer qui s\'en porte garant.',


    'localised_title' => 'Français et portugais',
    'localised_note' => 'Vide, la traduction propre à la plateforme s\'applique si elle existe, sinon l\'anglais.',
    'localised_from_file' => 'Si vide :',

    'people_title' => 'Membres du comité',
    'people_count' => '{0}Aucun membre enregistré|{1}Un membre|[2,*]:count membres',
    'people_empty_title' => 'Aucun membre enregistré',
    'people_empty_body' => 'Ajoutez chaque personne qui siège à ce comité à partir des comptes utilisateurs de la plateforme, et désignez la présidence.',
    'people_col_person' => 'Personne',
    'people_role_for' => 'Rôle de :name',
    'people_remove_confirm' => 'Retirer :name du comité :committee ?',
    'people_add' => 'Ajouter un membre',
    'people_add_action' => 'Ajouter le membre',
    'people_chair_hint' => 'Un comité a une seule présidence. En désigner une deuxième fait passer la première au rang de membre.',
    'member_added' => ':name ajouté en tant que :role.',
    'member_changed' => ':name est désormais :role.',
    'member_removed' => ':name retiré du comité.',
    'role_member' => 'Membre',
    'role_chair' => 'Présidence',
    'role_observer' => 'Observateur',
];
