<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Screen 14's navigation builder.
 *
 * "Everything manageable from the administration" is the client's stated
 * core requirement and a D2 acceptance item. The header read a config array
 * and the footer's columns were written into a Blade template, so renaming a
 * menu item meant a deploy.
 */
class NavigationBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(LegalPagesSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->editor = User::factory()->administrator()->create();
    }

    /* The site reads the records ---------------------------------------------- */

    public function test_the_header_renders_from_records(): void
    {
        $item = MenuItem::inMenu('header')->whereNull('parent_id')->orderBy('position')->firstOrFail();
        $item->setTranslations('en', ['label' => 'Renamed without a deploy']);

        $this->get('/en')->assertOk()->assertSee('Renamed without a deploy');
    }

    public function test_the_footer_renders_from_records(): void
    {
        $column = MenuItem::inMenu('footer')->whereNull('parent_id')->orderBy('position')->firstOrFail();
        $column->setTranslations('en', ['label' => 'A renamed column']);

        $this->get('/en')->assertOk()->assertSee('A renamed column');
    }

    public function test_hiding_an_item_removes_it_from_the_site(): void
    {
        // A label of its own, because several menu labels also appear in
        // the footer and in block copy, and this must assert on the header.
        $item = MenuItem::inMenu('header')->whereNull('parent_id')->orderBy('position')->skip(2)->firstOrFail();
        $item->setTranslations('en', ['label' => 'Uniquely named item']);

        $this->assertStringContainsString('Uniquely named item', $this->header($this->get('/en')->assertOk()->getContent()));

        $item->update(['is_visible' => false]);

        $this->assertStringNotContainsString('Uniquely named item', $this->header($this->get('/en')->assertOk()->getContent()));
    }

    /** Just the site header, so a label repeated in the footer cannot pass. */
    private function header(string $html): string
    {
        $start = strpos($html, '<header');
        $end = strpos($html, '</header>');

        return $start === false || $end === false ? '' : substr($html, $start, $end - $start);
    }

    public function test_reordering_changes_the_order_on_the_site(): void
    {
        $items = MenuItem::inMenu('header')->whereNull('parent_id')->orderBy('position')->get();
        $second = $items[1];

        $this->actingAs($this->editor)
            ->put(route('admin.menus.move', ['locale' => 'en', 'item' => $second->id]), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(
            $second->id,
            MenuItem::inMenu('header')->whereNull('parent_id')->orderBy('position')->first()->id
        );
    }

    /* The break this must survive --------------------------------------------- */

    /**
     * A MENU ITEM MUST NOT BE ABLE TO TAKE THE SITE DOWN.
     *
     * A menu item can outlive its destination: a page is deleted, a route is
     * renamed in a refactor, an editor mistypes a slug. Every one of those
     * happens on a live site, and the header is on every page, so a menu
     * item that threw would 500 the whole platform at once.
     */
    public function test_a_menu_item_pointing_at_a_deleted_page_does_not_break_the_site(): void
    {
        $page = Page::factory()->create(['slug' => 'temporary-page', 'status' => 'published']);

        $item = MenuItem::create([
            'menu' => 'header',
            'position' => 99,
            'destination_type' => 'page',
            'destination' => 'temporary-page',
            'is_visible' => true,
        ]);
        $item->setTranslations('en', ['label' => 'Temporary']);

        $this->get('/en')->assertOk()->assertSee('Temporary');

        $page->delete();

        // Not a 500, and not a link to a 404.
        $this->get('/en')->assertOk()->assertDontSee('Temporary');
        $this->get('/en/publications')->assertOk();
        $this->get('/fr')->assertOk();
    }

    public function test_a_menu_item_pointing_at_a_route_that_no_longer_exists_is_skipped(): void
    {
        $item = MenuItem::create([
            'menu' => 'header',
            'position' => 99,
            'destination_type' => 'route',
            'destination' => 'route.that.was.renamed',
            'is_visible' => true,
        ]);
        $item->setTranslations('en', ['label' => 'Ghost item']);

        $this->get('/en')->assertOk()->assertDontSee('Ghost item');
        $this->assertNull($item->url());
        $this->assertFalse($item->destinationIsResolvable());
    }

    public function test_a_menu_item_pointing_at_an_unpublished_page_is_skipped(): void
    {
        $page = Page::factory()->create(['slug' => 'draft-page', 'status' => 'draft']);

        $item = MenuItem::create([
            'menu' => 'header',
            'position' => 99,
            'destination_type' => 'page',
            'destination' => $page->slug,
            'is_visible' => true,
        ]);
        $item->setTranslations('en', ['label' => 'Not yet published']);

        $this->get('/en')->assertOk()->assertDontSee('Not yet published');
    }

    /**
     * A site with no navigation is a broken site. A fresh install, a bad
     * import or a purge that went further than intended falls back to the
     * built-in structure rather than rendering an empty header.
     */
    public function test_an_empty_menu_falls_back_to_the_built_in_navigation(): void
    {
        MenuItem::query()->delete();

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertNotEmpty(Navigation::primary());
        $this->assertNotEmpty(Navigation::footer());
        $this->assertStringContainsString(__('nav.publications'), $html);
        // And the footer keeps its columns.
        $this->assertStringContainsString(__('footer.follow_us'), $html);
    }

    /* Translation behaviour ---------------------------------------------------- */

    /**
     * Screen 14: "A menu item with an untranslated label shows the English
     * text on the translated site and is flagged here, rather than
     * disappearing from the navigation."
     */
    public function test_an_untranslated_label_falls_back_to_english_rather_than_vanishing(): void
    {
        $item = MenuItem::create([
            'menu' => 'header',
            'position' => 99,
            'destination_type' => 'route',
            'destination' => 'contact',
            'is_visible' => true,
        ]);
        $item->setTranslations('en', ['label' => 'English only item']);

        $this->get('/fr')->assertOk()->assertSee('English only item');

        $this->assertSame(['fr', 'pt'], $item->fresh()->load('translations')->missingLocales());
    }

    public function test_the_builder_flags_an_item_with_a_missing_translation(): void
    {
        $item = MenuItem::create([
            'menu' => 'header', 'position' => 99,
            'destination_type' => 'route', 'destination' => 'contact', 'is_visible' => true,
        ]);
        $item->setTranslations('en', ['label' => 'Half translated']);

        $this->actingAs($this->editor)
            ->get('/en/admin/menus')
            ->assertOk()
            ->assertSee('Half translated')
            ->assertSee('No FR, PT label');
    }

    public function test_the_builder_flags_an_item_whose_destination_has_gone(): void
    {
        $item = MenuItem::create([
            'menu' => 'header', 'position' => 99,
            'destination_type' => 'page', 'destination' => 'never-existed', 'is_visible' => true,
        ]);
        $item->setTranslations('en', ['label' => 'Points nowhere']);

        $this->actingAs($this->editor)
            ->get('/en/admin/menus')
            ->assertOk()
            ->assertSee('Destination missing');
    }

    /* The builder itself -------------------------------------------------------- */

    public function test_an_editor_can_add_a_menu_item_without_a_deploy(): void
    {
        $this->actingAs($this->editor)
            ->post(route('admin.menus.store', ['locale' => 'en']), [
                'menu' => 'header',
                'destination_type' => 'route',
                'destination' => 'contact',
                'label_en' => 'Get in touch',
                'label_fr' => 'Nous contacter',
                'label_pt' => 'Contacte-nos',
            ])
            ->assertRedirect();

        $this->get('/en')->assertOk()->assertSee('Get in touch');
        $this->get('/fr')->assertOk()->assertSee('Nous contacter');
    }

    /**
     * English is required because it is the fallback. An item with no
     * English label has nothing to show when a translation is missing and
     * would render as a blank link.
     */
    public function test_an_item_cannot_be_created_without_an_english_label(): void
    {
        $this->actingAs($this->editor)
            ->post(route('admin.menus.store', ['locale' => 'en']), [
                'menu' => 'header',
                'destination_type' => 'route',
                'destination' => 'contact',
                'label_fr' => 'Sans anglais',
            ])
            ->assertSessionHasErrors('label_en');
    }

    public function test_deleting_a_footer_column_takes_its_links_with_it(): void
    {
        $column = MenuItem::inMenu('footer')->whereNull('parent_id')->firstOrFail();
        $childIds = $column->children->pluck('id');

        $this->assertNotEmpty($childIds);

        $this->actingAs($this->editor)
            ->delete(route('admin.menus.destroy', ['locale' => 'en', 'item' => $column->id]))
            ->assertRedirect();

        $this->assertSame(0, MenuItem::whereIn('id', $childIds)->count());
    }

    public function test_the_builder_is_behind_the_content_permission(): void
    {
        $this->get('/en/admin/menus')->assertRedirect();

        $viewer = User::factory()->create(['role' => Role::Viewer]);
        $this->actingAs($viewer)->get('/en/admin/menus')->assertForbidden();
    }

    /**
     * The menus are the site's structure, not demonstration data. A purge
     * that removed them would leave the platform with no navigation, which
     * is the mistake this project has already made once with the homepage's
     * blocks.
     */
    public function test_the_menus_survive_the_go_live_purge(): void
    {
        $before = MenuItem::count();
        $this->assertGreaterThan(0, $before);

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        $this->assertSame($before, MenuItem::count());
    }
}
