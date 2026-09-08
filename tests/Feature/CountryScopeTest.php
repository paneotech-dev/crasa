<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\CountryRecord;
use Tests\Fixtures\SharedCountryRecord;
use Tests\TestCase;

/**
 * The country scope, tested on raw model queries rather than through a
 * controller. A controller check can be forgotten; a global scope cannot.
 */
class CountryScopeTest extends TestCase
{
    use RefreshDatabase;

    private Country $namibia;

    private Country $zambia;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('country_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });

        $this->namibia = Country::factory()->create(['name' => 'Namibia', 'iso_code' => 'na']);
        $this->zambia = Country::factory()->create(['name' => 'Zambia', 'iso_code' => 'zm']);

        CountryRecord::insert([
            ['country_id' => $this->namibia->id, 'title' => 'Namibia 2026 return'],
            ['country_id' => $this->zambia->id, 'title' => 'Zambia 2026 return'],
            ['country_id' => null, 'title' => 'Regional summary'],
        ]);
    }

    public function test_a_representative_sees_only_their_own_country_on_a_raw_query(): void
    {
        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create());

        $records = CountryRecord::all();

        $this->assertCount(1, $records);
        $this->assertSame('Namibia 2026 return', $records->first()->title);
    }

    /**
     * The listing must be scoped at the query, not filtered after the fact.
     * A count() that returns 3 while the listing shows 1 means the rows were
     * fetched and hidden, which leaks through pagination and exports.
     */
    public function test_the_listing_is_scoped_at_the_query_not_filtered_afterwards(): void
    {
        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create());

        $this->assertSame(1, CountryRecord::count());
        $this->assertSame(1, CountryRecord::query()->paginate(50)->total());
    }

    public function test_a_representative_cannot_reach_another_country_row_by_id(): void
    {
        $zambianRow = CountryRecord::withoutGlobalScopes()->where('title', 'Zambia 2026 return')->sole();

        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create());

        $this->assertNull(CountryRecord::find($zambianRow->id));
    }

    public function test_a_representative_cannot_write_to_another_country_row(): void
    {
        $zambianRow = CountryRecord::withoutGlobalScopes()->where('title', 'Zambia 2026 return')->sole();

        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create());

        $affected = CountryRecord::where('id', $zambianRow->id)->update(['title' => 'Tampered']);

        $this->assertSame(0, $affected);
        $this->assertSame(
            'Zambia 2026 return',
            CountryRecord::withoutGlobalScopes()->find($zambianRow->id)->title
        );
    }

    public function test_an_administrator_sees_every_country(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->assertSame(3, CountryRecord::count());
    }

    public function test_an_editor_sees_every_country(): void
    {
        $this->actingAs(User::factory()->editor()->create());

        $this->assertSame(3, CountryRecord::count());
    }

    public function test_a_regional_row_is_hidden_unless_the_model_opts_into_sharing(): void
    {
        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create());

        $this->assertSame(1, CountryRecord::count());
        $this->assertSame(2, SharedCountryRecord::count());
    }

    /**
     * A country-scoped account with no country is misconfigured. It returns
     * nothing rather than everything.
     */
    public function test_a_country_scoped_account_without_a_country_sees_nothing(): void
    {
        $orphan = User::factory()->memberStateRep()->create();
        $orphan->forceFill(['country_id' => null])->save();

        $this->actingAs($orphan);

        $this->assertSame(0, CountryRecord::count());
    }

    public function test_an_unauthenticated_query_is_not_constrained(): void
    {
        // Console commands, queued jobs and seeders have no acting user.
        // Policies and route middleware govern those paths.
        $this->assertSame(3, CountryRecord::count());
    }

    public function test_the_bypass_is_named_and_refuses_an_account_not_entitled_to_it(): void
    {
        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create());

        $this->expectException(AuthorizationException::class);

        CountryRecord::acrossAllCountries()->get();
    }

    public function test_an_administrator_may_use_the_named_bypass(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->assertSame(3, CountryRecord::acrossAllCountries()->count());
    }
}
