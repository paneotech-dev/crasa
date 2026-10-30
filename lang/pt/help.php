<?php

/*
 * Onboarding. Plain language, no jargon, and every sentence about this
 * platform rather than about content management in general.
 */

return [
    'title' => 'Ajuda',
    'note' => 'O que faz cada parte da administração, e as regras que a plataforma cumpre.',
    'welcome_title' => 'Bem-vindo, :name',
    'welcome_body' => 'É aqui que o site da CRASA se edita. Seis secções, cada uma com uma função. Nada aqui precisa de um programador nem de uma implantação.',
    'welcome_foot' => 'Este painel desaparece quando o fechar. Tudo o que contém fica na',
    'open_help' => 'página de ajuda',
    'dismiss' => 'Fechar',
    'restore_hints' => 'Mostrar a introdução novamente',
    'sections_title' => 'As seis secções',
    'blocks_title' => 'O que faz cada bloco',
    'blocks_note' => 'Uma página é construída com blocos. Estes são os doze que pode acrescentar.',
    'rules_title' => 'Regras que a plataforma cumpre',
    'step_content_title' => 'Conteúdo',
    'step_content_body' => 'Todas as páginas do site público. Uma página é uma lista de blocos: acrescenta, reordena, oculta e edita aqui. Nada fica no ar antes de publicar, e pode pré-visualizar primeiro.',
    'step_translations_title' => 'Traduções',
    'step_translations_body' => 'Inglês, francês e português lado a lado, com uma medida de cobertura por registo. Um registo pode ser publicado só em inglês; exigir as três pararia tudo.',
    'step_media_title' => 'Multimédia',
    'step_media_body' => 'Todas as imagens do site. Carregue uma vez e os tamanhos publicados são gerados. Uma imagem precisa de texto alternativo numa língua para aparecer nas páginas dessa língua.',
    'step_data_title' => 'Dados regulatórios',
    'step_data_body' => 'Estados membros, autoridades, comités, indicadores, instrumentos e publicações. As tabelas de referência podem ser corrigidas mas nunca eliminadas, porque tudo o resto aponta para elas.',
    'step_users_title' => 'Utilizadores',
    'step_users_body' => 'Quem tem conta e o que pode fazer. A matriz de permissões no fundo dessa página é a resposta completa, numa tabela.',
    'step_audit_title' => 'Registo de auditoria',
    'step_audit_body' => 'Cada alteração, quem a fez e quando. É apenas de acrescento: nada aqui pode ser editado ou eliminado, nem por um administrador.',
    'rule_languages_title' => 'Um registo pode existir só em inglês',
    'rule_languages_body' => 'Quando falta uma tradução, o inglês aparece no seu lugar, marcado como inglês para que um leitor de ecrã mude de voz, com um aviso por cima. Nunca fica em branco, nunca é escondido e nunca é traduzido automaticamente na hora.',
    'rule_publishing_title' => 'Publicar e editar são atos distintos',
    'rule_publishing_body' => 'Uma página passa por rascunho, revisão, publicada. Acrescentar um bloco a uma página no ar não o coloca no site: um bloco novo chega oculto, para que nada apareça à frente de catorze reguladores antes de estar preenchido.',
    'rule_alt_text_title' => 'Uma imagem sem texto alternativo não aparece',
    'rule_alt_text_body' => 'O texto alternativo é por língua. Uma imagem com texto em inglês e sem português não aparece de todo nas páginas portuguesas, em vez de aparecer com um atributo alt vazio ou uma frase inglesa no meio de uma página portuguesa.',
    'rule_deleting_title' => 'Eliminar e retirar são coisas diferentes',
    'rule_deleting_body' => 'O conteúdo é eliminado de forma reversível e pode ser restaurado com as traduções. Os dados de referência não podem ser eliminados. Uma imagem usada numa página não pode ser eliminada enquanto lá estiver.',
    'rule_audit_title' => 'Cada alteração fica registada',
    'rule_audit_body' => 'Alterações de conteúdo, mudanças de função, autenticações e descarregamentos escrevem todos uma linha. O registo não pode ser editado nem eliminado por ninguém, e é isso que o torna digno de leitura.',
    'rule_placeholders_title' => 'O conteúdo em falta é assinalado, não inventado',
    'rule_placeholders_body' => 'Onde a CRASA ainda tem de fornecer texto, o bloco fica assinalado e di-lo na página. Execute php artisan content:placeholders para a lista completa do que falta e do que cada um precisa.',
];
