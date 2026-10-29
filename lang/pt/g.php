<?php

/*
 * Stream G: the accessibility statement (G5). The three files carry the
 * same keys; AccessibilityStatementController renders them.
 */
return [
    'slug' => 'acessibilidade',
    'statement' => [
        'title' => 'Declaração de acessibilidade',
        'eyebrow' => 'Acessibilidade',
        'lead' => 'Esta plataforma foi construída para que todos a possam ler, pesquisar e usar: com teclado, leitor de ecrã, ampliador, telemóvel ou ligação lenta, em inglês, francês e português.',
        'meta_description' => 'Como a plataforma da CRASA cumpre as WCAG 2.1 nível AA, o que ainda não cumpre e como comunicar uma barreira.',
        'fig_standard' => 'WCAG 2.1 nível AA',
        'fig_standard_label' => 'Norma adotada',
        'fig_review' => '29 de outubro de 2026',
        'fig_review_label' => 'Última revisão',
        'toc' => 'Nesta página',
        'sections' => [
            'standard' => [
                'title' => 'A norma que seguimos',
                'body' => [
                    'Esta plataforma foi construída para cumprir as Diretrizes de Acessibilidade para Conteúdo Web (WCAG) 2.1, nível AA, o nível adotado pela maioria dos organismos públicos. Abrange o sítio público, a área de membros e a administração, em inglês, francês e português.',
                    'O nível A é o mínimo, sem o qual algumas pessoas não conseguem usar uma página. O nível AA, o nosso objetivo, elimina as barreiras mais comuns para pessoas com baixa visão, daltonismo ou deficiência motora, auditiva ou cognitiva. O nível AAA é o mais elevado e não é exigido a um sítio inteiro; cumprimos parte dele sempre que possível.',
                ],
            ],
            'done' => [
                'title' => 'O que fizemos',
                'items' => [
                    'Cada página começa com uma ligação "Saltar para o conteúdo", tem um único título principal, títulos por ordem e o idioma declarado.',
                    'Tudo funciona com teclado: menus, seletor de idioma, diapositivos, separadores, filtros, mapas, comparador, formulários e caixas de diálogo. Um contorno de foco visível mostra onde está.',
                    'O texto tem um contraste de pelo menos 4,5:1 com o fundo, e os controlos 3:1. Os valores são medidos por um script, não avaliados a olho.',
                    'As imagens têm uma descrição em texto, ou são marcadas como decorativas quando nada acrescentam. Os gráficos e mapas são acompanhados de uma tabela ou lista com a mesma informação.',
                    'A cor nunca é o único sinal: os estados têm uma palavra e um ícone, e as entradas do calendário uma letra.',
                    'Os formulários têm etiquetas por cima dos campos, os campos obrigatórios são indicados por extenso e os erros aparecem escritos junto ao campo e são lidos pelos leitores de ecrã.',
                    'As páginas adaptam-se a um ecrã de 320 píxeis de largura sem deslocação lateral e continuam utilizáveis com zoom a 200%.',
                    'O conteúdo em movimento pode ser parado, e nada se move se o seu dispositivo pedir movimento reduzido.',
                ],
            ],
            'options' => [
                'title' => 'Opções de visualização',
                'body' => [
                    'O botão Opções de visualização, no topo de cada página, permite mudar o tamanho do texto (quatro níveis), aumentar o espaçamento entre linhas, usar o espaçamento de leitura (letras e palavras mais espaçadas, sem itálico, o que ajuda muitos leitores com dislexia), passar para alto contraste, sublinhar todas as ligações e parar as animações.',
                    'As suas escolhas ficam guardadas neste navegador e aplicam-se a todas as páginas até as repor. Nada é enviado à CRASA.',
                ],
            ],
            'tested' => [
                'title' => 'Como testamos',
                'items' => [
                    'Verificações automáticas com axe-core em cada página pública e em cada tipo de ecrã da administração, nas três línguas.',
                    'Um percurso completo por teclado em cada tipo de página, com zoom a 200% e a 320 píxeis de largura.',
                    'Verificações com os leitores de ecrã NVDA no Windows e VoiceOver no macOS e no iPhone.',
                    'Validação HTML dos modelos de página.',
                ],
            ],
            'limits' => [
                'title' => 'Exceções e limitações conhecidas',
                'intro' => 'Estes elementos ainda não cumprem totalmente a norma. Cada um tem a data em que foi identificado e a data do passo seguinte.',
                'items' => [
                    [
                        'what' => 'Documentos PDF antigos',
                        'detail' => 'Algumas diretrizes e relatórios importados do sítio anterior não estão etiquetados para leitores de ecrã. Peça-nos e enviaremos o conteúdo num formato acessível.',
                        'found' => '29 de outubro de 2026',
                        'next' => 'Documentos mais descarregados revistos até 31 de janeiro de 2027',
                    ],
                    [
                        'what' => 'Textos em francês e português',
                        'detail' => 'Parte dos textos em francês e português foi traduzida automaticamente e está a ser revista pela CRASA. A versão inglesa está a um clique no seletor de idioma.',
                        'found' => '1 de outubro de 2026',
                        'next' => 'Revisão em curso, próximo ponto a 31 de janeiro de 2027',
                    ],
                    [
                        'what' => 'Publicações de redes sociais incorporadas',
                        'detail' => 'As publicações do X, Facebook e LinkedIn são apresentadas por essas plataformas, que gerem a sua acessibilidade. Cada publicação tem também uma ligação simples para a abrir.',
                        'found' => '29 de outubro de 2026',
                        'next' => 'Verificadas em cada revisão',
                    ],
                    [
                        'what' => 'Descrições de imagens antigas',
                        'detail' => 'Algumas fotografias importadas do sítio anterior têm descrições curtas ou genéricas. Uma imagem nova não pode ser publicada sem descrição.',
                        'found' => '29 de outubro de 2026',
                        'next' => 'Imagens das notícias revistas até 31 de janeiro de 2027',
                    ],
                    [
                        'what' => 'Duas cores alteradas para cumprir a norma (1.4.11)',
                        'detail' => 'Duas cores do desenho aprovado não atingiam o contraste de 3:1 exigido pelo critério 1.4.11 para o limite de um controlo. Os valores entregues diferem do desenho para o cumprir.',
                        'found' => '9 de setembro de 2026',
                        'next' => 'Resolvido nos valores entregues',
                    ],
                    [
                        'what' => 'Texto desativado (1.4.3)',
                        'detail' => 'A cor do texto desativado fica abaixo de 4,5:1 de propósito: o critério 1.4.3 isenta o texto de um controlo inativo.',
                        'found' => '9 de setembro de 2026',
                        'next' => 'Nenhuma alteração necessária',
                    ],
                ],
                'col_what' => 'Elemento',
                'col_found' => 'Identificado',
                'col_next' => 'Passo seguinte',
            ],
            'report' => [
                'title' => 'Comunicar uma barreira ou pedir outro formato',
                'body' => [
                    'Se algo nesta plataforma lhe for difícil ou impossível de usar, indique ao Secretariado a página e o que aconteceu. Se precisar de um documento noutro formato, diga qual.',
                ],
                'email' => 'Correio eletrónico',
                'phone' => 'Telefone',
                'form' => 'Usar o formulário de contacto',
                'reply' => 'Respondemos por escrito e dizemos o que será corrigido e quando.',
            ],
            'review' => [
                'title' => 'Revisão desta declaração',
                'body' => [
                    'Esta declaração foi preparada a 29 de outubro de 2026 após uma verificação completa da plataforma e revista pela última vez a 29 de outubro de 2026. É revista após cada alteração importante e pelo menos uma vez por ano. A próxima revisão está prevista para 31 de janeiro de 2027.',
                ],
            ],
        ],
        'cta_title' => 'Algo não funciona para si?',
        'cta_text' => 'Indique ao Secretariado a página e o que aconteceu.',
        'cta_button' => 'Contactar o Secretariado',
    ],
];
