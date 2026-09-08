<?php

namespace Database\Seeders;

use App\Enums\BlockType;
use App\Models\Page;
use App\Models\PageBlock;
use App\Support\Locales;
use Illuminate\Database\Seeder;

/**
 * The homepage, as a page record with its blocks.
 *
 * Not a Blade template. The whole point of the block library is that this
 * page is data: an editor reorders, hides and re-words it from the back
 * office without a deploy.
 *
 * Every string is the approved copy from .design/screens/02-homepage.dc.html,
 * in all three languages, taken from that file's own dictionaries rather
 * than retyped. Nothing here is drafted by the build.
 *
 * The page is flagged is_sample so it is stripped with everything else
 * before go-live and replaced by whatever the Secretariat approves. The
 * copy is approved; the arrangement is a proposal.
 */
class HomepageSeeder extends Seeder
{
    /** @return array<string, array<string, string|null>> */
    private function approved(): array
    {
        return [
            'en' => [
                'eyebrow' => 'Communications Regulators of Southern Africa',
                'headline' => 'Harmonising ICT and postal regulation across Southern Africa.',
                'subtext' => 'CRASA brings together the national regulatory authorities of 14 SADC member states to align frameworks, share regulatory practice, and strengthen the region\'s communications and postal sectors.',
                'primaryAction' => 'Explore the library',
                'secondaryAction' => 'About CRASA',
                'fact1' => 'Member regulators',
                'fact2' => 'Established',
                'fact3' => 'Citizens served',
                'fact4' => 'Sectors regulated',
                'whatWeDo' => 'What we do',
                'pillarsTitle' => 'Four lines of work, one regional framework',
                'pillar1t' => 'Harmonised frameworks',
                'pillar1b' => 'Model laws, guidelines and recommendations that member states adopt into national regulation, so a licence condition in one market is recognisable in another.',
                'pillar2t' => 'Spectrum and infrastructure',
                'pillar2b' => 'Coordinated allocation and assignment across borders, including the regional spectrum allocation plan and the IMT 2020 deployment roadmap.',
                'pillar3t' => 'Postal and logistics reform',
                'pillar3b' => 'Universal service obligation, quality of service monitoring and postal financial inclusion, delivered with the UPU.',
                'pillar4t' => 'Capacity and consumer protection',
                'pillar4b' => 'Training for regulator staff, and consumer rights instruments that give users the same protections across the region.',
                'compEyebrow' => 'Regulatory comparator',
                'compTitle' => 'Compare regulation across member states',
                'compBody' => 'Twelve indicators across spectrum management, roaming and mobile, and postal services. Select up to four member states and see them side by side, with a source and a date on every value.',
                'compAction' => 'Open the comparator',
                'pubEyebrow' => 'Regulatory library',
                'pubTitle' => 'Latest guidelines and frameworks',
                'pubAll' => 'All 35 publications',
                'newsEyebrow' => 'Newsroom',
                'newsTitle' => 'Latest updates',
                'newsAll' => 'All updates',
                'membersEyebrow' => 'Member regulators',
                'membersTitle' => 'Fourteen national authorities',
                'membersBody' => 'CRASA\'s membership covers the communications and postal regulators of 14 SADC member states, from Angola to Zimbabwe.',
                'membersAll' => 'View all members',
                'followUs' => 'Follow us',
                'eventsEyebrow' => 'Events',
                'eventsTitle' => 'Upcoming',
            ],
            'fr' => [
                'eyebrow' => 'Régulateurs des communications d\'Afrique australe',
                'headline' => 'Harmoniser la réglementation des TIC et des services postaux en Afrique australe.',
                'subtext' => 'La CRASA réunit les autorités de régulation nationales de 14 États membres de la SADC afin d\'aligner les cadres réglementaires, de partager les pratiques de régulation et de renforcer les secteurs des communications et des services postaux de la région.',
                'primaryAction' => 'Explorer la bibliothèque',
                'secondaryAction' => 'À propos de la CRASA',
                'fact1' => 'Régulateurs membres',
                'fact2' => 'Création',
                'fact3' => 'Citoyens desservis',
                'fact4' => 'Secteurs réglementés',
                'whatWeDo' => 'Ce que nous faisons',
                'pillarsTitle' => 'Quatre axes de travail, un cadre régional',
                'pillar1t' => 'Cadres harmonisés',
                'pillar1b' => 'Lois types, lignes directrices et recommandations que les États membres transposent dans leur réglementation nationale, afin qu\'une condition de licence dans un marché soit reconnaissable dans un autre.',
                'pillar2t' => 'Spectre et infrastructures',
                'pillar2b' => 'Attribution et assignation coordonnées entre les frontières, y compris le plan régional d\'attribution du spectre et la feuille de route de déploiement IMT 2020.',
                'pillar3t' => 'Réforme postale et logistique',
                'pillar3b' => 'Obligation de service universel, suivi de la qualité de service et inclusion financière postale, mis en œuvre avec l\'UPU.',
                'pillar4t' => 'Capacités et protection des consommateurs',
                'pillar4b' => 'Formation du personnel des régulateurs et instruments de protection des consommateurs qui garantissent les mêmes droits dans toute la région.',
                'compEyebrow' => 'Comparateur réglementaire',
                'compTitle' => 'Comparer la réglementation entre les États membres',
                'compBody' => 'Douze indicateurs répartis entre la gestion du spectre, l\'itinérance et le mobile, et les services postaux. Sélectionnez jusqu\'à quatre États membres et comparez-les, avec une source et une date pour chaque valeur.',
                'compAction' => 'Ouvrir le comparateur',
                'pubEyebrow' => 'Bibliothèque réglementaire',
                'pubTitle' => 'Dernières lignes directrices et cadres',
                'pubAll' => 'Les 35 publications',
                'newsEyebrow' => 'Salle de presse',
                'newsTitle' => 'Dernières actualités',
                'newsAll' => 'Toutes les actualités',
                'membersEyebrow' => 'Régulateurs membres',
                'membersTitle' => 'Quatorze autorités nationales',
                'membersBody' => 'Les membres de la CRASA regroupent les régulateurs des communications et des services postaux de 14 États membres de la SADC, de l\'Angola au Zimbabwe.',
                'membersAll' => 'Voir tous les membres',
                'followUs' => 'Nous suivre',
                'eventsEyebrow' => 'Événements',
                'eventsTitle' => 'À venir',
            ],
            'pt' => [
                'eyebrow' => 'Reguladores das comunicações da África Austral',
                'headline' => 'Harmonizar a regulamentação das TIC e dos serviços postais na África Austral.',
                'subtext' => 'A CRASA reúne as autoridades reguladoras nacionais de 14 Estados membros da SADC para alinhar quadros regulatórios, partilhar práticas de regulação e reforçar os setores das comunicações e dos serviços postais da região.',
                'primaryAction' => 'Explorar a biblioteca',
                'secondaryAction' => 'Sobre a CRASA',
                'fact1' => 'Reguladores membros',
                'fact2' => 'Fundação',
                'fact3' => 'Cidadãos servidos',
                'fact4' => 'Setores regulados',
                'whatWeDo' => 'O que fazemos',
                'pillarsTitle' => 'Quatro linhas de trabalho, um quadro regional',
                'pillar1t' => 'Quadros harmonizados',
                'pillar1b' => 'Leis-modelo, diretrizes e recomendações que os Estados membros transpõem para a regulamentação nacional, para que uma condição de licenciamento num mercado seja reconhecível noutro.',
                'pillar2t' => 'Espectro e infraestruturas',
                'pillar2b' => 'Atribuição e consignação coordenadas entre fronteiras, incluindo o plano regional de atribuição de espectro e o roteiro de implantação IMT 2020.',
                'pillar3t' => 'Reforma postal e logística',
                'pillar3b' => 'Obrigação de serviço universal, monitorização da qualidade de serviço e inclusão financeira postal, em conjunto com a UPU.',
                'pillar4t' => 'Capacitação e proteção do consumidor',
                'pillar4b' => 'Formação para o pessoal dos reguladores e instrumentos de direitos do consumidor que garantem as mesmas proteções em toda a região.',
                'compEyebrow' => 'Comparador regulatório',
                'compTitle' => 'Comparar a regulamentação entre os Estados membros',
                'compBody' => 'Doze indicadores em gestão do espectro, roaming e móvel, e serviços postais. Selecione até quatro Estados membros e compare-os lado a lado, com fonte e data em cada valor.',
                'compAction' => 'Abrir o comparador',
                'pubEyebrow' => 'Biblioteca regulatória',
                'pubTitle' => 'Diretrizes e quadros mais recentes',
                'pubAll' => 'Todas as 35 publicações',
                'newsEyebrow' => 'Sala de imprensa',
                'newsTitle' => 'Atualizações recentes',
                'newsAll' => 'Todas as atualizações',
                'membersEyebrow' => 'Reguladores membros',
                'membersTitle' => 'Catorze autoridades nacionais',
                'membersBody' => 'Os membros da CRASA abrangem os reguladores das comunicações e dos serviços postais de 14 Estados membros da SADC, de Angola ao Zimbabué.',
                'membersAll' => 'Ver todos os membros',
                'followUs' => 'Siga-nos',
                'eventsEyebrow' => 'Eventos',
                'eventsTitle' => 'Próximos',
            ],
        ];
    }

    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['slug' => 'home'],
            [
                'template' => 'landing',
                'status' => 'published',
                'published_at' => now(),
                'is_sample' => true,
            ]
        );

        $t = $this->approved();

        foreach (Locales::SUPPORTED as $locale) {
            $page->setTranslations($locale, [
                'title' => $t[$locale]['headline'],
                'meta_description' => $t[$locale]['subtext'],
            ]);
        }

        $page->blocks()->delete();

        $position = 0;

        // 1. Hero.
        //
        // The image slot is flagged as placeholder. The strongest available
        // photograph is a woman using a phone at a Cape Town market: a good
        // image in the wrong register for a treaty-based intergovernmental
        // body whose readers are regulators and ministry officials. The
        // brief for what should replace it is in docs/QUESTIONS.md.
        $hero = $this->block($page, BlockType::Hero, $position++, [
            'image' => 'hero',
            'cta_url' => '/publications',
        ], fn (string $l) => [
            'headline' => $t[$l]['headline'],
            'subhead' => $t[$l]['subtext'],
            'cta_label' => $t[$l]['primaryAction'],
        ]);

        $hero->update([
            'needs_content' => true,
            'content_note' => 'Hero photography. The current image is consumer register, not '
                .'institutional. Needs delegates in session, a signing, a plenary hall, telecom '
                .'infrastructure at scale or a regulator building. Landscape, 3000px minimum on '
                .'the long edge, subject and interest in the RIGHT two thirds because the type '
                .'sits left. Three images would enable the rotation. See docs/QUESTIONS.md.',
        ]);

        // 2. Key figures. Counts that the platform can stand behind: the
        // membership, the sectors, the founding year. No citizen or
        // subscriber totals, which would need a source CRASA has not given.
        $this->block($page, BlockType::KeyFigures, $position++, [
            'items' => [
                ['value' => '14'],
                ['value' => '1997'],
                ['value' => '3'],
            ],
        ], fn (string $l) => [
            'heading' => $t[$l]['whatWeDo'],
            'item_0_label' => $t[$l]['fact1'],
            'item_1_label' => $t[$l]['fact2'],
            'item_2_label' => $t[$l]['fact4'],
        ]);

        // 3. Pillars. Four lines of work, from the approved screen.
        $this->block($page, BlockType::Pillars, $position++, [
            'columns' => 4,
            'items' => [
                ['icon' => 'scale', 'link' => null],
                ['icon' => 'radio-tower', 'link' => null],
                ['icon' => 'package', 'link' => null],
                ['icon' => 'users', 'link' => null],
            ],
        ], fn (string $l) => [
            'heading' => $t[$l]['pillarsTitle'],
            'item_0_heading' => $t[$l]['pillar1t'],
            'item_0_body' => $t[$l]['pillar1b'],
            'item_1_heading' => $t[$l]['pillar2t'],
            'item_1_body' => $t[$l]['pillar2b'],
            'item_2_heading' => $t[$l]['pillar3t'],
            'item_2_body' => $t[$l]['pillar3b'],
            'item_3_heading' => $t[$l]['pillar4t'],
            'item_3_body' => $t[$l]['pillar4b'],
        ]);

        // 4. Featured comparison. The indicator tables arrive later, so this
        // renders its empty state for now, which is the honest state.
        $this->block($page, BlockType::FeaturedComparison, $position++, [
            'country_ids' => [],
            'chart_type' => 'table',
        ], fn (string $l) => [
            'heading' => $t[$l]['compTitle'],
            'intro' => $t[$l]['compBody'],
        ]);

        // 5. Featured publications.
        $this->block($page, BlockType::FeaturedPublications, $position++, [
            'limit' => 3,
            'filter' => [],
        ], fn (string $l) => ['heading' => $t[$l]['pubTitle']]);

        // 6. Newsroom strip.
        $this->block($page, BlockType::NewsroomStrip, $position++, [
            'limit' => 3,
            'filter' => [],
        ], fn (string $l) => ['heading' => $t[$l]['newsTitle']]);

        // 7. Member grid.
        // 7. Member states. The map leads the section: fourteen countries
        // are the subject, and a map of them says more than fourteen cards.
        $this->block($page, BlockType::MemberGrid, $position++, [
            'show_map' => true,
            'filter' => [],
        ], fn (string $l) => [
            'heading' => $t[$l]['membersTitle'],
            'intro' => $t[$l]['membersBody'],
        ]);

        // 8. Social feed. Links out to CRASA's own accounts, not an embedded
        // widget that would load third-party tracking on every visitor.
        $this->block($page, BlockType::SocialFeed, $position++, [
            'networks' => ['x', 'facebook', 'linkedin'],
        ], fn (string $l) => ['heading' => $t[$l]['followUs']]);
    }

    private function block(Page $page, BlockType $type, int $position, array $settings, callable $text): PageBlock
    {
        $block = PageBlock::create([
            'page_id' => $page->id,
            'block_type' => $type,
            'position' => $position,
            'is_visible' => true,
            'settings' => $settings,
            'is_sample' => true,
        ]);

        foreach (Locales::SUPPORTED as $locale) {
            $block->setTranslations($locale, array_filter($text($locale), fn ($v) => filled($v)));
        }

        return $block;
    }
}
