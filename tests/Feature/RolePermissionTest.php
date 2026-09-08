<?php

namespace Tests\Feature;

use App\Enums\Grant;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Country;
use App\Models\User;
use App\Support\PermissionMatrix;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\CountryRecord;
use Tests\TestCase;

/**
 * Authorisation is server-side on every route. These tests hit real routes
 * through the real middleware stack, because hiding a nav item is not access
 * control and a policy that is never called protects nothing.
 *
 * The routes are defined here rather than in the application: the interface
 * is built later, against screens 11 and 13, and this task proves the
 * authorisation layer those screens will sit on.
 */
class RolePermissionTest extends TestCase
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

        Route::middleware('web')->group(function () {
            Route::get('/t/records/{record}', function (int $record) {
                $row = CountryRecord::withoutGlobalScopes()->findOrFail($record);
                abort_unless(request()->user()->mayActOnCountry($row->country_id), 403);

                return response('ok');
            })->name('t.records.show');

            Route::put('/t/records/{record}', function (int $record) {
                $row = CountryRecord::withoutGlobalScopes()->findOrFail($record);
                abort_unless(request()->user()->mayActOnCountry($row->country_id), 403);

                return response('saved');
            })->name('t.records.update');

            Route::get('/t/users', function () {
                abort_unless(request()->user()->can('viewAny', User::class), 403);

                return response('user management');
            })->name('t.users.index');

            Route::get('/t/settings', function () {
                abort_unless(request()->user()->can(Permission::ManageSettings->value), 403);

                return response('settings');
            })->name('t.settings');

            Route::get('/t/unpublished', function () {
                abort_unless(request()->user()->can(Permission::ManageContent->value), 403);

                return response('draft');
            })->name('t.unpublished');

            Route::get('/t/audit', function () {
                abort_unless(request()->user()->can(Permission::ReadAuditLog->value), 403);

                return response('audit');
            })->name('t.audit');
        });
    }

    private function record(Country $country): int
    {
        return CountryRecord::withoutGlobalScopes()->create([
            'country_id' => $country->id,
            'title' => $country->name.' return',
        ])->id;
    }

    /* Country scope, over HTTP -------------------------------------------- */

    public function test_a_representative_gets_403_reading_another_countrys_data(): void
    {
        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create())
            ->get('/t/records/'.$this->record($this->zambia))
            ->assertForbidden();
    }

    public function test_a_representative_gets_403_writing_another_countrys_data(): void
    {
        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create())
            ->put('/t/records/'.$this->record($this->zambia))
            ->assertForbidden();
    }

    public function test_a_representative_reaches_their_own_countrys_data(): void
    {
        $rep = User::factory()->memberStateRep($this->namibia)->create();

        $this->actingAs($rep)->get('/t/records/'.$this->record($this->namibia))->assertOk();
        $this->actingAs($rep)->put('/t/records/'.$this->record($this->namibia))->assertOk();
    }

    public function test_an_administrator_reaches_any_countrys_data(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get('/t/records/'.$this->record($this->zambia))
            ->assertOk();
    }

    /* Role boundaries ------------------------------------------------------ */

    public function test_an_editor_gets_403_on_user_management(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get('/t/users')
            ->assertForbidden();
    }

    public function test_an_editor_gets_403_on_settings(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get('/t/settings')
            ->assertForbidden();
    }

    public function test_an_editor_reaches_content(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get('/t/unpublished')
            ->assertOk();
    }

    public function test_a_viewer_gets_403_on_anything_not_published(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/t/unpublished')->assertForbidden();
        $this->actingAs($viewer)->get('/t/users')->assertForbidden();
        $this->actingAs($viewer)->get('/t/settings')->assertForbidden();
        $this->actingAs($viewer)->get('/t/audit')->assertForbidden();
    }

    public function test_a_representative_gets_403_on_user_management_and_the_audit_log(): void
    {
        $rep = User::factory()->memberStateRep($this->namibia)->create();

        $this->actingAs($rep)->get('/t/users')->assertForbidden();
        $this->actingAs($rep)->get('/t/audit')->assertForbidden();
    }

    public function test_only_an_administrator_reads_the_audit_log(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->get('/t/audit')->assertOk();
        $this->actingAs(User::factory()->editor()->create())->get('/t/audit')->assertForbidden();
    }

    /* The matrix itself ---------------------------------------------------- */

    public function test_the_matrix_matches_screen_13_for_every_role_and_permission(): void
    {
        $expected = [
            Role::Administrator->value => [
                Permission::ManageContent->value => Grant::Granted,
                Permission::DeleteContent->value => Grant::Granted,
                Permission::ValidateSubmission->value => Grant::Granted,
                Permission::SubmitCountryReturn->value => Grant::Denied,
                Permission::ManageUsers->value => Grant::Granted,
                Permission::ReadAuditLog->value => Grant::Granted,
            ],
            Role::Editor->value => [
                Permission::ManageContent->value => Grant::Granted,
                Permission::DeleteContent->value => Grant::Denied,
                Permission::ValidateSubmission->value => Grant::Denied,
                Permission::ManageUsers->value => Grant::Denied,
                Permission::ManageSettings->value => Grant::Denied,
                Permission::OpenConfidentialDocuments->value => Grant::Denied,
            ],
            Role::MemberStateRep->value => [
                Permission::SubmitCountryReturn->value => Grant::Conditional,
                Permission::ExportReports->value => Grant::Conditional,
                Permission::OpenMemberDocuments->value => Grant::Conditional,
                Permission::UploadToCountryFolder->value => Grant::Conditional,
                Permission::ManageContent->value => Grant::Denied,
                Permission::ValidateSubmission->value => Grant::Denied,
            ],
            Role::Viewer->value => [
                Permission::ViewPublishedContent->value => Grant::Granted,
                Permission::ViewPublishedIndicators->value => Grant::Granted,
                Permission::OpenMemberDocuments->value => Grant::Denied,
                Permission::ReachCommitteeWorkspace->value => Grant::Denied,
                Permission::ExportReports->value => Grant::Denied,
            ],
            Role::CommitteeMember->value => [
                Permission::ViewPublishedContent->value => Grant::Granted,
                Permission::ReachCommitteeWorkspace->value => Grant::Conditional,
                Permission::OpenMemberDocuments->value => Grant::Conditional,
                Permission::OpenConfidentialDocuments->value => Grant::Denied,
                Permission::ManageContent->value => Grant::Denied,
            ],
        ];

        foreach ($expected as $roleValue => $permissions) {
            foreach ($permissions as $permissionValue => $grant) {
                $this->assertSame(
                    $grant,
                    PermissionMatrix::grant(Role::from($roleValue), Permission::from($permissionValue)),
                    $roleValue.' / '.$permissionValue
                );
            }
        }
    }

    public function test_every_role_has_a_grant_for_every_permission(): void
    {
        foreach (Role::cases() as $role) {
            foreach (Permission::cases() as $permission) {
                $this->assertInstanceOf(
                    Grant::class,
                    PermissionMatrix::grant($role, $permission),
                    'No grant defined for '.$role->value.' / '.$permission->value
                );
            }
        }
    }
}
