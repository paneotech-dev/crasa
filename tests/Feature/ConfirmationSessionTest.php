<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Confirming the member state records, as a working session.
 *
 * All fourteen authorities were flagged unverified and no member state had
 * confirmed its own regulator's details. Fourteen written confirmations from
 * fourteen regulators inside fourteen days is not realistic; the fourteen
 * delegates who can confirm will be in one room in Lilongwe.
 */
class ConfirmationSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->operator = User::factory()->administrator()->create(['name' => 'Secretariat operator']);
    }

    public function test_the_session_lists_every_member_state_with_its_status(): void
    {
        $html = $this->actingAs($this->operator)->get('/en/admin/confirmations')->assertOk()->getContent();

        foreach (Country::pluck('name') as $name) {
            $this->assertStringContainsString($name, $html);
        }

        // A room needs to see progress. Fourteen outstanding at the start.
        $this->assertStringContainsString('Still to confirm', $html);
    }

    public function test_a_delegate_can_correct_the_record_and_confirm_it(): void
    {
        $country = Country::where('iso_code', 'bw')->firstOrFail();
        $telecoms = Sector::where('slug', 'telecommunications')->firstOrFail();
        $committee = Committee::first();

        $this->actingAs($this->operator)
            ->put(route('admin.confirmations.update', ['locale' => 'en', 'iso' => 'bw']), [
                'name' => 'Botswana Communications Regulatory Authority',
                'acronym' => 'BOCRA',
                'website' => 'https://www.bocra.org.bw',
                'established_year' => 2013,
                'sectors' => [$telecoms->id],
                'committees' => [$committee->id],
                'confirmed_by_name' => 'A delegate in the room',
                'confirm' => 1,
            ])
            ->assertRedirect();

        $authority = $country->fresh()->authority;

        $this->assertSame('Botswana Communications Regulatory Authority', $authority->name);
        $this->assertSame('https://www.bocra.org.bw', $authority->website);
        $this->assertTrue($authority->isConfirmed());
        $this->assertSame('A delegate in the room', $authority->confirmed_by_name);
        $this->assertSame($this->operator->id, $authority->confirmed_by_id);
        $this->assertSame([$telecoms->id], $authority->sectors->pluck('id')->all());
        $this->assertSame([$committee->id], $country->fresh()->committees->pluck('id')->all());
    }

    /**
     * A confirmed record is no longer demonstration data, so the go-live
     * purge must not take it. Confirming is how a record graduates.
     */
    public function test_confirming_takes_the_record_out_of_the_purge(): void
    {
        $authority = Country::where('iso_code', 'na')->firstOrFail()->authority;
        $this->assertTrue($authority->is_sample);

        $this->actingAs($this->operator)->put(route('admin.confirmations.update', ['locale' => 'en', 'iso' => 'na']), [
            'name' => 'Communications Regulatory Authority of Namibia',
            'acronym' => 'CRAN',
            'confirmed_by_name' => 'A Namibian delegate',
            'confirm' => 1,
        ])->assertRedirect();

        $this->assertFalse($authority->fresh()->is_sample);

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        $this->assertNotNull(Authority::find($authority->id), 'A confirmed authority was removed by the purge.');
    }

    /**
     * The signed-in account is the Secretariat operator driving the session
     * on a projector. The person vouching is the delegate beside them, and
     * a confirmation recording the operator confirms nothing.
     */
    public function test_confirming_requires_the_name_of_whoever_is_vouching(): void
    {
        $this->actingAs($this->operator)
            ->put(route('admin.confirmations.update', ['locale' => 'en', 'iso' => 'zm']), [
                'name' => 'Zambia Information and Communications Technology Authority',
                'acronym' => 'ZICTA',
                'confirm' => 1,
            ])
            ->assertSessionHasErrors('confirmed_by_name');

        $this->assertFalse(Country::where('iso_code', 'zm')->first()->authority->isConfirmed());
    }

    /** A correction can be saved without claiming anyone confirmed it. */
    public function test_a_record_can_be_corrected_without_being_confirmed(): void
    {
        $this->actingAs($this->operator)
            ->put(route('admin.confirmations.update', ['locale' => 'en', 'iso' => 'mw']), [
                'name' => 'Malawi Communications Regulatory Authority',
                'acronym' => 'MACRA',
                'confirm' => 0,
            ])
            ->assertRedirect();

        $authority = Country::where('iso_code', 'mw')->first()->authority;

        $this->assertSame('MACRA', $authority->acronym);
        $this->assertFalse($authority->isConfirmed());
        $this->assertTrue($authority->is_sample, 'An unconfirmed record must stay demonstration data.');
    }

    /** Every confirmation writes an audit row naming the actor. */
    public function test_every_confirmation_is_audited(): void
    {
        $before = DB::table('audit_log')->count();

        $this->actingAs($this->operator)->put(route('admin.confirmations.update', ['locale' => 'en', 'iso' => 'za']), [
            'name' => 'Independent Communications Authority of South Africa',
            'acronym' => 'ICASA',
            'confirmed_by_name' => 'A South African delegate',
            'confirm' => 1,
        ])->assertRedirect();

        $this->assertGreaterThan($before, DB::table('audit_log')->count(), 'A confirmation left no audit trail.');
    }

    public function test_the_count_moves_as_records_are_confirmed(): void
    {
        $this->actingAs($this->operator)->get('/en/admin/confirmations')->assertOk()->assertSee('>0<', false);

        $this->actingAs($this->operator)->put(route('admin.confirmations.update', ['locale' => 'en', 'iso' => 'tz']), [
            'name' => 'Tanzania Communications Regulatory Authority',
            'acronym' => 'TCRA',
            'confirmed_by_name' => 'A Tanzanian delegate',
            'confirm' => 1,
        ]);

        $this->actingAs($this->operator)->get('/en/admin/confirmations')->assertOk()->assertSee('>1<', false);
    }

    public function test_an_invalid_website_is_refused_rather_than_stored(): void
    {
        $this->actingAs($this->operator)
            ->put(route('admin.confirmations.update', ['locale' => 'en', 'iso' => 'ls']), [
                'name' => 'Lesotho Communications Authority',
                'acronym' => 'LCA',
                'website' => 'not a website',
                'confirm' => 0,
            ])
            ->assertSessionHasErrors('website');
    }

    public function test_the_session_is_behind_the_content_permission(): void
    {
        $this->get('/en/admin/confirmations')->assertRedirect();

        $viewer = User::factory()->create(['role' => Role::Viewer]);
        $this->actingAs($viewer)->get('/en/admin/confirmations')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/confirmations/bw')->assertForbidden();
    }

    public function test_an_unknown_member_state_is_not_a_page(): void
    {
        $this->actingAs($this->operator)->get('/en/admin/confirmations/xx')->assertNotFound();
    }
}
