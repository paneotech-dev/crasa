<?php

return [
    'index_title' => 'États membres',
    'breadcrumb' => 'Fil d\'Ariane',

    'map_label' => 'Les quatorze États membres de la CRASA, sur une carte de l\'Afrique australe. Chacun renvoie à sa fiche.',
    'map_label_shaded' => 'Les quatorze États membres de la CRASA, ombrés selon :dataset. Chaque État affiche son chiffre sur la carte et renvoie à sa fiche. Le tableau ci-dessous reprend les mêmes chiffres.',
    'map_caption' => 'Chaque État membre renvoie à sa fiche. Les Comores et Maurice figurent à part : à l\'échelle de la région, ils ne seraient que des points, et omettre un membre d\'une carte de l\'adhésion serait pire qu\'un encart.',

    'map_shade_by' => 'Ombrer la carte selon',
    'map_option_none' => 'Adhésion seule',
    'map_apply' => 'Afficher sur la carte',
    'map_group_overview' => 'Transmission des données',
    'map_completeness' => 'Complétude des données',
    'map_unit_completeness' => 'pourcentage du registre d\'indicateurs transmis',

    'map_prompt' => 'Pointez un État membre, ou atteignez-le au clavier, pour voir son régulateur et son mandat.',
    'map_islands' => 'États membres insulaires, à plus grande échelle',

    'map_region' => ':country. :authority.',
    'map_region_shaded' => ':country, :value.',
    'map_region_inset' => ':country, à plus grande échelle. :authority.',
    'map_region_inset_shaded' => ':country, à plus grande échelle, :value.',

    'map_low' => 'Faible',
    'map_high' => 'Élevé',
    'map_period_unknown' => 'aucune période enregistrée',
    'map_note_completeness' => 'La complétude est mesurée sur l\'ensemble du registre d\'indicateurs : un État membre qui n\'a rien transmis prend la teinte la plus claire et non une hachure. Cette vue ne masque rien : un État clair n\'a pas transmis.',
    'map_note_indicator' => '{0}Les quatorze États membres ont transmis cet indicateur pour :period.|{1}Chiffres pour :period. Un État membre n\'a pas transmis cet indicateur ; il est hachuré et non ombré.|[2,*]Chiffres pour :period. :count États membres n\'ont pas transmis cet indicateur ; ils sont hachurés et non ombrés.',

    'map_table_toggle' => 'Lire les chiffres sous forme de tableau',
    'map_table_caption' => ':dataset pour chacun des quatorze États membres de la CRASA.',

    'detail_fallback_description' => 'État membre de la CRASA : :country.',
    'detail_iso' => 'Code ISO',
    'detail_acronym' => 'Sigle',
    'detail_sectors' => 'Secteurs relevant du mandat',
    'detail_joined' => 'Adhésion',
    'detail_committees' => 'Sièges en comité',
    'detail_role_member' => 'Membre',
    'detail_role_chair' => 'Présidence',
    'detail_role_observer' => 'Observateur',
    'detail_figures' => 'Chiffres transmis',
    'detail_figures_caption' => 'Chiffres d\'indicateurs transmis par :country.',
    'detail_indicator' => 'Indicateur',
    'detail_period' => 'Période',
    'detail_value' => 'Valeur',
    'detail_source' => 'Source',
    'detail_source_none' => 'Aucune source enregistrée',
    'detail_compare' => 'Comparer :country avec d\'autres États membres',
    'detail_publications' => 'Publications',
    'map_status_plain' => ':count États membres. Choisissez un jeu de données pour ombrer la carte.',
    'map_status_shaded' => ':dataset, :period. :reported États membres sur :total ont transmis. Chaque État affiche son chiffre sur la carte, et le tableau ci-dessous reprend les mêmes chiffres.',
    'count_committees' => '{0}Comités|{1}Comité|[2,*]Comités',
    'tally_instruments' => '{0}Instruments|{1}Instrument|[2,*]Instruments',
    'tally_publications' => '{0}Publications|{1}Publication|[2,*]Publications',
    'tally_indicators' => '{0}Indicateurs|{1}Indicateur|[2,*]Indicateurs',
];
