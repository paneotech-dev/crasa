<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\AuthorityContact;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * INC-07, UX-11: with nobody listed, the directory is out of the menu and
 * its page says how a person comes to be listed, with "List me" for a
 * representative. Nobody is listed without their consent.
 */
class DirectoryEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_directory_is_out_of_the_menu_and_says_how_to_be_listed(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $botswana = Country::where('iso_code', 'bw')->sole();
        $authority = Authority::factory()->create(['country_id' => $botswana->id]);
        $rep = User::factory()->memberStateRep($botswana)->create();

        $dashboard = $this->actingAs($rep)->get('/en/extranet/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.url('/en/directory').'"', $dashboard);

        $this->actingAs($rep)->get('/en/directory')->assertOk()
            ->assertSee('People appear here when they choose to be listed')
            ->assertSee('List me')
            ->assertSee('id="list-me"', false)
            ->assertDontSee('Nobody matches');

        // A contact who has not consented does not bring the page back.
        AuthorityContact::create(['authority_id' => $authority->id, 'country_id' => $botswana->id, 'name' => 'Not consented', 'is_sample' => false]);
        $this->assertStringNotContainsString('href="'.url('/en/directory').'"', $this->actingAs($rep)->get('/en/extranet/dashboard')->getContent());

        // Once somebody has chosen to be listed, the menu carries it.
        AuthorityContact::create(['authority_id' => $authority->id, 'country_id' => $botswana->id, 'name' => 'Listed person', 'listed_at' => now(), 'listed_by' => $rep->id, 'is_sample' => false]);
        $this->assertStringContainsString('href="'.url('/en/directory').'"', $this->actingAs($rep)->get('/en/extranet/dashboard')->getContent());
        $this->actingAs($rep)->get('/en/directory?q=nobody-by-this-name')->assertOk()->assertSee('Nobody matches');
    }
}
