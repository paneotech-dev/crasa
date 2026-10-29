<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Instrument;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * INC-12, UX-13: the register shows instruments in force by default, the
 * repealed ones on request, and "Date not recorded" rather than a dash.
 */
class RegisterDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_force_by_default_repealed_on_request_and_dates_in_words(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();

        $inForce = Instrument::factory()->inForce()->create(['enacted_on' => null]);
        $inForce->setTranslation('title', 'en', 'Numbering regulations in force');
        $repealed = Instrument::factory()->repealed()->create(['enacted_on' => '2010-05-01']);
        $repealed->setTranslation('title', 'en', 'Old licensing act');

        $this->actingAs($rep)->get('/en/register')->assertOk()
            ->assertSee('Numbering regulations in force')
            ->assertDontSee('Old licensing act')
            ->assertSee('Date not recorded')
            ->assertSee('Include repealed');

        $this->actingAs($rep)->get('/en/register?repealed=1')->assertOk()
            ->assertSee('Numbering regulations in force')
            ->assertSee('Old licensing act');

        $this->actingAs($rep)->get('/en/register/'.$inForce->slug)->assertOk()->assertSee('Date not recorded');
        $this->actingAs($rep)->get('/fr/register')->assertOk()->assertSee('Date non enregistrée');
    }
}
