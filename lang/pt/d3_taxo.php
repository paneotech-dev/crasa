<?php

/*
 * Conteúdo > Taxonomias, e a fotografia do formulário de notícias (dia 3, taxo).
 */
return [
    'nav' => 'Taxonomias',
    'title' => 'Taxonomias',
    'note' => 'As listas pelas quais a sala de imprensa e a biblioteca filtram. As alterações aparecem de imediato no sítio público, nas três línguas.',

    'kind_categories' => 'Categorias de notícias',
    'kind_publication-types' => 'Tipos de publicação',
    'kind_sectors' => 'Setores',
    'kind_tags' => 'Etiquetas',

    'new_categories' => 'Nova categoria de notícias',
    'new_publication-types' => 'Novo tipo de publicação',
    'new_sectors' => 'Novo setor',
    'new_tags' => 'Nova etiqueta',

    'used_by_categories' => 'Cada notícia tem uma categoria. O filtro da sala de imprensa e o distintivo de cada notícia seguem esta lista e a sua ordem.',
    'used_by_publication-types' => 'Cada publicação tem um tipo. O filtro da biblioteca e a etiqueta de cada publicação seguem esta lista e a sua ordem.',
    'used_by_sectors' => 'Telecomunicações, radiodifusão e serviços postais. Comités, instrumentos, indicadores, publicações e notícias remetem para um setor.',
    'used_by_tags' => 'Uma notícia pode ter várias etiquetas. O filtro de etiquetas da sala de imprensa segue esta lista e a sua ordem.',

    'empty_title' => 'Esta lista ainda está vazia',
    'empty_body' => 'Acrescente a primeira entrada com o botão acima.',

    'col_order' => 'Ordem',
    'col_name' => 'Nome',
    'col_slug' => 'Identificador',
    'col_badge' => 'Distintivo',
    'col_used' => 'Utilizado por',

    'field_names' => 'Nome',
    'field_name_in' => 'Nome em :language',
    'field_slug' => 'Identificador',
    'slug_help' => 'Letras minúsculas, algarismos, hífenes e sublinhados. Aparece no endereço das páginas filtradas.',
    'slug_help_existing' => 'Alterá-lo muda o endereço das páginas filtradas: as ligações guardadas com o identificador antigo deixam de filtrar. Os elementos que o utilizam acompanham a alteração.',
    'slug_locked' => 'O identificador de um setor designa o seu ícone e os endereços para onde a página inicial remete, por isso não pode ser alterado.',
    'field_order' => 'Ordem',
    'order_help' => 'Os números mais baixos aparecem primeiro no filtro público.',
    'field_colour' => 'Cor do distintivo',
    'colour_help' => 'Pares da paleta do sítio. Cada um mantém o contraste do texto em 4,5 para 1 ou mais.',
    'colour_navy' => 'Azul-marinho',
    'colour_green' => 'Verde',
    'colour_amber' => 'Âmbar',
    'colour_red' => 'Vermelho',
    'colour_purple' => 'Roxo',
    'colour_grey' => 'Cinzento',
    'contrast' => 'contraste de :ratio para 1',

    'create' => 'Criar entrada',
    'save' => 'Guardar entrada',
    'created' => ':name criado.',
    'saved' => ':name guardado.',
    'deleted' => ':name eliminado.',

    'delete_title' => 'Eliminar',
    'delete' => 'Eliminar entrada',
    'delete_unused' => 'Nenhum elemento utiliza esta entrada. Eliminá-la retira-a dos filtros públicos.',
    'delete_confirm' => 'Eliminar :name? Esta ação não pode ser anulada.',
    'in_use' => '{1} :name é utilizado por 1 elemento e não pode ser eliminado. Associe primeiro esse elemento a outra entrada.|[2,*] :name é utilizado por :count elementos e não pode ser eliminado. Associe primeiro esses elementos a outra entrada.',

    'photo' => 'Fotografia',
    'photo_choose' => 'Escolher da mediateca',
    'photo_none' => 'Sem fotografia',
    'photo_upload' => 'Carregar uma fotografia',
    'photo_upload_help' => 'JPG, PNG ou WebP, até 10 MB. É acrescentada à mediateca. O texto alternativo em inglês, francês e português é obrigatório.',
    'photo_alt_in' => 'Texto alternativo em :language',
    'focal' => 'Ponto focal',
    'focal_help' => 'O ponto em torno do qual a fotografia é recortada nos cartões e na página do artigo. Clique na fotografia, ou coloque o foco no marcador e use as setas (Shift para passos maiores). Deixe ambos os campos vazios para o enquadramento habitual.',
    'focal_marker' => 'Marcador do ponto focal',
    'focal_x' => 'A partir da esquerda (%)',
    'focal_y' => 'A partir do topo (%)',
];
