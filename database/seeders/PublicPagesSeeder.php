<?php

namespace Database\Seeders;

use App\Enums\BlockType;
use App\Models\Page;
use App\Models\PageBlock;
use App\Support\Locales;
use Illuminate\Database\Seeder;

/**
 * About CRASA, Committees and Membership. WS-03. No design screens exist for
 * these; they are built from the foundations components.
 *
 * WHAT IS STATED AS FACT HERE, AND WHY:
 *
 * CRASA is a Specialised Agency of SADC, established in 1997 under the
 * Protocol on Transport, Communications and Meteorology, and its mandate is
 * the harmonisation of the postal and ICT regulatory environment in the SADC
 * region. That sentence appears in the approved design screens in all three
 * languages, so it is client-supplied rather than drafted here. The fourteen
 * member states and the three sectors are in CLAUDE.md.
 *
 * WHAT IS NOT STATED:
 *
 * Individual committee mandates, strategic priorities, governance structure,
 * office holders, meeting cycles and dates. None of that is in any source
 * available to this build, and a plausible sentence about a regulator's
 * mandate is worse than a visible gap. Those blocks are flagged
 * needs_content with a note naming exactly what the Secretariat must supply,
 * and `php artisan content:placeholders` prints the list.
 */
class PublicPagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->about();
        $this->committees();
        $this->membership();
    }

    /* About ---------------------------------------------------------------- */

    private function about(): void
    {
        $page = $this->page('about', [
            'en' => ['title' => 'About CRASA', 'meta_description' => 'CRASA is a Specialised Agency of SADC, established in 1997, harmonising the postal and ICT regulatory environment across fourteen member states.'],
            'fr' => ['title' => 'À propos de la CRASA', 'meta_description' => 'La CRASA est une agence spécialisée de la SADC, créée en 1997, chargée d\'harmoniser l\'environnement réglementaire postal et des TIC dans quatorze États membres.'],
            'pt' => ['title' => 'Sobre a CRASA', 'meta_description' => 'A CRASA é uma Agência Especializada da SADC, criada em 1997, que harmoniza o ambiente regulatório postal e das TIC em catorze Estados membros.'],
        ]);

        $position = 0;

        // Sourceable. Every clause here is in CLAUDE.md or the approved
        // screens, in all three languages.
        $this->block($page, BlockType::RichText, $position++, ['alignment' => 'start'], [
            'en' => [
                'heading' => 'Who we are',
                'body' => "CRASA is a Specialised Agency of the Southern African Development Community, established in 1997 under the Protocol on Transport, Communications and Meteorology.\n\nIts mandate is the harmonisation of the postal and information and communications technologies regulatory environment in the SADC region. Membership is the national regulatory authorities of fourteen member states, from Angola to Zimbabwe, and the work covers three sectors: telecommunications, broadcasting and postal.",
            ],
            'fr' => [
                'heading' => 'Qui nous sommes',
                'body' => "La CRASA est une agence spécialisée de la Communauté de développement de l'Afrique australe, créée en 1997 dans le cadre du Protocole sur les transports, les communications et la météorologie.\n\nSon mandat est l'harmonisation de l'environnement réglementaire postal et des technologies de l'information et de la communication dans la région de la SADC. Elle réunit les autorités de régulation nationales de quatorze États membres, de l'Angola au Zimbabwe, et couvre trois secteurs : les télécommunications, la radiodiffusion et les services postaux.",
            ],
            'pt' => [
                'heading' => 'Quem somos',
                'body' => "A CRASA é uma Agência Especializada da Comunidade de Desenvolvimento da África Austral, criada em 1997 ao abrigo do Protocolo sobre Transportes, Comunicações e Meteorologia.\n\nO seu mandato é a harmonização do ambiente regulatório postal e das tecnologias de informação e comunicação na região da SADC. Reúne as autoridades reguladoras nacionais de catorze Estados membros, de Angola ao Zimbabué, e abrange três setores: telecomunicações, radiodifusão e serviços postais.",
            ],
        ], 'who-we-are');

        // Not sourceable. The governance structure, who holds each office
        // and the meeting cycle are not in any source available to this
        // build. The Executive Committee and the Secretariat get their own
        // sections below because the footer links to each of them
        // separately.
        $this->placeholder(
            $page,
            BlockType::RichText,
            $position++,
            ['alignment' => 'start'],
            'Governance: the structure of the Annual General Meeting and how decisions pass between it, the Executive Committee and the Secretariat. The meeting cycle and the current office holders.',
            [
                'en' => ['heading' => 'Governance'],
                'fr' => ['heading' => 'Gouvernance'],
                'pt' => ['heading' => 'Governação'],
            ],
            'governance'
        );

        $this->placeholder(
            $page,
            BlockType::RichText,
            $position++,
            ['alignment' => 'start'],
            'Executive Committee: how many members it has, how they are elected and for how long, which authorities currently hold the chair and the vice-chair, and what the committee decides rather than recommends.',
            [
                'en' => ['heading' => 'Executive Committee'],
                'fr' => ['heading' => 'Comité exécutif'],
                'pt' => ['heading' => 'Comité Executivo'],
            ],
            'executive-committee'
        );

        $this->placeholder(
            $page,
            BlockType::RichText,
            $position++,
            ['alignment' => 'start'],
            'Secretariat: where it is based, its establishment, the Executive Secretary and the functional roles, and the contact route for each. The contact page carries the general address already; this section is the standing description.',
            [
                'en' => ['heading' => 'Secretariat'],
                'fr' => ['heading' => 'Secrétariat'],
                'pt' => ['heading' => 'Secretariado'],
            ],
            'secretariat'
        );

        // Partner and funder attribution is supplied by the client. See the
        // writing rules in CLAUDE.md: the EU emblem in particular has
        // binding rules on proportion, clear space and wording, and a wrong
        // one on a donor-funded platform is raised formally. Nothing is
        // drafted here, not even a placeholder sentence naming a partner.
        $this->placeholder(
            $page,
            BlockType::RichText,
            $position++,
            ['alignment' => 'start'],
            'Partnerships: which organisations CRASA works with, in what capacity, and the attribution wording and marks each one requires. Client-supplied without exception.',
            [
                'en' => ['heading' => 'Partnerships'],
                'fr' => ['heading' => 'Partenariats'],
                'pt' => ['heading' => 'Parcerias'],
            ],
            'partnerships'
        );

        $this->placeholder(
            $page,
            BlockType::RichText,
            $position++,
            ['alignment' => 'start'],
            'Strategic plan: the current strategic cycle, its priorities and its dates. Screen 04 refers to a 2023 to 2028 cycle but the priorities themselves are not recorded anywhere available to this build.',
            [
                'en' => ['heading' => 'Strategic priorities'],
                'fr' => ['heading' => 'Priorités stratégiques'],
                'pt' => ['heading' => 'Prioridades estratégicas'],
            ],
            'strategic-priorities'
        );

        $this->block($page, BlockType::CallToAction, $position++, ['tone' => 'navy', 'cta_url' => '/members'], [
            'en' => ['headline' => 'Fourteen national regulators, one regional framework', 'cta_label' => 'See the member states'],
            'fr' => ['headline' => 'Quatorze régulateurs nationaux, un cadre régional', 'cta_label' => 'Voir les États membres'],
            'pt' => ['headline' => 'Catorze reguladores nacionais, um quadro regional', 'cta_label' => 'Ver os Estados membros'],
        ]);
    }

    /* Committees ------------------------------------------------------------ */

    private function committees(): void
    {
        $page = $this->page('committees', [
            'en' => ['title' => 'Committees', 'meta_description' => 'The CRASA committees and the member states that sit on them.'],
            'fr' => ['title' => 'Comités', 'meta_description' => 'Les comités de la CRASA et les États membres qui y siègent.'],
            'pt' => ['title' => 'Comités', 'meta_description' => 'Os comités da CRASA e os Estados membros que os integram.'],
        ]);

        $position = 0;

        $this->block($page, BlockType::RichText, $position++, ['alignment' => 'start'], [
            'en' => ['heading' => 'Committees', 'body' => 'CRASA works through committees drawn from the fourteen national regulatory authorities. Each covers a sector or a cross-sector theme.'],
            'fr' => ['heading' => 'Comités', 'body' => "La CRASA travaille par l'intermédiaire de comités composés des quatorze autorités de régulation nationales. Chacun couvre un secteur ou un thème transversal."],
            'pt' => ['heading' => 'Comités', 'body' => 'A CRASA trabalha através de comités constituídos pelas catorze autoridades reguladoras nacionais. Cada um abrange um setor ou um tema transversal.'],
        ]);

        $this->placeholder(
            $page,
            BlockType::Pillars,
            $position++,
            ['columns' => 3, 'items' => [['icon' => 'radio-tower'], ['icon' => 'package'], ['icon' => 'scale']]],
            'Committee mandates: the terms of reference for each of the six committees, who chairs each one, and which member states hold seats. The six names are taken from the approved screens; nothing beyond the names is sourced.',
            [
                'en' => ['heading' => 'What each committee does'],
                'fr' => ['heading' => 'Le rôle de chaque comité'],
                'pt' => ['heading' => 'O papel de cada comité'],
            ]
        );
    }

    /* Membership ------------------------------------------------------------ */

    private function membership(): void
    {
        $page = $this->page('members', [
            'en' => ['title' => 'Members', 'meta_description' => 'The fourteen national regulatory authorities that make up CRASA.'],
            'fr' => ['title' => 'Membres', 'meta_description' => 'Les quatorze autorités de régulation nationales qui composent la CRASA.'],
            'pt' => ['title' => 'Membros', 'meta_description' => 'As catorze autoridades reguladoras nacionais que compõem a CRASA.'],
        ]);

        $position = 0;

        $this->block($page, BlockType::MemberGrid, $position++, ['show_map' => true, 'filter' => []], [
            'en' => ['heading' => 'Fourteen national authorities', 'intro' => "CRASA's membership covers the communications and postal regulators of fourteen SADC member states, from Angola to Zimbabwe."],
            'fr' => ['heading' => 'Quatorze autorités nationales', 'intro' => "La CRASA réunit les régulateurs des communications et des services postaux de quatorze États membres de la SADC, de l'Angola au Zimbabwe."],
            'pt' => ['heading' => 'Catorze autoridades nacionais', 'intro' => 'A CRASA reúne os reguladores das comunicações e dos serviços postais de catorze Estados membros da SADC, de Angola ao Zimbabué.'],
        ]);

        $this->placeholder(
            $page,
            BlockType::RichText,
            $position++,
            ['alignment' => 'start'],
            'Accession dates: when each member state joined CRASA. Also confirmation, in writing, that each authority consents to being listed with its name and mark. Both belong in the D5 documentation.',
            [
                'en' => ['heading' => 'Membership'],
                'fr' => ['heading' => 'Adhésion'],
                'pt' => ['heading' => 'Adesão'],
            ]
        );
    }

    /* Helpers --------------------------------------------------------------- */

    private function page(string $slug, array $translations): Page
    {
        $page = Page::updateOrCreate(
            ['slug' => $slug],
            ['template' => 'standard', 'status' => 'published', 'published_at' => now(), 'is_sample' => true]
        );

        foreach ($translations as $locale => $fields) {
            $page->setTranslations($locale, $fields);
        }

        $page->blocks()->delete();

        return $page;
    }

    private function block(Page $page, BlockType $type, int $position, array $settings, array $text, ?string $anchor = null): PageBlock
    {
        $block = PageBlock::create([
            'page_id' => $page->id,
            'block_type' => $type,
            'position' => $position,
            'anchor' => $anchor,
            'is_visible' => true,
            'settings' => $settings,
            'is_sample' => true,
        ]);

        foreach (Locales::SUPPORTED as $locale) {
            if (isset($text[$locale])) {
                $block->setTranslations($locale, $text[$locale]);
            }
        }

        return $block;
    }

    /**
     * A block whose copy is not yet writable. It carries its heading, so the
     * page structure is real, and an honest note in place of body copy.
     */
    private function placeholder(Page $page, BlockType $type, int $position, array $settings, string $note, array $text, ?string $anchor = null): void
    {
        // Written into the field this block type actually renders. A note
        // in a field the block ignores is a flag nobody ever sees.
        $field = $type->bodyField();

        if ($field !== null) {
            foreach (Locales::SUPPORTED as $locale) {
                $text[$locale][$field] ??= __('pages.awaiting_content', [], $locale);
            }
        }

        $block = $this->block($page, $type, $position, $settings, $text, $anchor);
        $block->update(['needs_content' => true, 'content_note' => $note]);
    }
}
