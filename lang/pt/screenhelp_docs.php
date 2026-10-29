<?php

/*
 * Ajuda por ecrã para a gestão de documentos, por nome de rota.
 */

return [
    'extranet.folder' => [
        'purpose' => 'Os ficheiros de uma pasta, quem os pode ver e o que aconteceu na pasta.',
        'actions' => ['Carregar vários ficheiros de uma vez', 'Abrir um PDF no navegador ou descarregar um ficheiro', 'Abrir um ficheiro para lhe mudar o nome, movê-lo, substituí-lo ou eliminá-lo'],
    ],
    'extranet.document' => [
        'purpose' => 'Um ficheiro: as suas versões, com quem adicionou cada uma e quando, e a sua ligação de partilha.',
        'actions' => ['Carregar uma nova versão', 'Copiar a ligação de partilha', 'Mudar o nome, mover ou enviar o ficheiro para a reciclagem'],
    ],
    'extranet.folders.create' => [
        'purpose' => 'Criar uma pasta ou subpasta, até três níveis.',
        'actions' => ['Dar nome e descrição à pasta', 'Escolher onde fica e o nível de confidencialidade', 'Definir quem pode ver, carregar e validar'],
    ],
    'extranet.folders.edit' => [
        'purpose' => 'Alterar uma pasta: nome, descrição, lugar na árvore e acesso.',
        'actions' => ['Mudar o nome ou a descrição da pasta', 'Movê-la para outra pasta', 'Alterar quem pode ver, carregar e validar'],
    ],
    'extranet.documents.bin' => [
        'purpose' => 'Ficheiros eliminados das pastas que gere, guardados trinta dias.',
        'actions' => ['Restaurar um ficheiro na sua pasta', 'Ver quem eliminou um ficheiro e quando', 'Ver quando um ficheiro é eliminado definitivamente'],
    ],
    'admin.documents' => [
        'purpose' => 'Todas as pastas da área de membros, com quem as pode ver e quantos ficheiros contêm.',
        'actions' => ['Criar uma pasta ou subpasta', 'Editar uma pasta e o seu acesso', 'Eliminar uma pasta vazia'],
    ],
    'admin.documents.create' => [
        'purpose' => 'Criar uma pasta ou subpasta, até três níveis.',
        'actions' => ['Dar nome e descrição à pasta', 'Escolher onde fica e o nível de confidencialidade', 'Definir quem pode ver, carregar e validar'],
    ],
    'admin.documents.edit' => [
        'purpose' => 'Alterar uma pasta: nome, descrição, lugar na árvore e acesso.',
        'actions' => ['Mudar o nome ou a descrição da pasta', 'Movê-la para outra pasta', 'Alterar quem pode ver, carregar e validar'],
    ],
    'admin.documents.bin' => [
        'purpose' => 'Ficheiros eliminados das pastas da área de membros, guardados trinta dias.',
        'actions' => ['Restaurar um ficheiro na sua pasta', 'Ver quem eliminou um ficheiro e quando', 'Ver quando um ficheiro é eliminado definitivamente'],
    ],
];
