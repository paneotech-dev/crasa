<?php

namespace Tests\Feature;

use App\Enums\InstrumentStatus;
use App\Models\Country;
use App\Models\Instrument;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An instrument with no reference number renders everywhere it is listed.
 *
 * Found on the rebuilt host on 16 September: five views passed
 * reference_number, which is nullable, as the fallback to a string-only
 * parameter. Every instrument without one threw a TypeError, and the members'
 * register returned 500 for all ten seeded instruments. Imported and seeded
 * instruments often carry no reference number, so this is the common case,
 * not an edge.
 */
class InstrumentWithoutReferenceTest extends TestCase
{
    use RefreshDatabase;

    private Instrument $instrument;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $botswana = Country::where('iso_code', 'bw')->firstOrFail();

        $this->instrument = Instrument::create([
            'slug' => 'bw-number-portability-regulations',
            'country_id' => $botswana->id,
            'sector_id' => Sector::first()->id,
            'instrument_type' => 'regulation',
            'reference_number' => null,
            'status' => InstrumentStatus::InForce,
            'is_sample' => true,
        ]);
        $this->instrument->setTranslations('en', ['title' => 'Number portability regulations']);

        $this->rep = User::factory()->create(['role' => 'member_state_rep', 'country_id' => $botswana->id]);
    }

    public function test_the_register_lists_it(): void
    {
        $this->actingAs($this->rep)->get('/en/register')
            ->assertOk()
            ->assertSee('Number portability regulations');
    }

    public function test_a_missing_title_falls_back_to_something_readable(): void
    {
        $this->instrument->translations()->delete();

        $this->actingAs($this->rep)->get('/en/register')
            ->assertOk()
            ->assertSee('bw-number-portability-regulations');
    }

    public function test_a_null_default_is_an_empty_string_not_an_error(): void
    {
        $this->assertSame('', $this->instrument->translated('no_such_field', null));
    }
}
