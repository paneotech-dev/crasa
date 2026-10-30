<?php

namespace Database\Seeders;

use App\Enums\BlockType;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Database\Seeder;

/**
 * "Partner with us" (30 October 2026): a page record with blocks, like
 * About, so the Secretariat edits its text in the back office.
 *
 * Insert only. A page with this slug that already exists, including a
 * deleted one, is left exactly as it is, so running this again (or on the
 * live site after an editor has changed the text) changes nothing. The
 * footer link is added once, at the end of the About column.
 *
 * The text states no figures, partners or commitments: who CRASA is comes
 * from the About page, and the rest describes kinds of work in general
 * terms. The page is marked for client review so the back office lists it
 * until CRASA confirms the wording.
 */
class PartnerPageSeeder extends Seeder
{
    public const SLUG = 'partner-with-us';

    public function run(): void
    {
        $this->page();
        $this->footerLink();
    }

    private function page(): void
    {
        if (Page::withTrashed()->where('slug', self::SLUG)->exists()) {
            return;
        }

        $page = Page::create([
            'slug' => self::SLUG,
            'template' => 'standard',
            'status' => 'published',
            'published_at' => now(),
            'is_sample' => false,
        ]);
        $page->forceFill(['needs_client_review' => true])->save();

        foreach (self::PAGE as $locale => $fields) {
            $page->setTranslations($locale, $fields);
        }

        foreach (self::BLOCKS as $position => [$anchor, $type, $settings, $texts]) {
            $block = PageBlock::create([
                'page_id' => $page->id,
                'block_type' => $type,
                'position' => $position,
                'anchor' => $anchor,
                'is_visible' => true,
                'settings' => $settings,
                'is_sample' => false,
                'needs_content' => false,
                'content_note' => 'Drafted text: CRASA to confirm the wording.',
            ]);

            foreach ($texts as $locale => $fields) {
                $block->setTranslations($locale, $fields);
            }
        }
    }

    /** The footer's About column, if the site has one and the link is not there yet. */
    private function footerLink(): void
    {
        if (MenuItem::inMenu('footer')->where('destination_type', 'page')->where('destination', self::SLUG)->exists()) {
            return;
        }

        $column = MenuItem::inMenu('footer')->whereNull('parent_id')
            ->whereHas('children', fn ($q) => $q->where('destination', 'about'))
            ->first();

        if ($column === null) {
            return;
        }

        $item = MenuItem::create([
            'menu' => 'footer',
            'parent_id' => $column->id,
            'position' => (int) MenuItem::where('parent_id', $column->id)->max('position') + 1,
            'destination_type' => 'page',
            'destination' => self::SLUG,
            'is_visible' => true,
            'is_sample' => false,
        ]);

        foreach (self::MENU_LABEL as $locale => $label) {
            $item->setTranslations($locale, ['label' => $label]);
        }
    }

    public const MENU_LABEL = [
        'en' => 'Partner with us',
        'fr' => 'Devenir partenaire',
        'pt' => 'Seja nosso parceiro',
    ];

    private const PAGE = [
        'en' => [
            'title' => 'Partner with us',
            'heading' => 'Partner with CRASA',
            'standfirst' => 'CRASA is a Specialised Agency of the Southern African Development Community that harmonises the regulation of telecommunications, broadcasting and postal services among the regulators of its member states.',
            'meta_description' => 'How organisations can work with CRASA on the regulation of ICT, broadcasting and postal services in the SADC region, and how to contact the Secretariat.',
        ],
        'fr' => [
            'title' => 'Devenir partenaire',
            'heading' => 'Devenir partenaire de la CRASA',
            'standfirst' => 'La CRASA est une agence spécialisée de la Communauté de développement de l\'Afrique australe qui harmonise la réglementation des télécommunications, de la radiodiffusion et des services postaux entre les régulateurs de ses États membres.',
            'meta_description' => 'Comment les organisations peuvent collaborer avec la CRASA sur la réglementation des TIC, de la radiodiffusion et des services postaux dans la région de la SADC, et comment contacter le Secrétariat.',
        ],
        'pt' => [
            'title' => 'Seja nosso parceiro',
            'heading' => 'Seja parceiro da CRASA',
            'standfirst' => 'A CRASA é uma Agência Especializada da Comunidade de Desenvolvimento da África Austral que harmoniza a regulamentação das telecomunicações, da radiodifusão e dos serviços postais entre os reguladores dos seus Estados membros.',
            'meta_description' => 'Como as organizações podem colaborar com a CRASA na regulamentação das TIC, da radiodifusão e dos serviços postais na região da SADC, e como contactar o Secretariado.',
        ],
    ];

