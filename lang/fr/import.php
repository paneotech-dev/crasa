<?php

/*
 * Bulk import and export, RP-08 and BUILD_SPEC 6.2.
 *
 * Errors here name the row, the column and the reason. CLAUDE.md's own
 * example of a good error is exactly that shape.
 */

return [
    'title' => 'Import en masse',
    'note' => 'Un tableur en entrée, vérifié avant toute écriture.',
    'step_template' => 'Télécharger un modèle',
    'step_template_note' => 'Pré-rempli avec les noms, références et unités des indicateurs.',
    'template_all' => 'Tous les indicateurs',
    'template_hint' => 'Remplissez les colonnes valeur et source. Laissez une ligne vide si vous n\'avez rien à déclarer : une ligne vide n\'est pas une erreur, elle est publiée comme Non déclaré.',
    'step_upload' => 'Déposer le fichier',
    'step_upload_note' => 'Rien n\'est encore écrit. Le fichier est lu et analysé d\'abord.',
    'file' => 'Fichier CSV',
    'file_hint' => 'Le modèle que vous avez téléchargé, rempli. Jusqu\'à 2 Mo.',
    'check_action' => 'Vérifier le fichier',
    'step_report' => 'Contenu du fichier',
    'step_export' => 'Exporter ce retour',
    'step_export_note' => 'Les valeurs telles quelles, en CSV ou JSON. RP-08.',
    'export_csv' => 'Exporter en CSV',
    'export_json' => 'Exporter en JSON',
    'col_indicator' => 'Indicateur',
    'col_slug' => 'Référence',
    'col_unit' => 'Unité',
    'col_value' => 'Valeur',
    'col_source_note' => 'Source',
    'col_row' => 'Ligne',
    'col_column' => 'Colonne',
    'col_reason' => 'Motif',
    'errors_found' => '{1} Une ligne ne peut pas être importée.|[2,*] :count lignes ne peuvent pas être importées.',
    'errors_body' => 'Rien n\'a été écrit. Corrigez le fichier et redéposez-le : un jeu de données à moitié importé est pire qu\'un import refusé, car personne ne sait quelle moitié est réelle.',
    'error_no_slug' => 'Aucune référence d\'indicateur dans cette ligne.',
    'error_unknown_slug' => 'Aucun indicateur ne porte la référence :slug. Utilisez le modèle plutôt que de saisir une référence.',
    'error_not_a_number' => 'Doit être un nombre en :unit. Trouvé « :found ».',
    'error_not_a_boolean' => 'Doit être oui ou non. Trouvé « :found ».',
    'error_no_source' => 'Une valeur a besoin d\'une source. Indiquez sa provenance, pour pouvoir la défendre lorsqu\'un État membre la conteste.',
    'ready' => '{1} Une ligne est prête à être importée.|[2,*] :count lignes sont prêtes à être importées.',
    'commit_hint' => 'Toutes les lignes sont écrites ou aucune. Les valeurs existantes pour les mêmes indicateurs sont remplacées.',
    'commit_action' => 'Importer ces lignes',
    'committed' => '{1} Une valeur importée.|[2,*] :count valeurs importées.',
    'has_errors' => 'Ce fichier comporte des erreurs : rien n\'a été écrit. Corrigez-le et redéposez-le.',
    'expired' => 'Le fichier vérifié n\'est plus dans cette session. Redéposez-le.',
];
