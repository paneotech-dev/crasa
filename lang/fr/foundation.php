<?php

return [
    'title' => 'Socle technique',
    'heading' => 'Le socle technique est en place',
    'body' => "La couche de jetons, la mise en page de base, le sélecteur trilingue et la règle de repli sur l'anglais sont construits et fonctionnent dans les trois langues. Aucun contenu de page n'existe encore. Les quatorze États membres, la bibliothèque réglementaire et le comparateur suivent l'ordre de construction défini dans le modèle de données.",
    'checklist_heading' => 'Ce que cette page démontre',
    'check_tokens' => "La couleur, la typographie, les espacements, les rayons et les ombres proviennent d'un seul fichier de jetons. Rien sur cette page ne porte de valeur codée en dur.",
    'check_chrome' => "L'en-tête et le pied de page sont inclus depuis des partiels, ils ne sont pas reconstruits écran par écran.",
    'check_switcher' => 'Le sélecteur change de langue sans JavaScript et vous maintient sur la page que vous lisiez.',
    'check_fallback' => "Une fiche sans texte dans la langue demandée est affichée en anglais avec l'avis ci-dessous, et l'absence est journalisée.",
    'notice_absent' => "Vous lisez le français. L'avis ci-dessous apparaît au-dessus de toute fiche dont le texte n'existe qu'en anglais.",
    'notice_heading' => "L'avis de repli, tel qu'il apparaît au-dessus d'une fiche",
];
