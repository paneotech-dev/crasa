<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Support\Locales;
use App\Support\Navigation;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The header menu, restructured through the navigation builder.
 *
 * Nine root items, three of them groups. Every one of them is a record: the
 * Secretariat can rename, reorder, hide or renest any of it without a
 * deploy, which is the contract's stated core requirement.
 *
 * SCREEN 01 DRAWS NO DROPDOWN. It names --e-2 as the elevation for "Hover,
 * dropdowns, sticky headers" at line 418 and stops there, so the elevation
 * is the screen's and the rest is a disclosed variation in
 * docs/OPEN_ITEMS.md.
 */
class HeaderDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(HeaderMenuSeeder::class);
    }

    private function header(string $locale = 'en'): string
    {
        $html = $this->get('/'.$locale)->assertOk()->getContent();

        return substr($html, 0, strpos($html, '</header>'));
    }

    public function test_a_group_carries_its_children_and_its_own_page(): void
    {
        $header = $this->header();

        foreach (['Vision, mission and history', 'Structure', 'Secretariat'] as $child) {
            $this->assertStringContainsString($child, $header);
        }

        foreach (['Annual reports', 'Guidelines', 'Studies', 'Communiques'] as $child) {
            $this->assertStringContainsString($child, $header);
        }

        // No "All of" row: the group's page is reached from its own link
        // and its filters, not from an extra entry in the drop-down.
        $this->assertStringNotContainsString(__('nav.overview', ['section' => 'About']), $header);
    }

    /**
     * Projects was seeded hidden, with its children as records, so turning
     * it on when the legacy import landed was one toggle in the builder
     * rather than a deploy. This is the state after that toggle, and the
     * toggle itself is still proved: hiding the group takes all three rows
     * off the header without deleting anything.
     */
    public function test_projects_is_drawn_and_hiding_it_is_still_one_toggle(): void
    {
        $projects = MenuItem::where('menu', 'header')
            ->whereHas('translations', fn ($t) => $t->where('value', 'Projects'))
            ->firstOrFail();

        $this->assertTrue($projects->is_visible);
        $this->assertCount(2, $projects->children);

        $header = $this->header();
        $this->assertStringContainsString('Projects', $header);
        $this->assertStringContainsString('Current projects', $header);
        $this->assertStringContainsString('Past projects', $header);

        $projects->update(['is_visible' => false]);

        $hidden = $this->header();
        $this->assertStringNotContainsString('Current projects', $hidden);
        $this->assertStringNotContainsString('Past projects', $hidden);

        // Hidden, not deleted. The records are still there to turn back on.
        $this->assertCount(2, $projects->fresh()->children);
    }

    /* The floor ----------------------------------------------------------- */

    /* Three languages ------------------------------------------------------ */

    public function test_every_label_is_written_in_all_three_languages(): void
    {
        foreach (MenuItem::where('menu', 'header')->with('translations')->get() as $item) {
            $this->assertSame(
                [],
                $item->missingLocales(),
                'A menu item has no label in '.implode(', ', $item->missingLocales())
            );
        }

        $this->assertStringContainsString('À propos', $this->header('fr'));
        $this->assertStringContainsString('Rapports annuels', $this->header('fr'));
        $this->assertStringContainsString('Relatórios anuais', $this->header('pt'));
    }

    /** Nothing is hardcoded: with no records the header falls back flat. */
    public function test_the_header_is_records_and_falls_back_to_config(): void
    {
        MenuItem::where('menu', 'header')->delete();

        $items = Navigation::primary();

        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertSame([], $item['children'], 'The config fallback is flat.');
        }

        $this->get('/en')->assertOk();
    }

}
