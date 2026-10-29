<?php

/*
 * Contenu > Taxonomies, et la photographie du formulaire d'actualité (jour 3, taxo).
 */
return [
    'nav' => 'Taxonomies',
    'title' => 'Taxonomies',
    'note' => 'Les listes qui servent de filtres à la salle de presse et à la bibliothèque. Les modifications apparaissent aussitôt sur le site public, dans les trois langues.',

    'kind_categories' => 'Catégories d\'actualités',
    'kind_publication-types' => 'Types de publication',
    'kind_sectors' => 'Secteurs',
    'kind_tags' => 'Étiquettes',

    'new_categories' => 'Nouvelle catégorie d\'actualités',
    'new_publication-types' => 'Nouveau type de publication',
    'new_sectors' => 'Nouveau secteur',
    'new_tags' => 'Nouvelle étiquette',

    'used_by_categories' => 'Chaque actualité a une catégorie. Le filtre de la salle de presse et le badge de chaque actualité suivent cette liste et son ordre.',
    'used_by_publication-types' => 'Chaque publication a un type. Le filtre de la bibliothèque et l\'étiquette de chaque publication suivent cette liste et son ordre.',
    'used_by_sectors' => 'Télécommunications, radiodiffusion et services postaux. Les comités, instruments, indicateurs, publications et actualités renvoient à un secteur.',
    'used_by_tags' => 'Une actualité peut porter plusieurs étiquettes. Le filtre par thème de la salle de presse suit cette liste et son ordre.',

    'empty_title' => 'Cette liste est encore vide',
    'empty_body' => 'Ajoutez la première entrée avec le bouton ci-dessus.',

    'col_order' => 'Ordre',
    'col_name' => 'Nom',
    'col_slug' => 'Identifiant',
    'col_badge' => 'Badge',
    'col_used' => 'Utilisé par',

    'field_names' => 'Nom',
    'field_name_in' => 'Nom en :language',
    'field_slug' => 'Identifiant',
    'slug_help' => 'Lettres minuscules, chiffres, traits d\'union et tirets bas. Il figure dans l\'adresse des pages filtrées.',
    'slug_help_existing' => 'Le modifier change l\'adresse des pages filtrées : les liens enregistrés avec l\'ancien identifiant ne filtrent plus. Les éléments qui l\'utilisent suivent la modification.',
    'slug_locked' => 'L\'identifiant d\'un secteur désigne son icône et les adresses vers lesquelles pointe la page d\'accueil ; il ne peut pas être modifié.',
    'field_order' => 'Ordre',
    'order_help' => 'Les plus petits nombres apparaissent en premier dans le filtre public.',
    'field_colour' => 'Couleur du badge',
    'colour_help' => 'Des paires de la palette du site. Chacune garde un contraste du texte d\'au moins 4,5 pour 1.',
    'colour_navy' => 'Bleu marine',
    'colour_green' => 'Vert',
    'colour_amber' => 'Ambre',
    'colour_red' => 'Rouge',
    'colour_purple' => 'Violet',
    'colour_grey' => 'Gris',
    'contrast' => 'contraste :ratio pour 1',

    'create' => 'Créer l\'entrée',
    'save' => 'Enregistrer l\'entrée',
    'created' => ':name créé.',
    'saved' => ':name enregistré.',
    'deleted' => ':name supprimé.',

    'delete_title' => 'Supprimer',
    'delete' => 'Supprimer l\'entrée',
    'delete_unused' => 'Aucun élément n\'utilise cette entrée. La supprimer la retire des filtres publics.',
    'delete_confirm' => 'Supprimer :name ? Cette action est définitive.',
    'in_use' => '{1} :name est utilisé par 1 élément et ne peut pas être supprimé. Rattachez d\'abord cet élément à une autre entrée.|[2,*] :name est utilisé par :count éléments et ne peut pas être supprimé. Rattachez d\'abord ces éléments à une autre entrée.',

    'photo' => 'Photographie',
    'photo_choose' => 'Choisir dans la médiathèque',
    'photo_none' => 'Aucune photographie',
    'photo_upload' => 'Téléverser une photo',
    'photo_upload_help' => 'JPG, PNG ou WebP, 10 Mo au plus. Elle est ajoutée à la médiathèque. Le texte alternatif en anglais, en français et en portugais est obligatoire.',
    'photo_alt_in' => 'Texte alternatif en :language',
    'focal' => 'Point focal',
    'focal_help' => 'Le point autour duquel la photographie est recadrée sur les cartes et la page de l\'article. Cliquez sur la photographie, ou placez le focus sur le repère et utilisez les flèches (Maj pour de plus grands pas). Laissez les deux champs vides pour le cadrage habituel.',
    'focal_marker' => 'Repère du point focal',
    'focal_x' => 'Depuis la gauche (%)',
    'focal_y' => 'Depuis le haut (%)',
];
