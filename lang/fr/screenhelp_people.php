<?php

return [
    'admin.users.show' => ['purpose' => 'Un compte : ses informations et chaque entrée du journal d\'audit le concernant ou faite par lui, les plus récentes en premier.', 'actions' => ['Modifier le compte', 'Afficher les entrées le concernant ou faites par lui', 'Parcourir les entrées']],
    'admin.users.edit' => ['purpose' => 'Modifier le nom, l\'adresse électronique, le rôle, l\'État membre, le régulateur et les sièges en comité d\'un compte ; chaque modification est auditée.', 'actions' => ['Modifier les informations', 'Définir les sièges en comité', 'Enregistrer le compte']],
    'admin.registrations' => ['purpose' => 'Toutes les inscriptions des délégués, par réunion, du dépôt à la confirmation.', 'actions' => ['Confirmer ou refuser une inscription', 'Renvoyer la copie au délégué', 'Exporter en CSV ou en Excel']],
];
