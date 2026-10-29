<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Folder;
use App\Models\Meeting;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * INC-11, UX-02, INC-05: the member area is called the member area in
 * three languages, its search box searches it, and the pages list does not
 * carry an empty review column.
 */
class MemberAreaLabelsTest extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->botswana = Country::where('iso_code', 'bw')->sole();
    }

    public function test_the_member_area_is_named_in_three_languages_and_keeps_its_url(): void
    {
        $rep = User::factory()->memberStateRep($this->botswana)->create();

        foreach (['en' => 'Member area', 'fr' => 'Espace membres', 'pt' => 'Área de membros'] as $locale => $label) {
            $html = $this->actingAs($rep)->get("/{$locale}/extranet/dashboard")->assertOk()->getContent();
            $this->assertStringContainsString('aria-label="'.$label.'"', $html, $locale);
            $this->assertStringNotContainsString('>Extranet<', $html, $locale);
        }

        // The URL stays: the public header's link is held by PublicChromeTest.
        $this->actingAs($rep)->get('/en/extranet')->assertRedirect();
    }

    public function test_the_search_box_in_the_member_area_says_member_area(): void
    {
        $admin = User::factory()->administrator()->create();
        $rep = User::factory()->memberStateRep($this->botswana)->create();

        foreach ([$admin, $rep] as $user) {
            $this->actingAs($user)->get('/en/extranet/calendar')->assertOk()
                ->assertSee('placeholder="Search the member area…"', false)
                ->assertSee('action="'.url('/en/extranet/search').'"', false)
                ->assertDontSee('Search the administration');
        }

        // The back office keeps its own.
        $this->actingAs($admin)->get('/en/admin')->assertOk()
            ->assertSee('placeholder="Search the administration…"', false);

        $this->actingAs($rep)->get('/fr/extranet/calendar')->assertOk()
            ->assertSee('Rechercher dans l&#039;espace membres', false);
    }

    public function test_the_member_search_finds_only_what_the_reader_may_open(): void
    {
        $rep = User::factory()->memberStateRep($this->botswana)->create();
        $other = Country::where('iso_code', '!=', 'bw')->first();

        Meeting::factory()->create(['title' => 'Spectrum planning session', 'clearance' => 'member', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-10']);
        Meeting::factory()->create(['title' => 'Spectrum closed session', 'clearance' => 'confidential', 'starts_on' => '2026-11-11', 'ends_on' => '2026-11-11']);
        Folder::create(['name' => 'Spectrum returns Botswana', 'slug' => 'spectrum-bw', 'country_id' => $this->botswana->id, 'clearance' => 'member']);
        Folder::create(['name' => 'Spectrum returns elsewhere', 'slug' => 'spectrum-other', 'country_id' => $other->id, 'clearance' => 'member']);

        $this->actingAs($rep)->get('/en/extranet/search?q=Spectrum')->assertOk()
            ->assertSee('Spectrum planning session')
            ->assertSee('Spectrum returns Botswana')
            ->assertDontSee('Spectrum closed session')
            ->assertDontSee('Spectrum returns elsewhere');

        $this->actingAs($rep)->get('/en/extranet/search?q=zzzz')->assertOk()
            ->assertSee('Nothing in the member area matches');
    }

    public function test_a_viewer_is_refused_the_member_search(): void
    {
        $this->actingAs(User::factory()->viewer()->create())->get('/en/extranet/search?q=a')->assertForbidden();
    }

    public function test_the_approval_column_is_hidden_when_empty_and_named_when_not(): void
    {
        $admin = User::factory()->administrator()->create();
        $page = Page::factory()->create();

        $this->actingAs($admin)->get('/en/admin/pages')->assertOk()
            ->assertDontSee('Approval status')
            ->assertDontSee('Client review');

        $page->update(['needs_client_review' => true, 'client_reviewed_at' => now()]);

        $this->actingAs($admin)->get('/en/admin/pages')->assertOk()
            ->assertSee('Approval status')
            ->assertDontSee('Client review');
    }

    public function test_the_footer_menu_label_migration_renames_only_the_old_label(): void
    {
        $id = DB::table('menu_items')->insertGetId(['menu' => 'footer', 'destination_type' => 'route', 'destination' => 'extranet', 'position' => 1, 'is_visible' => true]);
        foreach (['en' => 'Extranet', 'fr' => 'Extranet', 'pt' => 'Members'] as $locale => $value) {
            DB::table('translations')->insert(['translatable_type' => 'App\Models\MenuItem', 'translatable_id' => $id, 'locale' => $locale, 'field' => 'label', 'value' => $value]);
        }

        $migration = require database_path('migrations/2026_10_29_021000_rename_the_extranet_menu_label_to_member_area.php');
        $migration->up();
        $migration->up();

        // By type as well as id: the taxonomy terms' names share the table
        // and can carry the same id.
        $labels = DB::table('translations')->where('translatable_type', 'App\Models\MenuItem')->where('translatable_id', $id)->where('field', 'label')->pluck('value', 'locale')->all();
        $this->assertSame(['en' => 'Member area', 'fr' => 'Espace membres', 'pt' => 'Members'], $labels);

        $migration->down();
        $this->assertSame('Extranet', DB::table('translations')->where('translatable_type', 'App\Models\MenuItem')->where('translatable_id', $id)->where('field', 'label')->where('locale', 'en')->value('value'));
    }
}
