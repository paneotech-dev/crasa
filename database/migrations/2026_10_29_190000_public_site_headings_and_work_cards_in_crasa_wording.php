<?php

use App\Models\PageBlock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Item 8f, the polish pass: headings in CRASA's words and the four "Our
 * work" cards in CRASA's own wording, from crasa.org (About, the current
 * projects) and the guideline it publishes. No heading keeps the "X, one Y"
 * pattern. Only text still exactly as it was seeded is changed, so an
 * edit the Secretariat has made in the back office is left alone; down()
 * puts the seeded text back on the same terms.
 */
return new class extends Migration
{
    /** [page slug, block type, field, [locale => [seeded, new]]] */
    private const TEXT = [
        ['home', 'pillars', 'heading', [
            'en' => [
                'Four lines of work, one regional framework',
                'Our work',
            ],
            'fr' => [
                'Quatre axes de travail, un cadre régional',
                'Notre travail',
            ],
            'pt' => [
                'Quatro linhas de trabalho, um quadro regional',
                'O nosso trabalho',
            ],
        ]],
        ['home', 'pillars', 'item_0_body', [
            'en' => [
                'Model laws, guidelines and recommendations that member states adopt into national regulation, so a licence condition in one market is recognisable in another.',
                'CRASA provides the model guidelines, recommendations and position papers on various regulatory thematic areas. The model policies and regulatory guidelines, as approved by the CRASA AGM, are submitted to the meeting of Ministers responsible for Telecommunications, Postal and ICT for endorsement.',
            ],
            'fr' => [
                'Lois types, lignes directrices et recommandations que les États membres transposent dans leur réglementation nationale, afin qu\'une condition de licence dans un marché soit reconnaissable dans un autre.',
                'La CRASA élabore les lignes directrices types, les recommandations et les documents de position sur divers domaines thématiques de la réglementation. Les politiques types et les lignes directrices réglementaires, approuvées par l\'AGA de la CRASA, sont soumises à la réunion des ministres chargés des télécommunications, de la poste et des TIC pour adoption.',
            ],
            'pt' => [
                'Leis-modelo, diretrizes e recomendações que os Estados membros transpõem para a regulamentação nacional, para que uma condição de licenciamento num mercado seja reconhecível noutro.',
                'A CRASA elabora as diretrizes-modelo, as recomendações e os documentos de posição sobre vários domínios temáticos da regulação. As políticas-modelo e as diretrizes regulamentares, aprovadas pela AGA da CRASA, são submetidas à reunião dos Ministros responsáveis pelas Telecomunicações, Correios e TIC para aprovação.',
            ],
        ]],
        ['home', 'pillars', 'item_1_body', [
            'en' => [
                'Coordinated allocation and assignment across borders, including the regional spectrum allocation plan and the IMT 2020 deployment roadmap.',
                'CRASA is implementing the IMT 2020 including 5G Technologies Deployment Roadmap for SADC, in partnership with the ITU, ICNIRP, GSMA, Ericsson and Detecon.',
            ],
            'fr' => [
                'Attribution et assignation coordonnées entre les frontières, y compris le plan régional d\'attribution du spectre et la feuille de route de déploiement IMT 2020.',
                'La CRASA met en œuvre la feuille de route de déploiement de l\'IMT 2020, 5G comprise, pour la SADC, avec l\'UIT, l\'ICNIRP, la GSMA, Ericsson et Detecon.',
            ],
            'pt' => [
                'Atribuição e consignação coordenadas entre fronteiras, incluindo o plano regional de atribuição de espectro e o roteiro de implantação IMT 2020.',
                'A CRASA executa o Roteiro de Implantação das IMT 2020, incluindo o 5G, para a SADC, com a UIT, a ICNIRP, a GSMA, Ericsson e Detecon.',
            ],
        ]],
        ['home', 'pillars', 'item_2_body', [
            'en' => [
                'Universal service obligation, quality of service monitoring and postal financial inclusion, delivered with the UPU.',
                'An intra-regional Postal Quality of Service Project with the Universal Postal Union, using the UPU\'s Global Monitoring System at the International Offices of Exchange of Designated Postal Operators.',
            ],
            'fr' => [
                'Obligation de service universel, suivi de la qualité de service et inclusion financière postale, mis en œuvre avec l\'UPU.',
                'Un projet intrarégional sur la qualité de service postale avec l\'Union postale universelle, qui utilise le Système mondial de suivi de l\'UPU dans les bureaux d\'échange internationaux des opérateurs postaux désignés.',
            ],
            'pt' => [
                'Obrigação de serviço universal, monitorização da qualidade de serviço e inclusão financeira postal, em conjunto com a UPU.',
                'Um projeto intrarregional de qualidade do serviço postal com a União Postal Universal, que utiliza o Sistema Global de Monitorização da UPU nas estações internacionais de permuta dos operadores postais designados.',
            ],
        ]],
        ['home', 'pillars', 'item_3_body', [
            'en' => [
                'Training for regulator staff, and consumer rights instruments that give users the same protections across the region.',
                'Consumer Rights and Protection Guidelines for the Electronic Communications Sector in SADC, Edition 2021.',
            ],
            'fr' => [
                'Formation du personnel des régulateurs et instruments de protection des consommateurs qui garantissent les mêmes droits dans toute la région.',
                'Lignes directrices sur les droits et la protection des consommateurs dans le secteur des communications électroniques de la SADC, édition 2021.',
            ],
            'pt' => [
                'Formação para o pessoal dos reguladores e instrumentos de direitos do consumidor que garantem as mesmas proteções em toda a região.',
                'Diretrizes de Direitos e Proteção do Consumidor para o Setor das Comunicações Eletrónicas na SADC, edição de 2021.',
            ],
        ]],
        ['members', 'member_grid', 'heading', [
            'en' => ['Fourteen national authorities', 'Member regulators'],
            'fr' => ['Quatorze autorités nationales', 'Régulateurs membres'],
            'pt' => ['Catorze autoridades nacionais', 'Reguladores membros'],
        ]],
        ['home', 'member_grid', 'heading', [
            'en' => [
                'Fourteen national authorities',
                'Member regulators',
            ],
            'fr' => [
                'Quatorze autorités nationales',
                'Régulateurs membres',
            ],
            'pt' => [
                'Catorze autoridades nacionais',
                'Reguladores membros',
            ],
        ]],
        ['home', 'statement_band', 'eyebrow', [
            'en' => [
                'One register, fourteen regulators',
                'The harmonisation mandate',
            ],
            'fr' => [
                'Un registre, quatorze régulateurs',
                'Le mandat d\'harmonisation',
            ],
            'pt' => [
                'Um registo, catorze reguladores',
                'O mandato de harmonização',
            ],
        ]],
        ['about', 'call_to_action', 'headline', [
            'en' => [
                'Fourteen national regulators, one regional framework',
                'ICT and postal regulators from the SADC region',
            ],
            'fr' => [
                'Quatorze régulateurs nationaux, un cadre régional',
                'Régulateurs des TIC et de la poste de la région SADC',
            ],
            'pt' => [
                'Catorze reguladores nacionais, um quadro regional',
                'Reguladores das TIC e dos correios da região da SADC',
            ],
        ]],
    ];

    public function up(): void
    {
        $this->swap(0, 1);
    }

    public function down(): void
    {
        $this->swap(1, 0);
    }

    private function swap(int $from, int $to): void
    {
        foreach (self::TEXT as [$page, $type, $field, $locales]) {
            $blocks = DB::table('page_blocks')->join('pages', 'pages.id', '=', 'page_blocks.page_id')
                ->where('pages.slug', $page)->where('page_blocks.block_type', $type)->pluck('page_blocks.id');

            foreach ($blocks as $block) {
                foreach ($locales as $locale => $pair) {
                    DB::table('translations')->where([
                        'translatable_type' => PageBlock::class, 'translatable_id' => $block, 'field' => $field, 'locale' => $locale,
                    ])->where('value', $pair[$from])->update(['value' => $pair[$to], 'updated_at' => now()]);
                }
            }
        }
    }
};
