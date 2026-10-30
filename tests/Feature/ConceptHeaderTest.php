<?php

namespace Tests\Feature;

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
 * The Concept 3 header (S2, S3), sitemap band and footer
 * (29 October 2026). Replaces the assertions on the earlier dropdown
 * header (c-nav, c-drawer) in HeaderDropdownTest, HeaderBreakpointTest,
 * HeaderOneRowTest and HeaderAndTypeTest.
 */
class ConceptHeaderTest extends TestCase
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

    private function page(string $path = '/en/about'): string
    {
        return $this->get($path)->assertOk()->getContent();
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, $from.' not found.');

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }

    public function test_the_nav_row_carries_every_record_and_a_panel_per_section(): void
    {
        $nav = $this->between($this->page(), 'data-k-nav>', '</nav>');

        foreach (['About', 'Members', 'Committees', 'Publications', 'Newsroom', 'Events', 'Comparator', 'Contact'] as $label) {
            $this->assertStringContainsString('>'.$label, $nav, $label.' is not in the header.');
        }

        // About, Publications, Projects, Newsroom, Events, Comparator.
        $this->assertGreaterThanOrEqual(5, substr_count($nav, 'data-k-mega'));
        $this->assertStringContainsString('Annual reports', $nav);
        $this->assertStringContainsString('Regional frameworks to adopt', $nav);
    }

    public function test_a_section_with_a_page_keeps_its_link_and_a_chevron_opens_the_panel(): void
    {
        $nav = $this->between($this->page(), 'data-k-nav>', '</nav>');

        $this->assertMatchesRegularExpression('#<a class="k-nt" href="[^"]*/en/about"[^>]*>About#', $nav);
        $this->assertMatchesRegularExpression('#<a class="k-nt" href="[^"]*/en/publications"[^>]*>Publications#', $nav);

        preg_match_all('/aria-controls="(k-mega-\d+)"/', $nav, $controls);
        $this->assertNotEmpty($controls[1]);

        foreach ($controls[1] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $nav, $id.' is controlled and does not exist.');
        }

        $this->assertMatchesRegularExpression('/<button class="k-chev" type="button" aria-expanded="false" aria-controls="k-mega-\d+"\s+aria-label="Show the About menu"/', $nav);
    }

    public function test_the_featured_card_is_the_comparator_or_a_real_record(): void
    {
        $nav = $this->between($this->page(), 'data-k-nav>', '</nav>');

        $this->assertStringContainsString('class="k-feat"', $nav);
        $this->assertStringContainsString('The same question, asked of fourteen regulators', $nav);
    }

    public function test_the_mega_menu_is_keyboard_operable(): void
    {
        $js = file_get_contents(public_path('js/nav.js'));

        foreach (["'Escape'", "'ArrowDown'", "'ArrowUp'", "'Home'", "'End'", "'ArrowLeft'", "'ArrowRight'", "toggle.focus()", "aria-expanded"] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle.' is not handled.');
        }

        // Without the script, hover and focus inside the item open it.
        $css = file_get_contents(public_path('css/k.css'));
        $this->assertStringContainsString('html:not(.k-nav-js) .k-ni--dd:focus-within .k-mega', $css);
        $this->assertStringContainsString('IntersectionObserver', $js);
        $this->assertStringNotContainsString('scrollY', $js);
    }

    public function test_the_drawer_has_every_link_and_the_languages_first(): void
    {
        $html = $this->page();
        $nav = $this->between($html, 'data-k-nav>', '</nav>');
        $drawer = $this->between($html, 'data-k-drawer hidden>', '</aside>');

        // Languages before the search and the sections.
        $this->assertLessThan(strpos($drawer, 'drawer-search'), strpos($drawer, 'k-d-langs'));
        $this->assertStringContainsString('hreflang="fr"', $drawer);
        $this->assertStringContainsString('hreflang="pt"', $drawer);

        // Every link of the desktop panels is in the drawer too.
        preg_match_all('/<a class="k-ml[^"]*" href="([^"]+)"/', $nav, $links);
        $this->assertNotEmpty($links[1]);
        foreach (array_unique($links[1]) as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $drawer, $href.' is not in the drawer.');
        }

        // Each section is an accordion button that says what it controls.
        $this->assertMatchesRegularExpression('/<button type="button" aria-expanded="false" aria-controls="k-dp-\d+" data-k-acc-toggle>About/', $drawer);
        $this->assertStringContainsString('Back office', $drawer);
        $this->assertStringContainsString('Member area', $drawer);

        $js = file_get_contents(public_path('js/chrome.js'));
        $this->assertStringContainsString("event.key === 'Escape'", $js);
        $this->assertStringContainsString("event.key !== 'Tab'", $js);
    }

    public function test_search_is_a_labelled_field_of_300px(): void
    {
        $header = $this->between($this->page(), '<header class="k-header"', '</header>');

        $this->assertMatchesRegularExpression('#<form class="k-qsearch" role="search" action="[^"]*/en/search" method="get">#', $header);
        $this->assertStringContainsString('<label class="c-sr" for="site-search">Search</label>', $header);
        $this->assertStringContainsString('width: 300px', file_get_contents(public_path('css/k.css')));
    }

    public function test_the_identity_is_the_full_name_on_one_line_and_the_toolbar_floats(): void
    {
        $header = $this->between($this->page(), '<header class="k-header"', '</header>');

        $this->assertStringContainsString('Communications Regulators&#039; Association of Southern Africa', $header);
        $this->assertStringContainsString('class="k-idb__short"', $header);
        // Since 30 October the display options float in the bottom corner
        // (partials/float-controls), straight after the header.
        $this->assertStringNotContainsString('data-a11y', $header);
        $this->assertMatchesRegularExpression('#</header>.*?<div class="c-float"[^>]*data-float>.*?data-a11y-toggle.*?<main#s', $this->page());

        $css = file_get_contents(public_path('css/k.css'));
        $this->assertMatchesRegularExpression('/\.k-idb__full \{[^}]*font: 600 20px[^}]*white-space: nowrap/', $css);
        $this->assertMatchesRegularExpression('/\.k-idb \{[^}]*max-width: max\(52%/', $css);
    }

    public function test_labels_are_translated(): void
    {
        $this->assertStringContainsString('Rapports annuels', $this->page('/fr/about'));
        $this->assertStringContainsString('Relatórios anuais', $this->page('/pt/about'));
    }

    public function test_sitemap_and_footer_are_on_every_public_page_and_the_breadcrumb_row_is_gone(): void
    {
        // The breadcrumb and share row was removed on 30 October 2026
        // (client request); Share is on content pages only (ShareButtonTest).
        foreach (['/en', '/en/about', '/en/contact'] as $path) {
            $html = $this->page($path);
            $this->assertStringNotContainsString('k-crumb', $html, $path);
            $this->assertStringNotContainsString('data-k-share', $html, $path);
            $this->assertStringContainsString('class="k-smap"', $html, $path);
            $this->assertStringContainsString('class="k-foot"', $html, $path);
        }

        $footer = $this->between($this->page(), '<footer class="k-foot"', '</footer>');
        $this->assertStringContainsString('data-network=', $footer);
        $this->assertStringContainsString('mailto:', $footer);
        $this->assertStringContainsString('Accessibility', $footer);
    }

    public function test_a_closed_drawer_takes_no_focus(): void
    {
        // On 29 October the drawer stayed rendered off screen when closed
        // (display:flex overrode hidden) and Tab cycled inside it forever.
        $css = file_get_contents(public_path('css/k.css'));
        $this->assertStringContainsString('.k-drawer[hidden], .k-scrim[hidden] { display: none; }', $css);

        $js = file_get_contents(public_path('js/chrome.js'));
        $this->assertStringContainsString('if (drawer.hidden) return;', $js);
    }
}
