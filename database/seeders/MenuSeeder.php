<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * The header and footer menus as records.
 *
 * Seeded from what the config file and the footer template held before 9
 * September 2026, so the site looks identical and the Secretariat can now
 * change any of it without a deploy.
 *
 * NOT is_sample. This is the site's own structure, exactly as the pages and
 * their blocks are, and the go-live purge must not remove the navigation.
 * That distinction was learned once already on this project, when a purge
 * took the homepage's blocks and returned a 404.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // purge(), not delete(): a mass delete fires no model events, so
        // the translations would be orphaned every time this seeder ran.
        MenuItem::query()->purge();

        $header = [
            ['route' => 'home', 'key' => 'home'],
            ['route' => 'about', 'key' => 'about'],
            ['route' => 'committees', 'key' => 'committees'],
            ['route' => 'members', 'key' => 'members'],
            ['route' => 'publications', 'key' => 'publications'],
            ['route' => 'newsroom', 'key' => 'newsroom'],
            ['route' => 'events', 'key' => 'events'],
            ['route' => 'comparator', 'key' => 'comparator'],
            ['route' => 'contact', 'key' => 'contact'],
        ];

        foreach ($header as $position => $item) {
            $this->item('header', null, $position, 'route', $item['route'], $this->navLabels($item['key']));
        }

        // The footer's four link columns, which were written into the Blade
        // template. Screen 02 draws them as tracked-caps headings with
        // links beneath, and the heading is the one carve-out from sentence
        // case on this platform.
        // The same structure config/navigation.php holds as the floor,
        // read rather than retyped so the two cannot drift.
        $columns = config('navigation.footer', []);

        foreach ($columns as $position => $column) {
            $parent = $this->item('footer', null, $position, 'route', null, $this->labels($column['heading']));

            foreach ($column['links'] as $childPosition => $link) {
                if (isset($link['url'])) {
                    // A brand name is the same in every language; a section
                    // link is not.
                    $labels = isset($link['literal'])
                        ? ['en' => $link['literal'], 'fr' => $link['literal'], 'pt' => $link['literal']]
                        : $this->labels($link['label']);

                    $this->item('footer', $parent->id, $childPosition, 'url', $link['url'], $labels);

                    continue;
                }

                $this->item('footer', $parent->id, $childPosition, 'route', $link['route'], $this->labels($link['label']));
            }
        }
    }

    /** @param array<string, string> $labels */
    private function item(string $menu, ?int $parent, int $position, string $type, ?string $destination, array $labels): MenuItem
    {
        $item = MenuItem::create([
            'menu' => $menu,
            'parent_id' => $parent,
            'position' => $position,
            'destination_type' => $type,
            'destination' => $destination,
            'is_visible' => true,
            'is_sample' => false,
        ]);

        foreach ($labels as $locale => $label) {
            $item->setTranslations($locale, ['label' => $label]);
        }

        return $item;
    }

    /**
     * The labels the lang files already carry, lifted into records.
     *
     * Read from the translation files rather than retyped, so the menu says
     * exactly what it said before and a typo cannot creep in during the
     * move.
     *
     * @return array<string, string>
     */
    private function navLabels(string $key): array
    {
        return $this->labels('nav.'.$key);
    }

    /** @return array<string, string> */
    private function labels(string $key): array
    {
        $labels = [];

        foreach (['en', 'fr', 'pt'] as $locale) {
            $labels[$locale] = __($key, [], $locale);
        }

        return $labels;
    }
}
