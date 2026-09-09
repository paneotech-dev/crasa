<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Support\Locales;
use Illuminate\Database\Seeder;

/**
 * The header menu, restructured on 10 September 2026.
 *
 * Nine root items, three of them groups. The legacy site's own structure is
 * what decided which: docs/LEGACY_INVENTORY.md records twenty-six internal
 * links grouped under About, Projects and Publications, and the new site's
 * nine flat links dropped that grouping.
 *
 * PROJECTS IS HIDDEN. The legacy site has current and past projects and
 * this platform has neither, so the group exists, carries its children, and
 * is not drawn until the import lands. That is one toggle in the
 * navigation builder rather than a deploy, which is the point of the menu
 * being records at all.
 *
 * Every label is written in all three languages here. A menu item with no
 * French label renders the English on the French site with a notice, which
 * is right as a fallback and wrong as a starting state for nine items
 * somebody can translate in an afternoon.
 */
class HeaderMenuSeeder extends Seeder
{
    /**
     * label per locale, destination, and children.
     *
     * @var list<array{key: string, type: string, destination: ?string, visible: bool, labels: array<string,string>, children: list<array{key: string, type: string, destination: string, labels: array<string,string>}>}>
     */
    private const STRUCTURE = [
        [
            'key' => 'about', 'type' => 'page', 'destination' => 'about', 'visible' => true,
            'labels' => ['en' => 'About', 'fr' => 'À propos', 'pt' => 'Sobre'],
            'children' => [
                ['key' => 'vision', 'type' => 'url', 'destination' => '/about#vision',
                 'labels' => ['en' => 'Vision, mission and history', 'fr' => 'Vision, mission et histoire', 'pt' => 'Visão, missão e história']],
                ['key' => 'structure', 'type' => 'url', 'destination' => '/about#structure',
                 'labels' => ['en' => 'Structure', 'fr' => 'Structure', 'pt' => 'Estrutura']],
                ['key' => 'secretariat', 'type' => 'url', 'destination' => '/about#secretariat',
                 'labels' => ['en' => 'Secretariat', 'fr' => 'Secrétariat', 'pt' => 'Secretariado']],
            ],
        ],
        [
            'key' => 'members', 'type' => 'route', 'destination' => 'members', 'visible' => true,
            'labels' => ['en' => 'Members', 'fr' => 'Membres', 'pt' => 'Membros'],
            'children' => [],
        ],
        [
            'key' => 'committees', 'type' => 'page', 'destination' => 'committees', 'visible' => true,
            'labels' => ['en' => 'Committees', 'fr' => 'Comités', 'pt' => 'Comités'],
            'children' => [],
        ],
        [
            'key' => 'publications', 'type' => 'route', 'destination' => 'publications', 'visible' => true,
            'labels' => ['en' => 'Publications', 'fr' => 'Publications', 'pt' => 'Publicações'],
            'children' => [
                ['key' => 'annual-reports', 'type' => 'url', 'destination' => '/publications?type[]=annual_report',
                 'labels' => ['en' => 'Annual reports', 'fr' => 'Rapports annuels', 'pt' => 'Relatórios anuais']],
                ['key' => 'guidelines', 'type' => 'url', 'destination' => '/publications?type[]=guideline',
                 'labels' => ['en' => 'Guidelines', 'fr' => 'Lignes directrices', 'pt' => 'Orientações']],
                ['key' => 'studies', 'type' => 'url', 'destination' => '/publications?type[]=study',
                 'labels' => ['en' => 'Studies', 'fr' => 'Études', 'pt' => 'Estudos']],
                ['key' => 'communiques', 'type' => 'url', 'destination' => '/publications?type[]=communique',
                 'labels' => ['en' => 'Communiques', 'fr' => 'Communiqués', 'pt' => 'Comunicados']],
            ],
        ],
        [
            /*
             * Hidden until the legacy import lands. The group and its
             * children are records either way, so showing it is a toggle
             * rather than a deploy.
             */
            'key' => 'projects', 'type' => 'url', 'destination' => null, 'visible' => false,
            'labels' => ['en' => 'Projects', 'fr' => 'Projets', 'pt' => 'Projetos'],
            'children' => [
                ['key' => 'current-projects', 'type' => 'url', 'destination' => '/projects/current',
                 'labels' => ['en' => 'Current projects', 'fr' => 'Projets en cours', 'pt' => 'Projetos em curso']],
                ['key' => 'past-projects', 'type' => 'url', 'destination' => '/projects/past',
                 'labels' => ['en' => 'Past projects', 'fr' => 'Projets achevés', 'pt' => 'Projetos concluídos']],
            ],
        ],
        [
            'key' => 'newsroom', 'type' => 'route', 'destination' => 'newsroom', 'visible' => true,
            'labels' => ['en' => 'Newsroom', 'fr' => 'Actualités', 'pt' => 'Notícias'],
            'children' => [],
        ],
        [
            'key' => 'events', 'type' => 'route', 'destination' => 'events', 'visible' => true,
            'labels' => ['en' => 'Events', 'fr' => 'Réunions', 'pt' => 'Reuniões'],
            'children' => [],
        ],
        [
            'key' => 'comparator', 'type' => 'route', 'destination' => 'comparator', 'visible' => true,
            'labels' => ['en' => 'Comparator', 'fr' => 'Comparateur', 'pt' => 'Comparador'],
            'children' => [],
        ],
        [
            'key' => 'contact', 'type' => 'route', 'destination' => 'contact', 'visible' => true,
            'labels' => ['en' => 'Contact', 'fr' => 'Contact', 'pt' => 'Contacto'],
            'children' => [],
        ],
    ];

    public function run(): void
    {
        MenuItem::where('menu', 'header')->delete();

        $position = 0;

        foreach (self::STRUCTURE as $definition) {
            $parent = $this->item($definition, null, $position++);

            $childPosition = 0;

            foreach ($definition['children'] as $child) {
                $this->item($child + ['visible' => true], $parent, $childPosition++);
            }
        }
    }

    /** @param array<string, mixed> $definition */
    private function item(array $definition, ?MenuItem $parent, int $position): MenuItem
    {
        $item = MenuItem::create([
            'menu' => 'header',
            'parent_id' => $parent?->id,
            'destination_type' => $definition['type'],
            'destination' => $definition['destination'],
            'position' => $position,
            'is_visible' => $definition['visible'],
            'is_sample' => false,
        ]);

        foreach (Locales::SUPPORTED as $locale) {
            $item->setTranslations($locale, ['label' => $definition['labels'][$locale]]);
        }

        return $item;
    }
}