    /*
     * Each body is plain text read by StructuredText: a short line with no
     * closing punctuation, first in its paragraph, is a card's heading.
     */
    private const BLOCKS = [
        ['why-partner', BlockType::RichText, ['alignment' => 'start'], [
            'en' => [
                'heading' => 'Why partner with CRASA',
                'body' => "Reach to the regulators of the region\nCRASA brings together the national regulatory authorities of the SADC member states, so work done with CRASA reaches regulators across the region through one body.\n\nRegional harmonisation\nCRASA works towards harmonised regulation of ICT, broadcasting and postal services in the region, through model guidelines and recommendations for its members.\n\nCapacity building\nCRASA supports the development of knowledge and skills among the staff of its member regulators.\n\nResearch and data\nCRASA gathers and shares regulatory information and studies on the sectors it covers.",
            ],
            'fr' => [
                'heading' => 'Pourquoi devenir partenaire de la CRASA',
                'body' => "Accès aux régulateurs de la région\nLa CRASA réunit les autorités de régulation nationales des États membres de la SADC : un travail mené avec la CRASA atteint les régulateurs de toute la région par l'intermédiaire d'un seul organisme.\n\nHarmonisation régionale\nLa CRASA œuvre à l'harmonisation de la réglementation des TIC, de la radiodiffusion et des services postaux dans la région, au moyen de lignes directrices types et de recommandations destinées à ses membres.\n\nRenforcement des capacités\nLa CRASA contribue au développement des connaissances et des compétences du personnel des régulateurs membres.\n\nRecherche et données\nLa CRASA recueille et partage des informations réglementaires et des études sur les secteurs qu'elle couvre.",
            ],
            'pt' => [
                'heading' => 'Porquê ser parceiro da CRASA',
                'body' => "Acesso aos reguladores da região\nA CRASA reúne as autoridades reguladoras nacionais dos Estados membros da SADC: um trabalho realizado com a CRASA chega aos reguladores de toda a região através de um único organismo.\n\nHarmonização regional\nA CRASA trabalha para a harmonização da regulamentação das TIC, da radiodifusão e dos serviços postais na região, através de diretrizes-modelo e recomendações destinadas aos seus membros.\n\nReforço de capacidades\nA CRASA apoia o desenvolvimento de conhecimentos e competências do pessoal dos reguladores membros.\n\nInvestigação e dados\nA CRASA recolhe e partilha informação regulamentar e estudos sobre os setores que abrange.",
            ],
        ]],
        ['ways-to-partner', BlockType::RichText, ['alignment' => 'start'], [
            'en' => [
                'heading' => 'Ways to partner',
                'body' => "Each partnership is discussed with the Secretariat case by case. The areas below describe the kinds of work that can be considered.\n\nProgramme and project partnerships\nWorking with CRASA on regional programmes and projects related to its mandate.\n\nCapacity building and training\nSupporting training, workshops and the exchange of knowledge for the staff of member regulators.\n\nResearch and data\nContributing to studies, data collection and the sharing of regulatory information in the region.\n\nEvents and meetings\nSupporting or taking part in CRASA meetings, workshops and regional forums.\n\nTechnical assistance\nProviding expertise on regulatory questions that member regulators and the Secretariat are working on.",
            ],
            'fr' => [
                'heading' => 'Formes de partenariat',
                'body' => "Chaque partenariat est examiné au cas par cas avec le Secrétariat. Les domaines ci-dessous décrivent les types de travaux qui peuvent être envisagés.\n\nPartenariats de programmes et de projets\nCollaborer avec la CRASA à des programmes et projets régionaux liés à son mandat.\n\nRenforcement des capacités et formation\nSoutenir des formations, des ateliers et l'échange de connaissances pour le personnel des régulateurs membres.\n\nRecherche et données\nContribuer à des études, à la collecte de données et au partage d'informations réglementaires dans la région.\n\nÉvénements et réunions\nSoutenir les réunions, ateliers et forums régionaux de la CRASA ou y participer.\n\nAssistance technique\nApporter une expertise sur les questions réglementaires traitées par les régulateurs membres et le Secrétariat.",
            ],
            'pt' => [
                'heading' => 'Formas de parceria',
                'body' => "Cada parceria é analisada caso a caso com o Secretariado. As áreas abaixo descrevem os tipos de trabalho que podem ser considerados.\n\nParcerias em programas e projetos\nColaborar com a CRASA em programas e projetos regionais relacionados com o seu mandato.\n\nReforço de capacidades e formação\nApoiar formações, workshops e o intercâmbio de conhecimentos para o pessoal dos reguladores membros.\n\nInvestigação e dados\nContribuir para estudos, para a recolha de dados e para a partilha de informação regulamentar na região.\n\nEventos e reuniões\nApoiar as reuniões, workshops e fóruns regionais da CRASA ou participar neles.\n\nAssistência técnica\nDisponibilizar conhecimentos especializados sobre as questões regulamentares em que os reguladores membros e o Secretariado trabalham.",
            ],
        ]],
        ['how-it-works', BlockType::RichText, ['alignment' => 'start'], [
            'en' => [
                'heading' => 'How it works',
                'body' => "Get in touch\nWrite to the Secretariat through the contact form, with a short description of your organisation and the work you have in mind.\n\nScope together\nThe Secretariat discusses the proposal with you and how it relates to CRASA's mandate and work.\n\nAgree and deliver\nWhere both sides decide to go ahead, the arrangements are agreed and the work is carried out together.",
            ],
            'fr' => [
                'heading' => 'Comment procéder',
                'body' => "Prendre contact\nÉcrivez au Secrétariat au moyen du formulaire de contact, en présentant brièvement votre organisation et le travail envisagé.\n\nDéfinir ensemble le cadre\nLe Secrétariat examine la proposition avec vous et la manière dont elle s'inscrit dans le mandat et les travaux de la CRASA.\n\nConvenir et mettre en œuvre\nLorsque les deux parties décident de poursuivre, les modalités sont convenues et le travail est mené conjointement.",
            ],
            'pt' => [
                'heading' => 'Como funciona',
                'body' => "Entrar em contacto\nEscreva ao Secretariado através do formulário de contacto, com uma breve descrição da sua organização e do trabalho que propõe.\n\nDefinir o âmbito em conjunto\nO Secretariado analisa a proposta consigo e a forma como se enquadra no mandato e no trabalho da CRASA.\n\nAcordar e executar\nQuando ambas as partes decidem avançar, as modalidades são acordadas e o trabalho é realizado em conjunto.",
            ],
        ]],
        [null, BlockType::CallToAction, ['tone' => 'navy', 'cta_url' => '/contact'], [
            'en' => [
                'headline' => 'Discuss a partnership with the Secretariat',
                'body' => 'Use the contact form to describe your organisation and the partnership you have in mind.',
                'cta_label' => 'Contact the Secretariat',
            ],
            'fr' => [
                'headline' => 'Discuter d\'un partenariat avec le Secrétariat',
                'body' => 'Utilisez le formulaire de contact pour présenter votre organisation et le partenariat envisagé.',
                'cta_label' => 'Contacter le Secrétariat',
            ],
            'pt' => [
                'headline' => 'Discutir uma parceria com o Secretariado',
                'body' => 'Utilize o formulário de contacto para apresentar a sua organização e a parceria que pretende.',
                'cta_label' => 'Contactar o Secretariado',
            ],
        ]],
    ];
}
