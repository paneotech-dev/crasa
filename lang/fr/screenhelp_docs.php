<?php

/*
 * Aide par écran pour la gestion des documents, par nom de route.
 */

return [
    'extranet.folder' => [
        'purpose' => "Les fichiers d'un dossier, qui peut les voir et ce qui s'est passé dans le dossier.",
        'actions' => ['Déposer plusieurs fichiers à la fois', 'Ouvrir un PDF dans le navigateur ou télécharger un fichier', 'Ouvrir un fichier pour le renommer, le déplacer, le remplacer ou le supprimer'],
    ],
    'extranet.document' => [
        'purpose' => 'Un fichier : ses versions, avec qui a ajouté chacune et quand, et son lien de partage.',
        'actions' => ['Déposer une nouvelle version', 'Copier le lien de partage', 'Renommer, déplacer ou mettre le fichier à la corbeille'],
    ],
    'extranet.folders.create' => [
        'purpose' => "Créer un dossier ou un sous-dossier, jusqu'à trois niveaux.",
        'actions' => ['Nommer et décrire le dossier', 'Choisir son emplacement et son niveau de confidentialité', 'Définir qui peut voir, déposer et valider'],
    ],
    'extranet.folders.edit' => [
        'purpose' => "Modifier un dossier : nom, description, place dans l'arborescence et accès.",
        'actions' => ['Renommer ou décrire le dossier', 'Le déplacer sous un autre dossier', 'Modifier qui peut voir, déposer et valider'],
    ],
    'extranet.documents.bin' => [
        'purpose' => 'Fichiers supprimés des dossiers que vous gérez, conservés trente jours.',
        'actions' => ['Restaurer un fichier dans son dossier', 'Voir qui a supprimé un fichier et quand', 'Voir quand un fichier sera supprimé définitivement'],
    ],
    'admin.documents' => [
        'purpose' => "Tous les dossiers de l'espace membres, avec qui peut les voir et combien de fichiers ils contiennent.",
        'actions' => ['Créer un dossier ou un sous-dossier', 'Modifier un dossier et son accès', 'Supprimer un dossier vide'],
    ],
    'admin.documents.create' => [
        'purpose' => "Créer un dossier ou un sous-dossier, jusqu'à trois niveaux.",
        'actions' => ['Nommer et décrire le dossier', 'Choisir son emplacement et son niveau de confidentialité', 'Définir qui peut voir, déposer et valider'],
    ],
    'admin.documents.edit' => [
        'purpose' => "Modifier un dossier : nom, description, place dans l'arborescence et accès.",
        'actions' => ['Renommer ou décrire le dossier', 'Le déplacer sous un autre dossier', 'Modifier qui peut voir, déposer et valider'],
    ],
    'admin.documents.bin' => [
        'purpose' => "Fichiers supprimés des dossiers de l'espace membres, conservés trente jours.",
        'actions' => ['Restaurer un fichier dans son dossier', 'Voir qui a supprimé un fichier et quand', 'Voir quand un fichier sera supprimé définitivement'],
    ],
];
