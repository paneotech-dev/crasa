<?php

/*
 * Stream G: the accessibility statement (G5). The three files carry the
 * same keys; AccessibilityStatementController renders them.
 */
return [
    'slug' => 'accessibilite',
    'statement' => [
        'title' => 'Déclaration d\'accessibilité',
        'eyebrow' => 'Accessibilité',
        'lead' => 'Cette plateforme est conçue pour que chacun puisse la lire, y chercher et l\'utiliser : au clavier, avec un lecteur d\'écran, une loupe, un téléphone ou une connexion lente, en anglais, en français et en portugais.',
        'meta_description' => 'Comment la plateforme de la CRASA répond aux WCAG 2.1 niveau AA, ce qui n\'y répond pas encore, et comment signaler un obstacle.',
        'fig_standard' => 'WCAG 2.1 niveau AA',
        'fig_standard_label' => 'Norme visée',
        'fig_review' => '29 octobre 2026',
        'fig_review_label' => 'Dernière révision',
        'toc' => 'Sur cette page',
        'sections' => [
            'standard' => [
                'title' => 'La norme suivie',
                'body' => [
                    'Cette plateforme est conçue pour être conforme aux Règles pour l\'accessibilité des contenus Web (WCAG) 2.1, niveau AA, le niveau adopté par la plupart des organismes publics. Elle couvre le site public, l\'espace membres et l\'administration, en anglais, en français et en portugais.',
                    'Le niveau A est le minimum, sans lequel certaines personnes ne peuvent pas utiliser une page. Le niveau AA, notre objectif, supprime les obstacles les plus courants pour les personnes malvoyantes, daltoniennes ou ayant un handicap moteur, auditif ou cognitif. Le niveau AAA est le plus élevé et n\'est pas exigé pour un site entier ; nous en appliquons une partie lorsque c\'est possible.',
                ],
            ],
            'done' => [
                'title' => 'Ce que nous avons fait',
                'items' => [
                    'Chaque page commence par un lien « Aller au contenu », comporte un seul titre principal, des titres dans l\'ordre et une langue déclarée.',
                    'Tout fonctionne au clavier : menus, choix de la langue, diaporama, onglets, filtres, cartes, comparateur, formulaires et boîtes de dialogue. Un contour de focus visible indique où vous êtes.',
                    'Le texte a un contraste d\'au moins 4,5:1 avec son fond, et les commandes 3:1. Les valeurs sont mesurées par un script, pas estimées à l\'œil.',
                    'Les images ont une description textuelle, ou sont marquées comme décoratives lorsqu\'elles n\'apportent rien. Les graphiques et les cartes sont accompagnés d\'un tableau ou d\'une liste donnant la même information.',
                    'La couleur n\'est jamais le seul signal : les statuts portent un mot et une icône, et les entrées du calendrier une lettre.',
                    'Les formulaires ont des étiquettes au-dessus des champs, les champs obligatoires sont indiqués en toutes lettres, et les erreurs sont écrites à côté du champ et lues par les lecteurs d\'écran.',
                    'Les pages s\'adaptent à un écran de 320 pixels de large sans défilement horizontal, et restent utilisables avec un zoom à 200 %.',
                    'Les contenus animés peuvent être mis en pause, et rien ne bouge si votre appareil demande de réduire les animations.',
                ],
            ],
            'options' => [
                'title' => 'Options d\'affichage',
                'body' => [
                    'Le bouton Options d\'affichage, en haut de chaque page, permet de changer la taille du texte (quatre niveaux), d\'agrandir l\'interligne, d\'utiliser l\'espacement de lecture (lettres et mots plus espacés, sans italique, ce qui aide de nombreux lecteurs dyslexiques), de passer en contraste élevé, de souligner tous les liens et d\'arrêter les animations.',
                    'Vos choix sont conservés dans ce navigateur et appliqués à chaque page jusqu\'à leur réinitialisation. Rien n\'est envoyé à la CRASA.',
                ],
            ],
            'tested' => [
                'title' => 'Comment nous testons',
                'items' => [
                    'Des contrôles automatiques avec axe-core sur chaque page publique et chaque type d\'écran d\'administration, dans les trois langues.',
                    'Un parcours complet au clavier sur chaque type de page, avec un zoom à 200 % et sur 320 pixels de large.',
                    'Des vérifications avec les lecteurs d\'écran NVDA sous Windows et VoiceOver sous macOS et iPhone.',
                    'La validation HTML des modèles de page.',
                ],
            ],
            'limits' => [
                'title' => 'Exceptions et limites connues',
                'intro' => 'Ces éléments ne répondent pas encore entièrement à la norme. Chacun a une date de constat et une date pour l\'étape suivante.',
                'items' => [
                    [
                        'what' => 'Anciens documents PDF',
                        'detail' => 'Certaines lignes directrices et rapports repris de l\'ancien site ne sont pas balisés pour les lecteurs d\'écran. Demandez-nous et nous vous enverrons le contenu dans un format accessible.',
                        'found' => '29 octobre 2026',
                        'next' => 'Documents les plus téléchargés revus d\'ici le 31 janvier 2027',
                    ],
                    [
                        'what' => 'Textes en français et en portugais',
                        'detail' => 'Une partie des textes en français et en portugais a été traduite automatiquement et est en cours de relecture par la CRASA. La version anglaise est accessible en un clic depuis le choix de la langue.',
                        'found' => '1er octobre 2026',
                        'next' => 'Relecture en cours, prochain point le 31 janvier 2027',
                    ],
                    [
                        'what' => 'Publications de réseaux sociaux intégrées',
                        'detail' => 'Les publications de X, Facebook et LinkedIn sont affichées par ces plateformes, qui en gèrent l\'accessibilité. Chaque publication a aussi un lien simple pour l\'ouvrir.',
                        'found' => '29 octobre 2026',
                        'next' => 'Vérifiées à chaque révision',
                    ],
                    [
                        'what' => 'Anciennes descriptions d\'images',
                        'detail' => 'Certaines photographies reprises de l\'ancien site ont une description courte ou générale. Une nouvelle image ne peut pas être publiée sans description.',
                        'found' => '29 octobre 2026',
                        'next' => 'Images de l\'actualité revues d\'ici le 31 janvier 2027',
                    ],
                    [
                        'what' => 'Deux couleurs modifiées pour respecter la norme (1.4.11)',
                        'detail' => 'Deux couleurs de la maquette approuvée n\'atteignaient pas le contraste de 3:1 exigé par le critère 1.4.11 pour le contour d\'une commande. Les valeurs livrées diffèrent de la maquette pour le respecter.',
                        'found' => '9 septembre 2026',
                        'next' => 'Résolu dans les valeurs livrées',
                    ],
                    [
                        'what' => 'Texte désactivé (1.4.3)',
                        'detail' => 'La couleur du texte désactivé est volontairement sous 4,5:1 : le critère 1.4.3 exempte le texte d\'une commande inactive.',
                        'found' => '9 septembre 2026',
                        'next' => 'Aucun changement nécessaire',
                    ],
                ],
                'col_what' => 'Élément',
                'col_found' => 'Constaté le',
                'col_next' => 'Étape suivante',
            ],
            'report' => [
                'title' => 'Signaler un obstacle ou demander un autre format',
                'body' => [
                    'Si un élément de cette plateforme vous est difficile ou impossible à utiliser, indiquez au Secrétariat la page et ce qui s\'est passé. Si vous avez besoin d\'un document dans un autre format, précisez lequel.',
                ],
                'email' => 'Courriel',
                'phone' => 'Téléphone',
                'form' => 'Utiliser le formulaire de contact',
                'reply' => 'Nous répondons par écrit et indiquons ce qui sera corrigé et quand.',
            ],
            'review' => [
                'title' => 'Révision de cette déclaration',
                'body' => [
                    'Cette déclaration a été établie le 29 octobre 2026 après une vérification complète de la plateforme, et révisée pour la dernière fois le 29 octobre 2026. Elle est revue après chaque changement important et au moins une fois par an. La prochaine révision est prévue le 31 janvier 2027.',
                ],
            ],
        ],
        'cta_title' => 'Quelque chose ne fonctionne pas pour vous ?',
        'cta_text' => 'Indiquez au Secrétariat la page et ce qui s\'est passé.',
        'cta_button' => 'Contacter le Secrétariat',
    ],
];
