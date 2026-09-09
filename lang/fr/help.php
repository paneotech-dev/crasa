<?php

/*
 * Onboarding. Plain language, no jargon, and every sentence about this
 * platform rather than about content management in general.
 */

return [
    'title' => 'Aide',
    'note' => 'Ce que fait chaque partie du back-office, et les règles que la plateforme applique.',
    'welcome_title' => 'Bienvenue, :name',
    'welcome_body' => 'C\'est ici que le site de la CRASA se modifie. Six sections, chacune avec un rôle. Rien ici ne demande un développeur ni un déploiement.',
    'welcome_foot' => 'Ce panneau disparaît quand vous le fermez. Tout son contenu reste sur',
    'open_help' => 'la page d\'aide',
    'dismiss' => 'Fermer',
    'restore_hints' => 'Réafficher l\'introduction',
    'sections_title' => 'Les six sections',
    'blocks_title' => 'Le rôle de chaque bloc',
    'blocks_note' => 'Une page est construite avec des blocs. Voici les douze que vous pouvez ajouter.',
    'rules_title' => 'Les règles de la plateforme',
    'step_content_title' => 'Contenu',
    'step_content_body' => 'Chaque page du site public. Une page est une liste de blocs : vous les ajoutez, les réordonnez, les masquez et les modifiez ici. Rien n\'est en ligne avant publication, et vous pouvez prévisualiser.',
    'step_translations_title' => 'Traductions',
    'step_translations_body' => 'Anglais, français et portugais côte à côte, avec un taux de couverture par enregistrement. Un enregistrement peut être publié en anglais seul ; exiger les trois arrêterait tout.',
    'step_media_title' => 'Médias',
    'step_media_body' => 'Toutes les images du site. Déposez une fois et les tailles servies sont générées. Une image a besoin d\'un texte alternatif dans une langue pour apparaître sur les pages de cette langue.',
    'step_data_title' => 'Données réglementaires',
    'step_data_body' => 'États membres, autorités, comités, indicateurs, instruments et publications. Les tables de référence peuvent être corrigées mais jamais supprimées : tout le reste y pointe.',
    'step_users_title' => 'Utilisateurs',
    'step_users_body' => 'Qui détient un compte et ce qu\'il peut faire. La matrice des permissions au bas de cette page donne toute la réponse, en un tableau.',
    'step_audit_title' => 'Journal d\'audit',
    'step_audit_body' => 'Chaque modification, son auteur et sa date. En ajout seul : rien ne peut y être modifié ni supprimé, pas même par un administrateur.',
    'rule_languages_title' => 'Un enregistrement peut n\'exister qu\'en anglais',
    'rule_languages_body' => 'Quand une traduction manque, l\'anglais est affiché à sa place, marqué comme anglais pour qu\'un lecteur d\'écran change de voix, avec une mention au-dessus. Jamais vide, jamais masqué, jamais traduit automatiquement à la volée.',
    'rule_publishing_title' => 'Publier et modifier sont deux actes distincts',
    'rule_publishing_body' => 'Une page passe par brouillon, relecture, publiée. Ajouter un bloc à une page en ligne ne le met pas sur le site : un nouveau bloc arrive masqué, pour que rien n\'apparaisse devant quatorze régulateurs avant que vous l\'ayez rempli.',
    'rule_alt_text_title' => 'Une image sans texte alternatif ne s\'affiche pas',
    'rule_alt_text_body' => 'Le texte alternatif est par langue. Une image avec un texte anglais et sans portugais ne s\'affiche pas du tout sur les pages portugaises, plutôt que de s\'afficher avec un attribut alt vide ou une phrase anglaise au milieu d\'une page portugaise.',
    'rule_deleting_title' => 'Supprimer et retirer sont deux choses différentes',
    'rule_deleting_body' => 'Le contenu est supprimé de façon réversible et peut être restauré avec ses traductions. Les données de référence ne peuvent pas être supprimées. Une image utilisée sur une page ne peut pas être supprimée tant qu\'elle y figure.',
    'rule_audit_title' => 'Chaque modification est enregistrée',
    'rule_audit_body' => 'Modifications de contenu, changements de rôle, connexions et téléchargements écrivent tous une ligne. Le journal ne peut être ni modifié ni supprimé, et c\'est ce qui le rend digne d\'être lu.',
    'rule_placeholders_title' => 'Le contenu manquant est signalé, pas inventé',
    'rule_placeholders_body' => 'Là où la CRASA doit encore fournir un texte, le bloc est signalé et le dit sur la page. Lancez php artisan content:placeholders pour la liste complète de ce qui manque et de ce qu\'il faut.',
];
