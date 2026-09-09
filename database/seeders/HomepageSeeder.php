<?php

namespace Database\Seeders;

use App\Enums\BlockType;
use App\Models\Country;
use App\Models\Indicator;
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
 * The page itself is NOT flagged is_sample. It is the site's homepage: the
 * Secretariat edits it, it does not receive it fresh after go-live. What
 * gets stripped is the demonstration records the blocks display, not the
 * page or the blocks. An earlier version of the strip command removed them
 * and the first thing its test found was the homepage returning 404.
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
                'slide1Eyebrow' => 'Regulatory comparator',
                'slide1Headline' => 'The same question, asked of fourteen regulators.',
                'slide1Subtext' => 'Licence conditions, tariffs and access obligations differ across the region. The comparator sets any indicator side by side, member state against member state, with the source and the reporting period against every figure.',
                'slide1Action' => 'Open the comparator',
                'slide2Eyebrow' => 'From the library',
                'slide2Headline' => 'Every document comes from this platform.',
                'slide2Subtext' => 'Model laws, guidelines, communiques and annual reports, held by CRASA and downloaded from CRASA. No redirect to Google Drive or SharePoint anywhere.',
                'slide2Action' => 'Browse the library',
                'bandEyebrow' => 'One register, fourteen regulators',
                'bandStatement' => 'A licence condition written in Gaborone should be recognisable in Maputo. That is the whole of the harmonisation mandate, and it only works if every member state reports against the same indicators.',
                'bandAction' => 'See what each state has reported',
                'bandGridLabel' => 'Indicators reported, by member state',
                'bandGridNote' => 'Counted from the register itself, not from a return. A zero means that state has filed nothing yet against the active indicator set, which is the gap the harmonisation programme exists to close.',
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
                'compBody' => 'Pick two member states and an indicator and read them here. Every value carries its source and the period it covers. The full tool takes four member states at once.',
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
                'slide1Eyebrow' => 'Comparateur réglementaire',
                'slide1Headline' => 'La même question, posée à quatorze régulateurs.',
                'slide1Subtext' => 'Les conditions de licence, les tarifs et les obligations d\'accès diffèrent dans la région. Le comparateur place n\'importe quel indicateur côte à côte, État membre par État membre, avec la source et la période de référence pour chaque chiffre.',
                'slide1Action' => 'Ouvrir le comparateur',
                'slide2Eyebrow' => 'De la bibliothèque',
                'slide2Headline' => 'Chaque document provient de cette plateforme.',
                'slide2Subtext' => 'Lois types, lignes directrices, communiqués et rapports annuels, conservés par la CRASA et téléchargés depuis la CRASA. Aucune redirection vers Google Drive ou SharePoint.',
                'slide2Action' => 'Parcourir la bibliothèque',
                'bandEyebrow' => 'Un registre, quatorze régulateurs',
                'bandStatement' => 'Une condition de licence rédigée à Gaborone devrait être reconnaissable à Maputo. C\'est là tout le mandat d\'harmonisation, et il ne fonctionne que si chaque État membre rend compte des mêmes indicateurs.',
                'bandAction' => 'Voir ce que chaque État a déclaré',
                'bandGridLabel' => 'Indicateurs déclarés, par État membre',
                'bandGridNote' => 'Comptés à partir du registre lui-même, et non d\'une déclaration. Un zéro signifie que cet État n\'a encore rien déposé pour l\'ensemble des indicateurs actifs, ce qui est précisément l\'écart que le programme d\'harmonisation vise à combler.',
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
                'compBody' => 'Choisissez deux États membres et un indicateur, et lisez-les ici. Chaque valeur porte sa source et la période couverte. L\'outil complet accepte quatre États membres à la fois.',
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
                'slide1Eyebrow' => 'Comparador regulatório',
                'slide1Headline' => 'A mesma pergunta, feita a catorze reguladores.',
                'slide1Subtext' => 'As condições de licenciamento, as tarifas e as obrigações de acesso diferem em toda a região. O comparador coloca qualquer indicador lado a lado, Estado membro a Estado membro, com a fonte e o período de referência para cada valor.',
                'slide1Action' => 'Abrir o comparador',
                'slide2Eyebrow' => 'Da biblioteca',
                'slide2Headline' => 'Cada documento provém desta plataforma.',
                'slide2Subtext' => 'Leis-modelo, linhas de orientação, comunicados e relatórios anuais, conservados pela CRASA e descarregados a partir da CRASA. Sem qualquer redirecionamento para o Google Drive ou o SharePoint.',
                'slide2Action' => 'Consultar a biblioteca',
                'bandEyebrow' => 'Um registo, catorze reguladores',
                'bandStatement' => 'Uma condição de licenciamento redigida em Gaborone deve ser reconhecível em Maputo. É nisso que consiste todo o mandato de harmonização, e só funciona se cada Estado membro reportar os mesmos indicadores.',
                'bandAction' => 'Ver o que cada Estado reportou',
                'bandGridLabel' => 'Indicadores reportados, por Estado membro',
                'bandGridNote' => 'Contados a partir do próprio registo, e não de uma declaração. Um zero significa que esse Estado ainda nada apresentou face ao conjunto de indicadores ativos, que é precisamente a lacuna que o programa de harmonização visa colmatar.',
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
                'compBody' => 'Escolha dois Estados membros e um indicador e leia-os aqui. Cada valor traz a sua fonte e o período abrangido. A ferramenta completa aceita quatro Estados membros de cada vez.',
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
                // The homepage is structure, not demonstration content. Its
                // blocks carry the copy and the strip removes those.
                'is_sample' => false,
            ]
        );

        $t = $this->approved();

        foreach (Locales::SUPPORTED as $locale) {
            $page->setTranslations($locale, [
                'title' => $t[$locale]['headline'],
                'meta_description' => $t[$locale]['subtext'],
            ]);
        }

        // purge(), not delete(). See HasTranslations::scopePurge.
        $page->blocks()->purge();

        $position = 0;

        // 1. Hero.
        //
        // The image slot is flagged as placeholder. The strongest available
        // photograph is a woman using a phone at a Cape Town market: a good
        // image in the wrong register for a treaty-based intergovernmental
        // body whose readers are regulators and ministry officials. The
        // brief for what should replace it is in docs/QUESTIONS.md.
        $hero = $this->block($page, BlockType::Hero, $position++, [
            /*
             * The boardroom photograph, batch two. Delegates around a glass
             * table, one taking notes.
             *
             * This was null for a day, deliberately: the library had no
             * institutional subject and a hero on the wrong picture is a
             * claim about what CRASA is, made on the first screen to a
             * regulator. This one is the right register and the slot is
             * simply switched on.
             *
             * Screen 02's photo-slide treatment then applies as drawn: the
             * image at inset:0, the 140deg colour blend over it, then the
             * 90deg navy gradient at .94, .82 and .34. The delegates sit
             * under the near-solid end behind the headline and the right
             * third reads as texture. Not flipped.
             */
            'image' => 'hero',
            'cta_url' => '/publications',
            'panel' => 'figures',
            /*
             * Two more slides, and neither needs a photograph.
             *
             * Screen 02 draws three, and the rotation has been built and
             * idle since it was written because the only way to fill it was
             * to repeat one image. These fill it with content instead: the
             * comparator, which is the platform's reason to exist and sat
             * four sections down the page, and the most recent publication,
             * which is the first thing a regulator arriving here wants.
             *
             * Each drops itself if its panel has no data to draw, so a
             * stripped database serves one slide and a banner rather than
             * three slides and two empty boxes.
             */
            /*
             * Every slide is a photo slide, so the three read as one band
             * rather than as a photograph followed by two data panels.
             * Screen 02's photo treatment carries all of them: the image at
             * inset:0, the 140deg colour blend, the .94/.82/.34 scrim. Per
             * the screen a photo slide runs its copy the full width and
             * carries no second column, so the figures live on the navy-mid
             * band immediately below.
             *
             * Slide three is the weakest photograph in the library and is
             * here because it is the only remaining real one. Recorded in
             * CLIENT_REQUESTS.md: a third institutional photograph is
             * wanted, a plenary or a signing, and slide three should be
             * looked at again when it arrives.
             */
            'rotation_items' => [
                ['cta_url' => '/comparator', 'panel' => 'comparison', 'image' => 'data'],
                ['cta_url' => '/publications', 'panel' => 'publication', 'image' => 'institutional-fallback'],
            ],
        ], fn (string $l) => [
            // Sentence case, per the tracked-caps ruling. The screen's copy
            // is client-supplied and already in all three languages.
            'eyebrow' => $t[$l]['eyebrow'],
            'headline' => $t[$l]['headline'],
            'subhead' => $t[$l]['subtext'],
            'cta_label' => $t[$l]['primaryAction'],

            'slide_0_eyebrow' => $t[$l]['slide1Eyebrow'],
            'slide_0_headline' => $t[$l]['slide1Headline'],
            'slide_0_subhead' => $t[$l]['slide1Subtext'],
            'slide_0_cta_label' => $t[$l]['slide1Action'],

            'slide_1_eyebrow' => $t[$l]['slide2Eyebrow'],
            'slide_1_headline' => $t[$l]['slide2Headline'],
            'slide_1_subhead' => $t[$l]['slide2Subtext'],
            'slide_1_cta_label' => $t[$l]['slide2Action'],
        ]);

        $hero->update([
            // The photograph has arrived; the note stays because the
            // rotation's other two slides still carry no image and CRASA's
            // own meeting photography would be worth more than this.
            'needs_content' => true,
            // Rewritten 9 September: the note still described an image the
            // banner no longer carries, which is exactly the kind of drift
            // a placeholder list exists to prevent and had itself acquired.
            'content_note' => 'Hero photography. The banner runs with no photograph at all, on '
                .'navy and typography, because the supplied library has no institutional subject '
                .'and a wrong one on the first screen is a false claim about what CRASA is. '
                .'Needs delegates in plenary, a signing ceremony, a speaker at a podium with '
                .'member state flags, institutional architecture, telecommunications '
                .'infrastructure at scale, or a network operations centre. Landscape, 3000px '
                .'minimum on the long edge, subject and interest in the RIGHT two thirds because '
                .'the type sits left. Three images would enable the rotation. CRASA own AGM '
                .'photography is worth more than anything purchasable. See '
                .'docs/CLIENT_REQUESTS.md section 1.',
        ]);

        // 2. Key figures. Counts that the platform can stand behind: the
        // membership, the sectors, the founding year. No citizen or
        // subscriber totals, which would need a source CRASA has not given.
        //
        // Each one goes where it points. Fourteen member states are a page
        // on this platform; so is the founding protocol; so are the three
        // sectors the committees are organised by. A figure a reader cannot
        // follow is a fact about the site rather than a way into it.
        $this->block($page, BlockType::KeyFigures, $position++, [
            'items' => [
                ['value' => '14', 'link' => 'members'],
                ['value' => '1997', 'link' => 'about'],
                ['value' => '3', 'link' => 'committees'],
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
        /*
         * Pinned rather than left to pick itself, so the homepage shows the
         * same four member states every time and the link through to the
         * comparator reproduces exactly what is on the page. Comoros is one
         * of the four deliberately: it has not reported this category, and
         * the homepage should show what a missing figure looks like rather
         * than only ever showing four filled cells.
         */
        $featured = Indicator::where('slug', 'like', '%mobile-cellular%')->first()
            ?? Indicator::orderBy('display_order')->first();

        $this->block($page, BlockType::FeaturedComparison, $position++, [
            'country_ids' => Country::whereIn('iso_code', ['bw', 'za', 'zm', 'km'])->pluck('id')->all(),
            'indicator_id' => $featured?->id,
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

        /*
         * 6. The statement band, screen 02 line 376.
         *
         * The one place on the homepage where CRASA says something in its
         * own voice rather than listing what it holds. It sits between the
         * publications and the newsroom because that is where the screen
         * puts it, and because without it those two sections share the
         * cream page ground and fuse into one run.
         *
         * The claim has to pass the substitution test: replace CRASA with
         * EACO or ARICEA and it must stop being true. Fourteen states, one
         * register, three sectors does.
         */
        $this->block($page, BlockType::StatementBand, $position++, [
            'cta_url' => '/comparator',
        ], fn (string $l) => [
            'eyebrow' => $t[$l]['bandEyebrow'],
            'statement' => $t[$l]['bandStatement'],
            'cta_label' => $t[$l]['bandAction'],
            'grid_label' => $t[$l]['bandGridLabel'],
            'grid_note' => $t[$l]['bandGridNote'],
        ]);

        // 7. Newsroom strip.
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
        ]);

        foreach (Locales::SUPPORTED as $locale) {
            $block->setTranslations($locale, array_filter($text($locale), fn ($v) => filled($v)));
        }

        return $block;
    }
}
