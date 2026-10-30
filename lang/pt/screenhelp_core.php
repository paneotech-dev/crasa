<?php

/*
 * Help per screen for the back office and the member area, keyed by route
 * name. Each entry: a title, one sentence on what the screen is for, and its
 * three main actions. Read by App\Support\ScreenHelp with every other
 * screenhelp_*.php file. _back_office and _member are each section's general
 * help, shown for a screen that has no entry of its own.
 */

return [
    '_back_office' => [
        'title' => 'Administração',
        'purpose' => 'A administração é onde o Secretariado publica e modera os conteúdos, valida os relatórios dos Estados-membros e gere as contas.',
        'actions' => [
            'Escolha uma secção na barra lateral: o que vê depende da sua função.',
            'Use a caixa de pesquisa da barra superior para encontrar qualquer registo da administração.',
            'Abra o sino para ver os pedidos de aprovação, os relatórios e os resultados de validação que aguardam por si.',
        ],
    ],
    '_member' => [
        'title' => 'Área de membros',
        'purpose' => 'A área de membros é onde os catorze Estados-membros e os comités partilham documentos, apresentam relatórios e acompanham as reuniões.',
        'actions' => [
            'Escolha uma secção na barra lateral: os seus comités, pastas e relatórios.',
            'Use a caixa de pesquisa da barra superior para pesquisar na área de membros.',
            'Abra o sino para ver os novos documentos, as alterações de reuniões e os resultados de validação.',
        ],
    ],

    /* Help itself ---------------------------------------------------------- */

    'screen-help' => [
        'title' => 'Ajuda',
        'purpose' => 'Esta página explica o ecrã de onde veio: para que serve e as suas três ações principais.',
        'actions' => [
            'Leia para que serve o ecrã e quais são as suas ações principais.',
            'Volte ao ecrã com Voltar ao ecrã.',
            'Abra o guia de formação da sua função para obter instruções passo a passo.',
        ],
    ],

    /* Account and notifications ------------------------------------------------ */

    'account' => [
        'title' => 'A sua conta',
        'purpose' => 'Os seus próprios dados: nome, idioma, palavra-passe e a forma como a plataforma o contacta.',
        'actions' => [
            'Altere o idioma da interface e as preferências de visualização.',
            'Altere a sua palavra-passe.',
            'Termine a sessão na plataforma.',
        ],
    ],
    'two-factor.enrol' => [
        'title' => 'Início de sessão em dois passos',
        'purpose' => 'Configura um segundo passo no início de sessão com uma aplicação de autenticação, o que protege as contas que administram a plataforma.',
        'actions' => [
            'Leia o código com uma aplicação de autenticação no seu telemóvel.',
            'Introduza o código de seis dígitos indicado pela aplicação para confirmar.',
            'Guarde os códigos de recuperação num local seguro.',
        ],
    ],
    'my-dashboard' => [
        'title' => 'O meu painel',
        'purpose' => 'A sua página inicial: os últimos elementos publicados e um acesso ao comparador.',
        'actions' => [
            'Abra as últimas publicações, notícias e atualizações regulamentares.',
            'Vá ao comparador para comparar indicadores entre Estados-membros.',
            'Abra a sua conta para alterar o idioma ou a palavra-passe.',
        ],
    ],
    'notifications' => [
        'title' => 'Notificações',
        'purpose' => 'Tudo o que a plataforma lhe comunicou: pedidos de aprovação, relatórios, resultados de validação, novos documentos, alterações de reuniões e inscrições confirmadas.',
        'actions' => [
            'Filtre a lista por tipo, ou mostre apenas as notificações por ler.',
            'Abra uma notificação para ir ao respetivo elemento; fica então marcada como lida.',
            'Marque uma notificação, ou todas, como lidas.',
        ],
    ],

    /* Back office ------------------------------------------------------------- */

    'admin.dashboard' => [
        'title' => 'Painel da administração',
        'purpose' => 'A visão geral do trabalho que aguarda a sua função: aprovações, relatórios a validar, atividade recente e dados de demonstração ainda presentes na base de dados.',
        'actions' => [
            'Abra, a partir dos blocos do painel, um elemento que aguarda a sua decisão.',
            'Acompanhe a atividade recente na plataforma.',
            'Vá a uma secção a partir da barra lateral.',
        ],
    ],
    'admin.search' => [
        'title' => 'Pesquisar na administração',
        'purpose' => 'Encontra páginas, publicações, notícias, eventos, utilizadores e outros registos em toda a administração.',
        'actions' => [
            'Escreva uma palavra ou uma referência na caixa de pesquisa.',
            'Restrinja os resultados por tipo.',
            'Abra um resultado para o editar.',
        ],
    ],
    'admin.help' => [
        'title' => 'Apresentação da administração',
        'purpose' => 'Uma breve apresentação da administração e do ponto de partida de cada secção, que pode reler a qualquer momento.',
        'actions' => [
            'Leia onde começa cada secção.',
            'Reponha as sugestões que ocultou noutros ecrãs.',
            'Abra o guia de formação da sua função.',
        ],
    ],
    'admin.approvals' => [
        'title' => 'Aprovações',
        'purpose' => 'Os elementos enviados para aprovação que aguardam uma decisão, limitados aos tipos de conteúdo que pode aprovar.',
        'actions' => [
            'Abra um elemento para o ler juntamente com as alterações.',
            'Aprove-o para o publicar, ou devolva-o ao autor com um comentário.',
            'Filtre a lista por tipo de conteúdo.',
        ],
    ],
    'admin.approvals.show' => [
        'title' => 'Pedido de aprovação',
        'purpose' => 'Um elemento enviado para aprovação: o conteúdo tal como foi submetido, o que mudou e a nota do autor.',
        'actions' => [
            'Leia a pré-visualização e a lista de alterações.',
            'Aprove o elemento para o publicar.',
            'Devolva-o ao autor com um comentário a indicar o que deve ser alterado.',
        ],
    ],
    'admin.approvals.rules' => [
        'title' => 'Regras de aprovação',
        'purpose' => 'Define, para cada tipo de conteúdo, se a publicação exige aprovação e quem pode dispensá-la.',
        'actions' => [
            'Escolha a regra de cada tipo de conteúdo.',
            'Guarde as regras.',
            'Verifique no ecrã de funções quais as funções que têm a permissão de aprovar.',
        ],
    ],
    'admin.audit' => [
        'title' => 'Registo de auditoria',
        'purpose' => 'O registo de quem alterou o quê e quando: cada publicação, aprovação, alteração de conta e mudança de estado de um relatório.',
        'actions' => [
            'Filtre o registo por utilizador, objeto, ação ou data.',
            'Leia cada alteração: campo, valor antes e valor depois.',
            'Exporte o registo filtrado para CSV.',
        ],
    ],
    'admin.announcements' => [
        'title' => 'Anúncios',
        'purpose' => 'Avisos do Secretariado aos membros, por vezes limitados a um setor ou a um comité.',
        'actions' => [
            'Redija um novo anúncio.',
            'Edite ou publique um rascunho.',
            'Retire da publicação um anúncio que já não se aplica.',
        ],
    ],
    'admin.announcements.create' => [
        'title' => 'Novo anúncio',
        'purpose' => 'Redige um aviso aos membros em inglês, francês e português.',
        'actions' => [
            'Redija o título e o texto, pelo menos em inglês.',
            'Escolha o setor ou o comité a que se destina, se for o caso.',
            'Guarde-o como rascunho e depois publique-o.',
        ],
    ],
    'admin.announcements.edit' => [
        'title' => 'Editar anúncio',
        'purpose' => 'Altera um anúncio e publica-o; os membros são notificados quando é publicado.',
        'actions' => [
            'Altere o texto em cada idioma.',
            'Publique o anúncio, o que notifica os membros a que se destina.',
            'Retire-o da publicação quando já não se aplicar.',
        ],
    ],
    'admin.authority-changes' => [
        'title' => 'Alterações às fichas das autoridades',
        'purpose' => 'Alterações à ficha de um regulador propostas pelo respetivo Estado-membro, a aguardar o Secretariado.',
        'actions' => [
            'Abra uma proposta para a comparar com a ficha atual.',
            'Aprove-a para atualizar a ficha pública.',
            'Recuse-a com uma nota para o Estado-membro.',
        ],
    ],
    'admin.authority-changes.show' => [
        'title' => 'Alteração de ficha proposta',
        'purpose' => 'A alteração proposta por um Estado-membro à ficha do seu regulador, lado a lado com a ficha atual.',
        'actions' => [
            'Compare cada campo proposto com o valor atual.',
            'Aprove a alteração para a aplicar.',
            'Recuse-a com uma nota.',
        ],
    ],
    'admin.committees.seats' => [
        'title' => 'Lugares no comité',
        'purpose' => 'Regista os Estados-membros que têm lugar num comité e em que qualidade.',
        'actions' => [
            'Adicione um Estado-membro ao comité.',
            'Defina a sua função, por exemplo presidente ou membro.',
            'Remova um lugar que já não se aplica.',
        ],
    ],
    'admin.confirmations' => [
        'title' => 'Confirmações dos Estados-membros',
        'purpose' => 'As fichas dos catorze Estados-membros e a indicação de cada uma ter sido ou não confirmada pelo próprio regulador.',
        'actions' => [
            'Abra um Estado-membro para rever a sua ficha.',
            'Veja quais as fichas ainda não verificadas.',
            'Registe quem confirmou uma ficha.',
        ],
    ],
    'admin.confirmations.edit' => [
        'title' => 'Confirmar a ficha de um Estado-membro',
        'purpose' => 'A ficha do regulador de um Estado-membro, editável no próprio ecrã, com uma ação de confirmação que regista quem a validou.',
        'actions' => [
            'Corrija o nome, a sigla, o sítio web ou o mandato.',
            'Registe a pessoa que confirmou os dados.',
            'Confirme a ficha.',
        ],
    ],
    'admin.contributions' => [
        'title' => 'Instrumentos entregues',
        'purpose' => 'Instrumentos nacionais enviados pelos Estados-membros para o registo harmonizado, a aguardar o Secretariado.',
        'actions' => [
            'Abra uma contribuição para a rever.',
            'Valide-a para a integrar no registo.',
            'Devolva-a ao Estado-membro com comentários.',
        ],
    ],
    'admin.contributions.show' => [
        'title' => 'Rever uma contribuição',
        'purpose' => 'Um instrumento entregue: o seu texto, documentos e histórico.',
        'actions' => [
            'Leia o instrumento e os documentos anexos.',
            'Valide-o para o integrar no registo.',
            'Devolva-o com um comentário a indicar o que deve ser alterado.',
        ],
    ],
    'admin.data' => [
        'title' => 'Conteúdos',
        'purpose' => 'A lista dos tipos de conteúdo que pode gerir: publicações, notícias, instrumentos, projetos e os restantes registos.',
        'actions' => [
            'Escolha um tipo de conteúdo para ver os seus registos.',
            'Crie um novo registo desse tipo.',
            'Veja quantos registos tem cada tipo.',
        ],
    ],
    'admin.resources' => [
        'title' => 'Registos',
        'purpose' => 'Os registos de um tipo de conteúdo, com o seu estado e cobertura linguística.',
        'actions' => [
            'Pesquise e filtre os registos.',
            'Abra um registo para o editar.',
            'Adicione um novo registo.',
        ],
    ],
    'admin.resources.create' => [
        'title' => 'Novo registo',
        'purpose' => 'Cria um registo deste tipo de conteúdo.',
        'actions' => [
            'Preencha os campos, pelo menos em inglês.',
            'Guarde-o como rascunho.',
            'Envie-o para aprovação ou publique-o, conforme a sua função o permitir.',
        ],
    ],
    'admin.resources.edit' => [
        'title' => 'Editar registo',
        'purpose' => 'Altera um registo; cada alteração fica registada com o seu autor e a hora.',
        'actions' => [
            'Altere os campos e guarde.',
            'Envie o registo para aprovação ou publique-o, conforme a sua função o permitir.',
            'Elimine o registo se não deveria existir.',
        ],
    ],
    'admin.instruments.versions' => [
        'title' => 'Versões do instrumento',
        'purpose' => 'O histórico de versões de um instrumento regulamentar: quem o alterou e quando.',
        'actions' => [
            'Leia a lista de versões.',
            'Compare uma versão com a anterior.',
            'Reponha uma versão anterior, se a sua função o permitir.',
        ],
    ],
    'admin.instruments.diff' => [
        'title' => 'Comparar versões',
        'purpose' => 'Mostra, campo a campo, o que mudou entre duas versões de um instrumento.',
        'actions' => [
            'Leia o texto removido e o texto acrescentado.',
            'Escolha outras versões para comparar.',
            'Volte ao histórico de versões.',
        ],
    ],
    'admin.media' => [
        'title' => 'Biblioteca multimédia',
        'purpose' => 'As imagens e ficheiros usados no sítio público, com o local onde cada um é usado.',
        'actions' => [
            'Carregue uma nova imagem ou um novo ficheiro.',
            'Abra um elemento para editar a descrição e os créditos.',
            'Veja onde um elemento é usado antes de o substituir.',
        ],
    ],
    'admin.media.create' => [
        'title' => 'Carregar multimédia',
        'purpose' => 'Adiciona uma imagem ou um ficheiro à biblioteca multimédia.',
        'actions' => [
            'Escolha o ficheiro a carregar.',
            'Redija a descrição para leitores de ecrã e os créditos.',
            'Guarde-o na biblioteca.',
        ],
    ],
    'admin.media.edit' => [
        'title' => 'Editar multimédia',
        'purpose' => 'Altera a descrição, os créditos e o recorte de uma imagem ou ficheiro.',
        'actions' => [
            'Altere a descrição e os créditos.',
            'Veja as páginas que o usam.',
            'Substitua-o ou elimine-o.',
        ],
    ],
    'admin.meetings' => [
        'title' => 'Reuniões',
        'purpose' => 'As reuniões e eventos do calendário, futuros e passados, com as respetivas inscrições.',
        'actions' => [
            'Adicione uma reunião.',
            'Abra uma reunião para alterar a data, o local ou o estado.',
            'Abra a lista de pessoas inscritas numa reunião.',
        ],
    ],
    'admin.meetings.create' => [
        'title' => 'Nova reunião',
        'purpose' => 'Adiciona uma reunião ou um evento ao calendário.',
        'actions' => [
            'Introduza o título, as datas e o local.',
            'Escolha o comité e quem a pode ver.',
            'Guarde-a, ou envie-a para aprovação.',
        ],
    ],
    'admin.meetings.edit' => [
        'title' => 'Editar reunião',
        'purpose' => 'Altera uma reunião; os membros do comité e as pessoas inscritas são notificados quando é alterada ou cancelada.',
        'actions' => [
            'Altere as datas, o local ou a ligação online.',
            'Defina o estado como adiada ou cancelada.',
            'Guarde a alteração.',
        ],
    ],
    'admin.meetings.delete' => [
        'title' => 'Eliminar reunião',
        'purpose' => 'Confirma que uma reunião deve ser retirada do calendário, juntamente com os seus documentos.',
        'actions' => [
            'Verifique se esta é a reunião a retirar.',
            'Considere antes defini-la como cancelada, o que conserva o registo.',
            'Confirme a eliminação.',
        ],
    ],
    'admin.delegates' => [
        'title' => 'Inscrições',
        'purpose' => 'As pessoas inscritas numa reunião, com o estado de cada inscrição.',
        'actions' => [
            'Ordene e leia a lista de inscrições.',
            'Confirme ou recuse uma inscrição.',
            'Transfira a lista como folha de cálculo.',
        ],
    ],
    'admin.menus' => [
        'title' => 'Menus',
        'purpose' => 'Os menus do sítio público, com a sua ordem e as etiquetas em cada idioma.',
        'actions' => [
            'Adicione um elemento a um menu.',
            'Altere a ordem dos elementos.',
            'Altere a etiqueta de um elemento em inglês, francês e português.',
        ],
    ],
    'admin.pages' => [
        'title' => 'Páginas',
        'purpose' => 'As páginas do sítio público, com o seu estado e cobertura linguística.',
        'actions' => [
            'Abra uma página para a editar.',
            'Adicione uma nova página.',
            'Filtre as páginas por estado.',
        ],
    ],
    'admin.pages.create' => [
        'title' => 'Nova página',
        'purpose' => 'Cria uma página no sítio público.',
        'actions' => [
            'Introduza o título e o endereço.',
            'Escolha a sua posição no sítio.',
            'Guarde-a como rascunho e adicione os seus blocos.',
        ],
    ],
    'admin.pages.edit' => [
        'title' => 'Editar página',
        'purpose' => 'Altera o texto e os blocos de uma página; um elemento em revisão abre apenas para leitura.',
        'actions' => [
            'Altere o texto e a ordem dos blocos.',
            'Pré-visualize a página.',
            'Envie-a para aprovação ou publique-a, conforme a sua função o permitir.',
        ],
    ],
    'admin.pages.preview' => [
        'title' => 'Pré-visualização da página',
        'purpose' => 'Mostra a página tal como aparecerá no sítio público, antes de ser publicada.',
        'actions' => [
            'Leia a página tal como um visitante a verá.',
            'Verifique-a com a largura de um telemóvel.',
            'Volte ao editor para a alterar.',
        ],
    ],
    'admin.pages.blocks.create' => [
        'title' => 'Adicionar um bloco',
        'purpose' => 'Adiciona um bloco a uma página, por exemplo texto, uma imagem ou uma lista.',
        'actions' => [
            'Escolha o tipo de bloco.',
            'Preencha o seu conteúdo.',
            'Guarde-o na página.',
        ],
    ],
    'admin.pages.blocks.edit' => [
        'title' => 'Editar um bloco',
        'purpose' => 'Altera um bloco de uma página.',
        'actions' => [
            'Altere o conteúdo do bloco.',
            'Guarde o bloco.',
            'Remova o bloco da página.',
        ],
    ],
    'admin.reports' => [
        'title' => 'Notas regionais',
        'purpose' => 'Notas geradas a partir dos indicadores, por Estado-membro ou por indicador, guardadas para poderem ser consultadas novamente.',
        'actions' => [
            'Gere uma nota para um Estado-membro ou um indicador.',
            'Abra uma nota guardada.',
            'Imprima uma nota ou transfira-a em PDF.',
        ],
    ],
    'admin.reports.show' => [
        'title' => 'Nota',
        'purpose' => 'Uma nota guardada, com os números a partir dos quais foi gerada.',
        'actions' => [
            'Leia a nota.',
            'Transfira-a em PDF.',
            'Volte à lista de notas.',
        ],
    ],
    'admin.roles' => [
        'title' => 'Funções',
        'purpose' => 'As funções da plataforma e o que cada uma pode fazer.',
        'actions' => [
            'Leia as permissões de cada função.',
            'Abra uma função para alterar as suas permissões.',
            'Crie uma nova função.',
        ],
    ],
    'admin.roles.create' => [
        'title' => 'Nova função',
        'purpose' => 'Cria uma função com o seu próprio conjunto de permissões.',
        'actions' => [
            'Dê um nome à função.',
            'Assinale as permissões que lhe correspondem.',
            'Guarde a função.',
        ],
    ],
    'admin.roles.edit' => [
        'title' => 'Editar função',
        'purpose' => 'Altera o que uma função pode fazer; os outros administradores são notificados.',
        'actions' => [
            'Assinale ou desmarque permissões.',
            'Guarde a alteração.',
            'Verifique no ecrã de utilizadores quem tem esta função.',
        ],
    ],
    'admin.samples' => [
        'title' => 'Dados de demonstração',
        'purpose' => 'Os dados de demonstração que ainda estão na base de dados, tabela a tabela, antes de serem removidos para a entrada em funcionamento.',
        'actions' => [
            'Leia quantas linhas de demonstração tem cada tabela.',
            'Verifique quais os registos ainda assinalados como dados de demonstração.',
            'Peça à equipa técnica que execute a remoção quando for seguro fazê-lo.',
        ],
    ],
    'admin.settings' => [
        'title' => 'Definições',
        'purpose' => 'Definições gerais da plataforma, como os contactos, o correio eletrónico e as contas nas redes sociais.',
        'actions' => [
            'Altere uma definição.',
            'Verifique se o envio de correio eletrónico está ativado.',
            'Guarde as definições.',
        ],
    ],
    'admin.social' => [
        'title' => 'Publicações nas redes sociais',
        'purpose' => 'As publicações das redes sociais apresentadas no sítio público.',
        'actions' => [
            'Adicione uma publicação.',
            'Abra uma publicação para a editar.',
            'Envie uma publicação para aprovação ou publique-a.',
        ],
    ],
    'admin.social.create' => [
        'title' => 'Nova publicação nas redes sociais',
        'purpose' => 'Adiciona uma publicação das redes sociais a apresentar no sítio público.',
        'actions' => [
            'Cole o endereço da publicação.',
            'Redija a legenda.',
            'Guarde-a, ou envie-a para aprovação.',
        ],
    ],
    'admin.social.edit' => [
        'title' => 'Editar publicação nas redes sociais',
        'purpose' => 'Altera uma publicação das redes sociais apresentada no sítio público.',
        'actions' => [
            'Altere a legenda ou o endereço.',
            'Guarde a alteração.',
            'Elimine a publicação.',
        ],
    ],
    'admin.submissions' => [
        'title' => 'Declarações anuais',
        'purpose' => 'Os relatórios dos Estados-membros e o seu estado: rascunho, apresentado, validado ou devolvido.',
        'actions' => [
            'Filtre os relatórios por Estado-membro, período ou estado.',
            'Abra um relatório apresentado para o rever.',
            'Veja quais os Estados-membros que ainda não apresentaram o relatório.',
        ],
    ],
    'admin.submissions.show' => [
        'title' => 'Rever um relatório',
        'purpose' => 'O relatório de um Estado-membro, indicador a indicador, com comentários sobre os campos.',
        'actions' => [
            'Leia cada valor e a respetiva fonte.',
            'Comente um campo que precisa de ser alterado.',
            'Valide o relatório, ou devolva-o ao Estado-membro.',
        ],
    ],
    'admin.translations' => [
        'title' => 'Traduções',
        'purpose' => 'Os conteúdos a que falta o texto em francês ou em português.',
        'actions' => [
            'Filtre por tipo de conteúdo ou por idioma.',
            'Abra um elemento para o traduzir.',
            'Pré-visualize uma tradução antes de a guardar.',
        ],
    ],
    'admin.translations.edit' => [
        'title' => 'Traduzir',
        'purpose' => 'O texto em inglês ao lado do texto em francês ou em português, campo a campo.',
        'actions' => [
            'Escreva ou corrija a tradução de cada campo.',
            'Pré-visualize a página traduzida.',
            'Guarde a tradução.',
        ],
    ],
    'admin.translations.preview' => [
        'title' => 'Pré-visualização da tradução',
        'purpose' => 'Mostra o elemento traduzido tal como um leitor desse idioma o verá.',
        'actions' => [
            'Leia o elemento traduzido.',
            'Verifique a paginação com o texto mais longo.',
            'Volte à tradução para a corrigir.',
        ],
    ],
    'admin.users' => [
        'title' => 'Utilizadores',
        'purpose' => 'As contas da plataforma, com as suas funções, Estados-membros e estado.',
        'actions' => [
            'Convide um novo utilizador.',
            'Altere a função ou o Estado-membro de um utilizador.',
            'Suspenda ou reative uma conta.',
        ],
    ],
    'admin.users.form' => [
        'title' => 'Convidar um utilizador',
        'purpose' => 'Cria uma conta e regista o convite destinado ao seu titular.',
        'actions' => [
            'Introduza o nome e o endereço de correio eletrónico.',
            'Escolha a função e o Estado-membro.',
            'Envie o convite.',
        ],
    ],

    /* Member area ------------------------------------------------------------- */

    'extranet.dashboard' => [
        'title' => 'Painel da área de membros',
        'purpose' => 'A sua página inicial na área de membros: os seus comités, pastas, relatórios e o que mudou.',
        'actions' => [
            'Abra um comité ou uma pasta.',
            'Veja o estado do relatório do seu Estado-membro.',
            'Leia os últimos anúncios e notificações.',
        ],
    ],
    'extranet.folders' => [
        'title' => 'Pastas',
        'purpose' => 'As pastas de documentos que pode abrir, por comité e por Estado-membro.',
        'actions' => [
            'Abra uma pasta para ver os seus documentos.',
            'Veja o nível de acesso de cada pasta.',
            'Pesquise um documento em todas as suas pastas.',
        ],
    ],
    'extranet.folder' => [
        'title' => 'Pasta',
        'purpose' => 'Os documentos de uma pasta, ao nível de acesso que a sua função e o seu lugar permitem.',
        'actions' => [
            'Abra ou transfira um documento.',
            'Carregue um documento, se a sua função o permitir.',
            'Volte a todas as pastas.',
        ],
    ],
    'extranet.committee' => [
        'title' => 'Espaço do comité',
        'purpose' => 'Os membros, documentos e reuniões de um comité.',
        'actions' => [
            'Leia os documentos mais recentes do comité.',
            'Veja as suas próximas reuniões.',
            'Veja os Estados-membros que nele têm lugar.',
        ],
    ],
    'extranet.calendar' => [
        'title' => 'Calendário',
        'purpose' => 'As reuniões dos seus comités e da região, mês a mês.',
        'actions' => [
            'Passe de um mês para outro.',
            'Abra uma reunião para ver os pormenores.',
            'Adicione o calendário à sua própria aplicação de calendário.',
        ],
    ],
    'extranet.search' => [
        'title' => 'Pesquisar na área de membros',
        'purpose' => 'Encontra documentos, pastas e registos da área de membros que pode abrir.',
        'actions' => [
            'Escreva uma palavra ou uma referência.',
            'Restrinja os resultados por tipo.',
            'Abra um resultado.',
        ],
    ],
    'announcements' => [
        'title' => 'Anúncios',
        'purpose' => 'Avisos do Secretariado aos membros.',
        'actions' => [
            'Abra um anúncio para o ler.',
            'Veja a que setor ou comité se destina.',
            'Volte ao seu painel.',
        ],
    ],
    'announcements.show' => [
        'title' => 'Anúncio',
        'purpose' => 'Um aviso do Secretariado.',
        'actions' => [
            'Leia o anúncio.',
            'Abra os documentos para os quais remete.',
            'Volte a todos os anúncios.',
        ],
    ],
    'authority' => [
        'title' => 'A sua autoridade',
        'purpose' => 'A ficha do regulador do seu Estado-membro tal como o público a vê, e uma forma de propor correções.',
        'actions' => [
            'Verifique a ficha apresentada ao público.',
            'Proponha uma correção a um campo.',
            'Envie a proposta ao Secretariado.',
        ],
    ],
    'completeness' => [
        'title' => 'Completude dos dados',
        'purpose' => 'Que Estados-membros comunicaram que indicadores, período a período.',
        'actions' => [
            'Leia a matriz de Estados-membros e indicadores.',
            'Escolha um período.',
            'Abra a versão em tabela dos valores.',
        ],
    ],
    'contributions' => [
        'title' => 'As suas contribuições',
        'purpose' => 'Instrumentos nacionais que o seu Estado-membro entregou, ou está a preparar, para o registo harmonizado.',
        'actions' => [
            'Inicie uma nova contribuição.',
            'Abra um rascunho para o continuar.',
            'Veja a decisão do Secretariado sobre cada uma.',
        ],
    ],
    'contributions.create' => [
        'title' => 'Nova contribuição',
        'purpose' => 'Descreve um instrumento nacional para o registo harmonizado.',
        'actions' => [
            'Introduza a referência, o tipo, o setor e o estado.',
            'Redija o título e o resumo.',
            'Guarde-a como rascunho.',
        ],
    ],
    'contributions.edit' => [
        'title' => 'Editar contribuição',
        'purpose' => 'Altera uma contribuição e envia-a ao Secretariado.',
        'actions' => [
            'Corrija os dados e anexe o documento.',
            'Guarde o rascunho.',
            'Submeta-a ao Secretariado para validação.',
        ],
    ],
    'directory' => [
        'title' => 'Diretório de membros',
        'purpose' => 'Contactos nos catorze reguladores que aceitaram figurar na lista, reservado aos membros.',
        'actions' => [
            'Pesquise por nome, autoridade ou setor.',
            'Adicione um contacto da sua autoridade.',
            'Corrija ou remova um contacto pelo qual é responsável.',
        ],
    ],
    'indicators.reference' => [
        'title' => 'Definições dos indicadores',
        'purpose' => 'O que significa cada indicador do relatório e como o comunicar.',
        'actions' => [
            'Leia a definição e a unidade de um indicador.',
            'Filtre por categoria.',
            'Vá ao seu relatório.',
        ],
    ],
    'register' => [
        'title' => 'Registo harmonizado',
        'purpose' => 'Os instrumentos regulamentares dos catorze Estados-membros, que todos os membros podem consultar.',
        'actions' => [
            'Pesquise e filtre por Estado-membro, setor ou tipo.',
            'Abra um instrumento.',
            'Compare instrumentos entre Estados-membros.',
        ],
    ],
    'register.show' => [
        'title' => 'Instrumento',
        'purpose' => 'Um instrumento do registo harmonizado, com os seus documentos e instrumentos relacionados.',
        'actions' => [
            'Leia os dados do instrumento.',
            'Abra os seus documentos.',
            'Abra um instrumento relacionado.',
        ],
    ],
    'submissions' => [
        'title' => 'Declaração anual',
        'purpose' => 'As declarações anuais do seu Estado-membro, período a período, e o seu estado.',
        'actions' => [
            'Submeta uma nova declaração para um período.',
            'Abra uma declaração em rascunho ou devolvida para a completar.',
            'Exporte uma declaração em CSV ou JSON.',
        ],
    ],
    'submissions.edit' => [
        'title' => 'Declaração anual, período, Estado-membro',
        'purpose' => 'Uma declaração anual, indicador a indicador, com os eventuais comentários do Secretariado.',
        'actions' => [
            'Introduza ou corrija cada valor com a respetiva fonte.',
            'Leia os comentários do Secretariado sobre os campos devolvidos.',
            'Submeta a declaração para validação.',
        ],
    ],
    'submissions.import' => [
        'title' => 'Importar um relatório',
        'purpose' => 'Preenche um relatório a partir de uma folha de cálculo, em dois passos: verificação e, depois, confirmação.',
        'actions' => [
            'Transfira o modelo e preencha-o.',
            'Carregue o ficheiro e leia o resultado da verificação.',
            'Confirme a importação, que grava tudo ou nada.',
        ],
    ],
];
