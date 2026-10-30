<?php

/*
 * Item 1j : modérateur limité à des États membres ou à des comités.
 */

return [
    'section' => 'Périmètre de modération',
    'help' => 'Pour un compte qui approuve des contenus. Cochez des États membres ou des comités pour limiter la boîte d\'approbation à leurs éléments. Ne cochez rien pour un modérateur qui voit tous les éléments.',
    'rule' => 'Un élément relève d\'un État membre ou d\'un comité par son propre champ État membre ou comité (les publications ont les deux, les événements ont un comité). Les actualités, les pages, les publications sur les réseaux sociaux et les éléments sans ces champs sont régionaux : seuls les modérateurs sans périmètre les voient.',
    'member_states' => 'États membres',
    'committees' => 'Comités',
    'your_scope' => 'Votre périmètre : :scope',
];
