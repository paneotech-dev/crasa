<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\Meeting;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Day 3: create, edit and delete for member states, authorities,
 * committees, indicators and instruments, with deletes refused by name
 * while anything points at the record, and the people on a committee.
 */
class Day3CrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        config(['crasa.two_factor_required' => false]);

        $this->admin = User::factory()->administrator()->create();
    }

    /** Read a localised name as a public page would, outside the back office. */
    private function inLocale(string $locale, \Closure $read): mixed
    {
        $this->app->instance('request', Request::create('/'.$locale));
        app()->setLocale($locale);

        try {
            return $read();
        } finally {
            app()->setLocale('en');
        }
    }

    public function test_every_list_form_and_the_seats_screen_open_in_three_languages(): void
    {
        Authority::factory()->create(['country_id' => Country::where('iso_code', 'bw')->value('id')]);
        Indicator::factory()->create();
        Instrument::factory()->create();
        $committee = Committee::first();
        $committee->users()->attach(User::factory()->create()->id, ['role' => 'chair', 'is_sample' => false]);

        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (['countries', 'authorities', 'committees', 'indicators', 'instruments'] as $key) {
                $model = \App\Admin\ResourceRegistry::find($key)->model;
                $this->actingAs($this->admin)->get("/{$locale}/admin/data/{$key}")->assertOk();
                $this->actingAs($this->admin)->get("/{$locale}/admin/data/{$key}/new")->assertOk();
                $this->actingAs($this->admin)->get("/{$locale}/admin/data/{$key}/".$model::query()->withoutGlobalScopes()->value('id'))->assertOk();
            }
            $this->actingAs($this->admin)->get("/{$locale}/admin/committees/{$committee->slug}/seats")->assertOk()
                ->assertSee(__('d3_crud.people_title', [], $locale));
        }

        // The platform's own French line is shown beside an empty field,
        // never put in it.
        $za = Country::where('iso_code', 'za')->sole();
        $html = $this->actingAs($this->admin)->get('/en/admin/data/countries/'.$za->id)->getContent();
        $this->assertStringContainsString('Afrique du Sud', $html);
        $this->assertStringNotContainsString('value="Afrique du Sud"', $html);
    }

    /* Member states ---------------------------------------------------------- */

    public function test_a_member_state_is_created_with_its_french_and_portuguese_name(): void
    {
        $this->actingAs($this->admin)->get('/en/admin/data/countries/new')->assertOk()
            ->assertSee('name="localised[fr][name]"', false);

        $this->actingAs($this->admin)->post('/en/admin/data/countries', [
            'name' => 'Seychelles',
            'iso_code' => 'sc',
            'official_languages' => 'en,fr',
            'localised' => ['fr' => ['name' => 'Seychelles'], 'pt' => ['name' => 'Seicheles']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $country = Country::where('iso_code', 'sc')->sole();
        $this->assertSame('Seicheles', $this->inLocale('pt', fn () => $country->fresh()->name));
        $this->assertSame('Seychelles', $country->rawName());
    }

    public function test_a_member_state_iso_code_must_be_new(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/data/countries', [
            'name' => 'Duplicate', 'iso_code' => 'na', 'official_languages' => 'en',
        ])->assertSessionHasErrors('iso_code');
    }

    public function test_a_member_state_is_edited_and_its_confirm_action_stays_separate(): void
    {
        $country = Country::where('iso_code', 'na')->sole();

        $this->actingAs($this->admin)->get('/en/admin/data/countries/'.$country->id)->assertOk()
            ->assertSee(route('admin.confirmations.edit', ['locale' => 'en', 'iso' => 'na']), false);

        $this->actingAs($this->admin)->put('/en/admin/data/countries/'.$country->id, [
            'name' => 'Namibia', 'iso_code' => 'na', 'official_languages' => 'en',
            'localised' => ['fr' => ['name' => 'Namibie (rev.)'], 'pt' => ['name' => '']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Namibie (rev.)', $this->inLocale('fr', fn () => $country->fresh()->name));
        // Empty falls back to the platform's own line.
        $this->assertSame('Namíbia', $this->inLocale('pt', fn () => $country->fresh()->name));
    }

    public function test_a_member_state_nothing_points_at_is_deleted_with_its_name_typed(): void
    {
        $country = Country::create(['name' => 'Testland', 'iso_code' => 'tl', 'official_languages' => 'en']);

        $this->actingAs($this->admin)->delete('/en/admin/data/countries/'.$country->id, ['confirm' => 'wrong'])
            ->assertSessionHasErrors('confirm');
        $this->assertDatabaseHas('countries', ['id' => $country->id]);

        $this->actingAs($this->admin)->delete('/en/admin/data/countries/'.$country->id, ['confirm' => 'Testland'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('countries', ['id' => $country->id]);
    }

    public function test_a_member_state_with_records_is_refused_by_name(): void
    {
        $country = Country::where('iso_code', 'na')->sole();
        Authority::factory()->create(['country_id' => $country->id]);
        User::factory()->count(2)->create(['country_id' => $country->id]);

        $this->actingAs($this->admin)->delete('/en/admin/data/countries/'.$country->id, ['confirm' => 'Namibia'])
            ->assertSessionHasErrors(['record' => 'This member state has 1 authority and 2 user accounts; move or delete them first.']);

        $this->assertDatabaseHas('countries', ['id' => $country->id]);
        $this->actingAs($this->admin)->get('/en/admin/data/countries/'.$country->id)->assertOk()
            ->assertSee('This member state has 1 authority and 2 user accounts; move or delete them first.');
    }

    public function test_only_an_administrator_adds_or_deletes_a_member_state(): void
    {
        $country = Country::where('iso_code', 'na')->sole();
        $payload = ['name' => 'X', 'iso_code' => 'xx', 'official_languages' => 'en'];

        foreach ([User::factory()->editor()->create(), User::factory()->auditor()->create()] as $user) {
            $this->actingAs($user)->post('/en/admin/data/countries', $payload)->assertForbidden();
            $this->actingAs($user)->delete('/en/admin/data/countries/'.$country->id, ['confirm' => 'Namibia'])->assertForbidden();
        }

        $this->actingAs(User::factory()->auditor()->create())
            ->put('/en/admin/data/countries/'.$country->id, $payload)->assertForbidden();
        $this->assertDatabaseMissing('countries', ['iso_code' => 'xx']);
    }

    /* Authorities ------------------------------------------------------------ */

    public function test_an_authority_is_created_edited_and_deleted(): void
    {
        $country = Country::where('iso_code', 'na')->sole();

        $this->actingAs($this->admin)->post('/en/admin/data/authorities', [
            'name' => 'Test Regulatory Authority', 'acronym' => 'TRA', 'country_id' => $country->id,
            'localised' => ['fr' => ['name' => 'Autorité de régulation test'], 'pt' => ['name' => '']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $authority = Authority::where('acronym', 'TRA')->sole();
        $this->assertSame('Autorité de régulation test', $this->inLocale('fr', fn () => $authority->fresh()->name));

        $this->actingAs($this->admin)->put('/en/admin/data/authorities/'.$authority->id, [
            'name' => 'Test Regulatory Authority', 'acronym' => 'TRA2', 'country_id' => $country->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('TRA2', $authority->fresh()->acronym);
        // Not posted, not cleared.
        $this->assertSame('Autorité de régulation test', $this->inLocale('fr', fn () => $authority->fresh()->name));

        $this->actingAs($this->admin)->delete('/en/admin/data/authorities/'.$authority->id, ['confirm' => 'Test Regulatory Authority'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('authorities', ['id' => $authority->id]);
        $this->assertDatabaseMissing('translations', ['translatable_type' => Authority::class, 'translatable_id' => $authority->id]);
    }

    public function test_editing_an_authority_never_confirms_it_and_shows_who_did(): void
    {
        $authority = Authority::factory()->create(['country_id' => Country::where('iso_code', 'bw')->value('id')]);

        $this->actingAs($this->admin)->get('/en/admin/data/authorities/'.$authority->id)->assertOk()
            ->assertSee('Not confirmed yet')
            ->assertSee(route('admin.confirmations.edit', ['locale' => 'en', 'iso' => 'bw']), false);

        $this->actingAs($this->admin)->put('/en/admin/data/authorities/'.$authority->id, [
            'name' => $authority->name, 'acronym' => $authority->acronym, 'country_id' => $authority->country_id,
            'confirmed_at' => now()->toDateTimeString(), 'confirmed_by_name' => 'Somebody',
        ])->assertRedirect();
        $this->assertNull($authority->fresh()->confirmed_at);

        $authority->forceFill(['confirmed_at' => '2026-09-23 10:00:00', 'confirmed_by_name' => 'T. Delegate'])->save();
        $this->actingAs($this->admin)->get('/en/admin/data/authorities/'.$authority->id)->assertOk()
            ->assertSee('Confirmed by T. Delegate on 23 September 2026');
    }

    public function test_an_authority_with_records_is_refused_by_name(): void
    {
        $authority = Authority::factory()->create(['country_id' => Country::where('iso_code', 'bw')->value('id')]);
        Committee::first()->update(['chair_authority_id' => $authority->id]);
        User::factory()->create(['authority_id' => $authority->id, 'country_id' => $authority->country_id]);

        $this->actingAs($this->admin)->delete('/en/admin/data/authorities/'.$authority->id, ['confirm' => $authority->name])
            ->assertSessionHasErrors(['record' => 'This authority has 1 committee it chairs and 1 user account; move or delete them first.']);
        $this->assertDatabaseHas('authorities', ['id' => $authority->id]);
    }

    public function test_an_editor_cannot_delete_an_authority_and_an_auditor_cannot_change_one(): void
    {
        $authority = Authority::factory()->create(['country_id' => Country::where('iso_code', 'bw')->value('id')]);

        $this->actingAs(User::factory()->editor()->create())
            ->delete('/en/admin/data/authorities/'.$authority->id, ['confirm' => $authority->name])->assertForbidden();

        $auditor = User::factory()->auditor()->create();
        $this->actingAs($auditor)->post('/en/admin/data/authorities', [
            'name' => 'X', 'acronym' => 'X', 'country_id' => $authority->country_id,
        ])->assertForbidden();
        $this->actingAs($auditor)->put('/en/admin/data/authorities/'.$authority->id, [
            'name' => 'X', 'acronym' => 'X', 'country_id' => $authority->country_id,
        ])->assertForbidden();
        $this->assertDatabaseHas('authorities', ['id' => $authority->id, 'name' => $authority->name]);
    }

    /* Committees ------------------------------------------------------------- */

    public function test_a_committee_is_created_edited_and_deleted(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/data/committees', [
            'name' => 'Cybersecurity Working Group',
            'sector_id' => Sector::first()->id,
            'en' => ['mandate' => 'Coordinates incident response across the region and keeps a long mandate text that is no slug.'],
            'localised' => ['fr' => ['name' => 'Groupe de travail sur la cybersécurité'], 'pt' => ['name' => '']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        // The slug comes from the name, not from the mandate.
        $committee = Committee::where('slug', 'cybersecurity-working-group')->sole();
        $this->assertSame('Groupe de travail sur la cybersécurité', $this->inLocale('fr', fn () => $committee->fresh()->name));

        $this->actingAs($this->admin)->put('/en/admin/data/committees/'.$committee->id, [
            'name' => 'Cybersecurity Committee', 'sector_id' => Sector::first()->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Cybersecurity Committee', $committee->fresh()->rawName());

        $this->actingAs($this->admin)->delete('/en/admin/data/committees/'.$committee->id, ['confirm' => 'Cybersecurity Committee'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('committees', ['id' => $committee->id]);
    }

    public function test_a_committee_with_meetings_and_folders_is_refused_by_name(): void
    {
        $committee = Committee::first();
        Meeting::factory()->count(12)->create(['committee_id' => $committee->id]);
        foreach ([1, 2, 3] as $n) {
            DB::table('folders')->insert([
                'committee_id' => $committee->id, 'name' => 'Folder '.$n, 'slug' => 'folder-'.$n,
                'clearance' => 'member', 'is_sample' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->admin)->delete('/en/admin/data/committees/'.$committee->id, ['confirm' => $committee->rawName()])
            ->assertSessionHasErrors(['record' => 'This committee has 12 meetings and 3 workspace folders; move or delete them first.']);
        $this->assertDatabaseHas('committees', ['id' => $committee->id]);

        $this->actingAs($this->admin)->get('/fr/admin/data/committees/'.$committee->id)->assertOk()
            ->assertSee('Ce comité a 12 réunions et 3 dossiers de l&#039;espace de travail ; déplacez ou supprimez ces éléments d&#039;abord.', false);
    }

    public function test_an_auditor_cannot_create_or_delete_a_committee(): void
    {
        $auditor = User::factory()->auditor()->create();
        $committee = Committee::first();

        $this->actingAs($auditor)->get('/en/admin/data/committees/new')->assertForbidden();
        $this->actingAs($auditor)->post('/en/admin/data/committees', ['name' => 'X'])->assertForbidden();
        $this->actingAs($auditor)->delete('/en/admin/data/committees/'.$committee->id, ['confirm' => $committee->rawName()])->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())
            ->delete('/en/admin/data/committees/'.$committee->id, ['confirm' => $committee->rawName()])->assertForbidden();
    }

    /* The people on a committee --------------------------------------------- */

    public function test_members_are_added_the_chair_is_set_and_changed_and_a_seat_removed(): void
    {
        $committee = Committee::first();
        [$first, $second] = User::factory()->count(2)->create()->all();
        $base = '/en/admin/committees/'.$committee->slug;

        $this->actingAs($this->admin)->get($base.'/seats')->assertOk()->assertSee('No members recorded yet');

        $this->actingAs($this->admin)->post($base.'/members', ['user_id' => $first->id, 'role' => 'chair'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($base.'/members', ['user_id' => $second->id, 'role' => 'member'])
            ->assertRedirect()->assertSessionHasNoErrors();

        // A second chair stands the first down to member.
        $this->actingAs($this->admin)->put($base.'/members/'.$second->id, ['role' => 'chair'])->assertRedirect();
        $roles = $committee->users()->get()->mapWithKeys(fn ($u) => [$u->id => $u->pivot->role])->all();
        $this->assertSame(['member', 'chair'], [$roles[$first->id], $roles[$second->id]]);

        // Nobody sits twice.
        $this->actingAs($this->admin)->post($base.'/members', ['user_id' => $first->id, 'role' => 'member'])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($this->admin)->delete($base.'/members/'.$first->id)->assertRedirect();
        $this->assertSame([$second->id], $committee->users()->pluck('users.id')->all());

        $changes = AuditLog::where('auditable_type', Committee::class)->where('auditable_id', $committee->id)
            ->where('action', AuditLog::UPDATED)->orderBy('id')->get()->pluck('changes')->all();
        $this->assertSame($first->name, $changes[0]['member_added']);
        $this->assertSame(['member_changed' => $second->name, 'from' => 'member', 'to' => 'chair'], $changes[2]);
        $this->assertSame(['member_removed' => $first->name], $changes[3]);

        // A committee with people on it is not deleted from under them.
        $this->actingAs($this->admin)->delete('/en/admin/data/committees/'.$committee->id, ['confirm' => $committee->rawName()])
            ->assertSessionHasErrors(['record' => 'This committee has 1 committee member; move or delete them first.']);
    }

    public function test_a_representative_or_auditor_cannot_change_the_people_on_a_committee(): void
    {
        $committee = Committee::first();
        $person = User::factory()->create();
        $committee->users()->attach($person->id, ['role' => 'member', 'is_sample' => false]);
        $base = '/en/admin/committees/'.$committee->slug;

        foreach ([User::factory()->memberStateRep()->create(), User::factory()->auditor()->create()] as $user) {
            $this->actingAs($user)->post($base.'/members', ['user_id' => $user->id, 'role' => 'chair'])->assertForbidden();
            $this->actingAs($user)->put($base.'/members/'.$person->id, ['role' => 'chair'])->assertForbidden();
            $this->actingAs($user)->delete($base.'/members/'.$person->id)->assertForbidden();
        }

        $this->assertSame('member', $committee->users()->first()->pivot->role);
    }

    public function test_changing_someone_who_is_not_on_the_committee_is_a_404(): void
    {
        $committee = Committee::first();
        $stranger = User::factory()->create();

        $this->actingAs($this->admin)->put('/en/admin/committees/'.$committee->slug.'/members/'.$stranger->id, ['role' => 'chair'])->assertNotFound();
        $this->actingAs($this->admin)->delete('/en/admin/committees/'.$committee->slug.'/members/'.$stranger->id)->assertNotFound();
    }

    /* Indicators ------------------------------------------------------------- */

    private function indicatorPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Fixed broadband subscriptions',
            'category' => 'market',
            'data_type' => 'numeric',
            'definition' => 'Fixed broadband subscriptions per 100 inhabitants.',
            'unit' => 'subscriptions_per_100',
            'periodicity' => 'annual',
            'sector_id' => Sector::first()->id,
            'source_standard' => 'ITU World Telecommunication/ICT Indicators',
            'display_order' => 40,
            'may_exceed_hundred' => 1,
            'is_active' => 1,
        ], $overrides);
    }

    public function test_an_indicator_is_created_with_a_definition_in_three_languages_edited_and_deleted(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/data/indicators', $this->indicatorPayload([
            'localised' => [
                'fr' => ['name' => 'Abonnements au haut débit fixe', 'definition' => 'Abonnements au haut débit fixe pour 100 habitants.'],
                'pt' => ['name' => 'Assinaturas de banda larga fixa', 'definition' => 'Assinaturas de banda larga fixa por 100 habitantes.'],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $indicator = Indicator::where('slug', 'fixed-broadband-subscriptions')->sole();
        $this->assertTrue($indicator->canExceedHundred());
        $this->assertSame(40, (int) $indicator->display_order);
        $this->assertSame('Assinaturas de banda larga fixa por 100 habitantes.', $this->inLocale('pt', fn () => $indicator->fresh()->definition));

        $this->actingAs($this->admin)->put('/en/admin/data/indicators/'.$indicator->id, $this->indicatorPayload([
            'unit' => 'percent', 'may_exceed_hundred' => 0,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('percent', $indicator->fresh()->unit);
        $this->assertFalse($indicator->fresh()->canExceedHundred());
        $this->assertTrue(AuditLog::where('auditable_type', Indicator::class)->where('auditable_id', $indicator->id)->where('action', AuditLog::UPDATED)->exists());

        $this->actingAs($this->admin)->delete('/en/admin/data/indicators/'.$indicator->id, ['confirm' => 'Fixed broadband subscriptions'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('indicators', ['id' => $indicator->id]);
        $this->assertDatabaseMissing('translations', ['translatable_type' => Indicator::class, 'translatable_id' => $indicator->id]);
    }

    public function test_an_indicator_with_values_is_refused_naming_the_count(): void
    {
        $indicator = Indicator::factory()->create(['name' => 'Mobile penetration']);
        foreach (['bw', 'na', 'za'] as $iso) {
            IndicatorValue::factory()->create(['indicator_id' => $indicator->id, 'country_id' => Country::where('iso_code', $iso)->value('id')]);
        }

        $this->actingAs($this->admin)->delete('/en/admin/data/indicators/'.$indicator->id, ['confirm' => 'Mobile penetration'])
            ->assertSessionHasErrors(['record' => 'This indicator has 3 reported values; move or delete them first.']);
        $this->assertDatabaseHas('indicators', ['id' => $indicator->id]);
    }

    public function test_an_editor_or_auditor_cannot_create_or_delete_an_indicator(): void
    {
        $indicator = Indicator::factory()->create();

        foreach ([User::factory()->editor()->create(), User::factory()->auditor()->create()] as $user) {
            $this->actingAs($user)->post('/en/admin/data/indicators', $this->indicatorPayload())->assertForbidden();
            $this->actingAs($user)->put('/en/admin/data/indicators/'.$indicator->id, $this->indicatorPayload())->assertForbidden();
            $this->actingAs($user)->delete('/en/admin/data/indicators/'.$indicator->id, ['confirm' => $indicator->rawName()])->assertForbidden();
        }
        $this->assertDatabaseHas('indicators', ['id' => $indicator->id]);
    }

    /* Instruments ------------------------------------------------------------ */

    private function instrumentPayload(array $overrides = []): array
    {
        return array_merge([
            'reference_number' => 'Act 8 of 2026',
            'country_id' => Country::where('iso_code', 'na')->value('id'),
            'sector_id' => Sector::first()->id,
            'instrument_type' => 'act',
            'status' => 'draft',
            'en' => ['title' => 'Communications Amendment Act'],
            'fr' => ['title' => 'Loi portant modification des communications'],
            'pt' => ['title' => 'Lei de alteração das comunicações'],
        ], $overrides);
    }

    public function test_an_instrument_is_created_edited_and_deleted(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/data/instruments', $this->instrumentPayload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $instrument = Instrument::where('reference_number', 'Act 8 of 2026')->sole();
        $this->assertSame('Lei de alteração das comunicações', $instrument->translated('title', '', 'pt'));

        $this->actingAs($this->admin)->put('/en/admin/data/instruments/'.$instrument->id, $this->instrumentPayload(['reference_number' => 'Act 9 of 2026']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Act 9 of 2026', $instrument->fresh()->reference_number);

        $this->actingAs($this->admin)->delete('/en/admin/data/instruments/'.$instrument->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSoftDeleted('instruments', ['id' => $instrument->id]);
    }

    public function test_an_instrument_others_name_as_their_replacement_is_refused(): void
    {
        $later = Instrument::factory()->create();
        Instrument::factory()->count(2)->create(['superseded_by_id' => $later->id]);

        $this->actingAs($this->admin)->delete('/en/admin/data/instruments/'.$later->id)
            ->assertSessionHasErrors(['record' => 'This instrument has 2 instruments it supersedes; move or delete them first.']);
        $this->assertNotSoftDeleted('instruments', ['id' => $later->id]);
    }

    public function test_an_auditor_cannot_change_an_instrument_and_an_editor_cannot_delete_one(): void
    {
        $instrument = Instrument::factory()->create();
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)->post('/en/admin/data/instruments', $this->instrumentPayload())->assertForbidden();
        $this->actingAs($auditor)->put('/en/admin/data/instruments/'.$instrument->id, $this->instrumentPayload())->assertForbidden();
        $this->actingAs($auditor)->delete('/en/admin/data/instruments/'.$instrument->id)->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())->delete('/en/admin/data/instruments/'.$instrument->id)->assertForbidden();
        $this->assertNotSoftDeleted('instruments', ['id' => $instrument->id]);
    }
}
