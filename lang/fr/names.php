<?php

/*
 * Names held in a single database column (countries, sectors, committees,
 * indicators), in French. Read by App\Models\Concerns\HasLocalisedNames.
 *
 * MACHINE TRANSLATION, 14 October 2026, not yet reviewed by a French
 * speaker: docs/TRANSLATION_REVIEW.md lists every entry for review.
 * Indicator names follow the UIT and UPU French terminology where it exists.
 */
return [
    'countries' => [
        'ao' => 'Angola',
        'bw' => 'Botswana',
        'km' => 'Comores',
        'cd' => 'RD Congo',
        'sz' => 'Eswatini',
        'ls' => 'Lesotho',
        'mw' => 'Malawi',
        'mu' => 'Maurice',
        'mz' => 'Mozambique',
        'na' => 'Namibie',
        'za' => 'Afrique du Sud',
        'tz' => 'Tanzanie',
        'zm' => 'Zambie',
        'zw' => 'Zimbabwe',
        'mg' => 'Madagascar',
        'sc' => 'Seychelles',
    ],
    'sectors' => [
        'telecommunications' => 'Télécommunications',
        'broadcasting' => 'Radiodiffusion',
        'postal' => 'Services postaux',
    ],
    'committees' => [
        'spectrum-and-frequency-management' => 'Gestion du spectre et des fréquences',
        'postal-and-logistics' => 'Poste et logistique',
        'legal-and-regulatory-affairs' => 'Affaires juridiques et réglementaires',
        'economics' => 'Économie',
        'consumer-affairs-and-protection' => 'Affaires et protection des consommateurs',
        'human-and-institutional-capacity' => 'Capacités humaines et institutionnelles',
    ],
    'indicators' => [
        'fixed-telephone-subscriptions' => [
            'name' => 'Abonnements au téléphone fixe pour 100 habitants',
            'definition' => 'Somme des lignes téléphoniques fixes analogiques actives, des abonnements de voix sur IP (VoIP), des abonnements à la boucle locale hertzienne fixe (WLL), des équivalents en canaux vocaux RNIS et des cabines téléphoniques publiques fixes, divisée par la population et multipliée par 100. Anciennement appelées lignes téléphoniques principales en service.',
        ],
        'mobile-cellular-subscriptions' => [
            'name' => 'Abonnements au téléphone mobile cellulaire pour 100 habitants',
            'definition' => 'Abonnements à un service public de téléphonie mobile donnant accès au RTPC au moyen de la technologie cellulaire, répartis entre abonnements postpayés et comptes prépayés actifs utilisés au cours des trois derniers mois. S\'applique à tous les abonnements offrant des communications vocales. Sont exclus les cartes de données et modems USB, les services publics de données mobiles, la radio mobile privée à ressources partagées, le télépoint, la radiomessagerie, la communication M2M et la télémesure. Divisés par la population et multipliés par 100.',
        ],
        'fixed-broadband-subscriptions' => [
            'name' => 'Abonnements au large bande fixe pour 100 habitants',
            'definition' => 'Abonnements fixes à un accès haut débit à l\'Internet public, une connexion TCP/IP, à des débits descendants égaux ou supérieurs à 256 kbit/s. Comprend les modems câble, la DSL, la fibre jusqu\'au domicile ou à l\'immeuble, le large bande par satellite, le large bande hertzien fixe de Terre et le WiMAX fixe, résidentiels comme professionnels, quel que soit le mode de paiement. Exclut l\'accès aux communications de données par les réseaux mobiles cellulaires. Divisés par la population et multipliés par 100.',
        ],
        'active-mobile-broadband-subscriptions' => [
            'name' => 'Abonnements actifs au large bande mobile pour 100 habitants',
            'definition' => 'Somme des abonnements actifs au large bande mobile à l\'Internet public, sur combiné ou sur ordinateur. Couvre les abonnés effectifs et non potentiels. Un abonnement doit comporter une redevance récurrente ou avoir accédé à l\'Internet au cours des trois derniers mois. Comprend les réseaux offrant des débits descendants d\'au moins 256 kbit/s ; exclut les abonnements donnant accès uniquement au GPRS, à l\'EDGE ou au CDMA 1xRTT. Divisés par la population et multipliés par 100.',
        ],
        'international-bandwidth-per-inhabitant' => [
            'name' => 'Utilisation de la bande passante internationale par habitant',
            'definition' => 'Utilisation moyenne de toutes les liaisons internationales, y compris les câbles à fibre optique, les liaisons radioélectriques et le trafic traité par les stations terriennes de satellite et les téléports, en moyenne sur les douze mois de l\'année de référence. Lorsque le trafic est asymétrique, la plus élevée des valeurs entrante et sortante est retenue. Tous les types d\'opérateurs sont comptés. Divisée par la population totale.',
        ],
        'population-covered-3g' => [
            'name' => 'Pourcentage de la population couverte par au moins un réseau mobile 3G',
            'definition' => 'Pourcentage d\'habitants à portée d\'au moins un signal mobile cellulaire 3G, qu\'ils soient abonnés ou non. Calculé en divisant le nombre d\'habitants couverts par au moins un signal 3G par la population totale, multiplié par 100. Exclut les personnes couvertes uniquement par le GPRS, l\'EDGE ou le CDMA 1xRTT.',
        ],
        'multichannel-tv-subscriptions' => [
            'name' => 'Abonnements à la télévision multicanal pour 100 habitants',
            'definition' => 'Services offrant des programmes de télévision en plus des chaînes hertziennes gratuites : télévision par câble, satellite direct, télévision sur protocole Internet et télévision numérique de Terre. Comptés uniquement lorsque des chaînes supplémentaires sont disponibles contre paiement d\'une redevance. Divisés par la population et multipliés par 100.',
        ],
        'licensed-postal-operators' => [
            'name' => 'Nombre d\'opérateurs postaux titulaires d\'une licence, autorisés ou déclarés',
            'definition' => 'Opérateurs fournissant des services de distribution de lettres, de colis et d\'envois express, qui ne sont pas nécessairement chargés d\'assurer le service universel ni de remplir les obligations découlant des Actes de l\'UPU.',
        ],
        'permanent-post-offices' => [
            'name' => 'Nombre de bureaux de poste permanents',
            'definition' => 'Tous les bureaux ouverts au public et exploités dans des locaux fixes, soit la somme des bureaux tenus par des agents de l\'opérateur désigné et des bureaux tenus par des personnes extérieures à l\'opérateur désigné. Exclut les bureaux de poste mobiles.',
        ],
        'letter-post-items-domestic' => [
            'name' => 'Envois de la poste aux lettres, service intérieur',
            'definition' => 'Volume total du courrier de la poste aux lettres traité par l\'opérateur désigné dans son service intérieur. Comprend en principe les envois ordinaires et les envois faisant l\'objet d\'un traitement particulier, à savoir les envois recommandés, les lettres avec valeur déclarée et les journaux, ainsi que les envois publicitaires et le courrier hybride.',
        ],
        'postal-parcels-domestic' => [
            'name' => 'Colis postaux, service intérieur',
            'definition' => 'Nombre de colis postaux ordinaires et avec valeur déclarée traités par l\'opérateur désigné dans son service intérieur.',
        ],
        'postal-staff-total' => [
            'name' => 'Effectif total du personnel postal',
            'definition' => 'Somme du personnel à temps plein et à temps partiel, soit le nombre de personnes physiques employées par l\'opérateur désigné et non le nombre de postes pourvus en équivalents temps plein. Exclut les personnes employées par des sous-traitants et le personnel temporaire engagé pour les périodes de congés ou des événements ponctuels.',
        ],
        'postal-transit-time-standard' => [
            'name' => 'Délai d\'acheminement prévu de bout en bout pour la poste aux lettres prioritaire intérieure',
            'definition' => 'Délai d\'acheminement prévu de bout en bout pour les envois prioritaires de la poste aux lettres du service intérieur, généralement un, deux ou trois jours après le dépôt. Lorsque des normes différentes s\'appliquent à différents parcours du pays, la plus courte est retenue. Lorsque plusieurs normes s\'appliquent au même parcours, la plus courte est retenue.',
        ],
        'postal-items-within-standard' => [
            'name' => 'Pourcentage d\'envois de la poste aux lettres distribués dans le respect de la norme de qualité',
            'definition' => 'Pourcentage d\'envois effectivement distribués dans le délai d\'acheminement prévu par la norme, soit le résultat annuel d\'une mesure de la qualité du service intérieur pour l\'année considérée.',
        ],
    ],
];
