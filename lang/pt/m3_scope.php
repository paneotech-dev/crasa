<?php

/*
 * Item 1j: moderador limitado a Estados-Membros ou comités.
 */

return [
    'section' => 'Âmbito de moderação',
    'help' => 'Para uma conta que aprova conteúdos. Marque Estados-Membros ou comités para limitar a caixa de aprovação aos respetivos itens. Não marque nada para um moderador que vê todos os itens.',
    'rule' => 'Um item pertence a um Estado-Membro ou comité pelo seu próprio campo Estado-Membro ou comité (as publicações têm ambos, os eventos têm um comité). Notícias, páginas, publicações nas redes sociais e itens sem nenhum desses campos são regionais: só os moderadores sem âmbito os veem.',
    'member_states' => 'Estados-Membros',
    'committees' => 'Comités',
    'your_scope' => 'O seu âmbito: :scope',
];
