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

    public function test_the_header_carries_the_structure_that_was_specified(): void
    {
        $header = $this->header();

        foreach (['About', 'Members', 'Committees', 'Publications', 'Newsroom', 'Events', 'Comparator', 'Contact'] as $label) {
            $this->assertStringContainsString('>'.$label, $header, $label.' is not in the header.');
        }

        // Two groups drawn, because Projects is hidden.
        $this->assertSame(2, substr_count($header, 'data-nav-group'));
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

        // About is a page as well as a heading, so the group offers it.
        $this->assertStringContainsString(__('nav.overview', ['section' => 'About']), $header);
    }

    /**
     * Projects is hidden until the legacy import lands. The group and its
     * children are records either way, so showing it is one toggle in the
     * builder rather than a deploy.
     */
    public function test_projects_is_a_record_and_is_not_drawn(): void
    {
        $projects = MenuItem::where('menu', 'header')
            ->whereHas('translations', fn ($t) => $t->where('value', 'Projects'))
            ->firstOrFail();

        $this->assertFalse($projects->is_visible);
        $this->assertCount(2, $projects->children);

        $this->assertStringNotContainsString('Projects', $this->header());
        $this->assertStringNotContainsString('Current projects', $this->header());

        // One toggle, and it is there.
        $projects->update(['is_visible' => true]);

        $header = $this->header();
        $this->assertStringContainsString('Projects', $header);
        $this->assertStringContainsString('Current projects', $header);
        $this->assertStringContainsString('Past projects', $header);
    }

    /** A group with no page of its own is a button, not a link to nowhere. */
    public function test_a_group_with_no_destination_is_not_a_link(): void
    {
        MenuItem::where('menu', 'header')
            ->whereHas('translations', fn ($t) => $t->where('value', 'Projects'))
            ->firstOrFail()
            ->update(['is_visible' => true]);

        $header = $this->header();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*class="c-nav__link c-nav__toggle"[^>]*>\s*Projects/s',
            $header
        );
        $this->assertStringNotContainsString('<a class="c-nav__link" href="">Projects', $header);
    }

    /* The floor ----------------------------------------------------------- */

    /**
     * Every child link is in the document. The rules that close a menu are
     * under html.has-js and the toggle is not drawn without it, so with no
     * script the header is an open list rather than a hidden one.
     */
    public function test_no_navigation_is_hidden_behind_a_script(): void
    {
        $header = $this->header();

        // In the markup, not built by script.
        $this->assertSame(9, substr_count($header, 'class="c-nav__child"'));

        $css = file_get_contents(public_path('css/chrome.css'));
        $this->assertStringContainsString('html.has-js .c-nav__menu {', $css);
        $this->assertStringContainsString('html:not(.has-js) .c-nav__toggle { display: none; }', $css);

        // And the closed state is a hidden attribute the script sets, not a
        // display rule that applies before it runs.
        $js = file_get_contents(public_path('js/nav.js'));
        $this->assertStringContainsString('menu.hidden = true;', $js);
        $this->assertStringContainsString("event.key !== 'Escape'", $js);
    }

    public function test_the_toggle_announces_its_state_and_what_it_controls(): void
    {
        $header = $this->header();

        $this->assertMatchesRegularExpression('/aria-expanded="false"/', $header);
        $this->assertMatchesRegularExpression('/aria-controls="nav-menu-\d+"/', $header);

        // Every menu it names exists.
        preg_match_all('/aria-controls="([^"]+)"/', $header, $controls);

        foreach ($controls[1] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $header, $id.' is controlled and does not exist.');
        }
    }

    /** The drawer nests the same groups, indented. */
    public function test_the_drawer_carries_the_second_level(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();
        $drawer = substr($html, strpos($html, 'class="c-drawer"'));

        $this->assertStringContainsString('c-drawer__link--child', $drawer);
        $this->assertStringContainsString('Annual reports', $drawer);
        $this->assertStringContainsString('Structure', $drawer);
    }

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

    public function test_a_current_child_marks_its_group(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get('/'.$locale.'/publications')->assertOk();
        }

        $html = $this->get('/en/publications')->assertOk()->getContent();
        $header = substr($html, 0, strpos($html, '</header>'));

        $this->assertMatchesRegularExpression(
            '/class="c-nav__link c-nav__toggle"[^>]*aria-current="page"/s',
            $header
        );
    }
}
