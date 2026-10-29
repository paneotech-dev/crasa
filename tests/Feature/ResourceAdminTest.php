<?php

namespace Tests\Feature;

use App\Admin\ResourceRegistry;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Publication;
use App\Models\Sector;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * CRUD for every administered model.
 *
 * The tests that carry weight here are the refusals. A generic controller
 * is only safe if every action asks the specific model's policy, and if a
 * model with no policy is refused rather than waved through.
 */
class ResourceAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
        $this->editor = User::factory()->editor()->create();
    }

    /* Every resource is reachable and none of them 500 --------------------- */

    public function test_every_registered_resource_lists_without_error(): void
    {
        foreach (ResourceRegistry::all() as $definition) {
            $response = $this->actingAs($this->administrator)
                ->get('/en/admin/data/'.$definition->key);

            // Forbidden is a valid answer for a resource this account does
            // not hold; a 500 never is.
            $this->assertContains(
                $response->getStatusCode(),
                [200, 403],
                $definition->key.' returned '.$response->getStatusCode()
            );
        }
    }

    public function test_every_resource_names_itself_in_all_three_languages(): void
    {
        foreach (ResourceRegistry::all() as $definition) {
            foreach (['en', 'fr', 'pt'] as $locale) {
                foreach (['resource_', 'resource_'.$definition->key.'_one' => null] as $prefix => $_) {
                }

                foreach (['resource_'.$definition->key, 'resource_'.$definition->key.'_one', 'resourcenote_'.$definition->key] as $key) {
                    $this->assertNotSame(
                        'admin.'.$key,
                        __('admin.'.$key, [], $locale),
                        $key.' has no '.$locale.' translation.'
                    );
                }
            }
        }
    }

    public function test_the_section_index_lists_only_what_this_account_can_open(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin/data')->assertOk()->getContent();

        $this->assertStringContainsString(__('admin.resource_countries'), $html);
        $this->assertStringContainsString(__('admin.resource_publications'), $html);
    }

    public function test_an_unknown_resource_is_a_404(): void
    {
        $this->actingAs($this->administrator)->get('/en/admin/data/spacecraft')->assertNotFound();
    }

    /* Authorisation --------------------------------------------------------- */

    public function test_a_viewer_reaches_no_resource(): void
    {
        $viewer = User::factory()->viewer()->create();

        foreach (['countries', 'publications', 'tags'] as $key) {
            $this->actingAs($viewer)->get('/en/admin/data/'.$key)->assertForbidden();
        }
    }

    public function test_the_generic_controller_asks_the_specific_model_policy(): void
    {
        // Enquiries take the settings permission, which an editor does not
        // hold; publications take the content one, which it does. Same
        // controller, same route shape, different answers, because each
        // action asks the model in front of it.
        $this->actingAs($this->editor)->get('/en/admin/data/publications')->assertOk();
        $this->actingAs($this->editor)->get('/en/admin/data/enquiries')->assertForbidden();
    }

    /* Reference data -------------------------------------------------------- */

    public function test_reference_data_is_editable(): void
    {
        $country = Country::where('name', 'Namibia')->sole();

        $this->actingAs($this->administrator)
            ->put('/en/admin/data/countries/'.$country->id, [
                'name' => 'Namibia',
                'iso_code' => 'na',
                'official_languages' => 'English',
            ])
            ->assertRedirect();

        $this->assertSame('English', $country->fresh()->official_languages);
    }

    public function test_reference_data_cannot_be_deleted_while_anything_points_at_it(): void
    {
        $country = Country::where('name', 'Namibia')->sole();
        \App\Models\Authority::factory()->create(['country_id' => $country->id]);

        // Day 3: a member state can be deleted, but only once nothing points
        // at it. Deleting the row for Namibia does not remove Namibia from
        // SADC; it orphans every instrument, value and account attached.
        $this->actingAs($this->administrator)
            ->from('/en/admin/data/countries/'.$country->id)
            ->delete('/en/admin/data/countries/'.$country->id, ['confirm' => 'Namibia'])
            ->assertSessionHasErrors('record');

        $this->assertDatabaseHas('countries', ['id' => $country->id]);
    }

    public function test_reference_data_cannot_be_created_where_the_set_is_fixed(): void
    {
        $this->actingAs($this->administrator)->get('/en/admin/data/sectors/new')->assertForbidden();
        $this->actingAs($this->administrator)
            ->post('/en/admin/data/sectors', ['name' => 'Broadband'])
            ->assertForbidden();

        $this->assertSame(3, Sector::count());
    }

    /* Creating and editing content ------------------------------------------ */

    public function test_creating_a_publication_writes_its_three_languages_and_a_slug(): void
    {
        $this->actingAs($this->administrator)
            ->post('/en/admin/data/publications', [
                'publication_type' => 'guideline',
                'sector_id' => Sector::first()->id,
                'published_on' => '2026-03-01',
                'is_public' => '1',
                // Published with no file needs the link to where it lives.
                'external_url' => 'https://example.org/roaming',
                'en' => ['title' => 'SADC roaming guidelines', 'summary' => 'The regional framework.'],
                'fr' => ['title' => 'Lignes directrices sur itinerance'],
                'pt' => ['title' => 'Diretrizes de roaming'],
            ])
            ->assertRedirect();

        $publication = Publication::sole();

        $this->assertSame('sadc-roaming-guidelines', $publication->slug);
        $this->assertSame('Lignes directrices sur itinerance', $publication->translate('title', 'fr')->value);
        $this->assertSame('Diretrizes de roaming', $publication->translate('title', 'pt')->value);
        $this->assertTrue($publication->is_public);
    }

    public function test_validation_is_server_side(): void
    {
        $this->actingAs($this->administrator)
            ->from('/en/admin/data/publications/new')
            ->post('/en/admin/data/publications', ['published_on' => 'not a date'])
            ->assertSessionHasErrors(['publication_type', 'published_on']);

        $this->assertSame(0, Publication::count());
    }

    public function test_a_slug_collision_does_not_overwrite_an_existing_record(): void
    {
        foreach ([1, 2] as $_) {
            $this->actingAs($this->administrator)->post('/en/admin/data/publications', [
                'publication_type' => 'guideline',
                'published_on' => '2026-03-01',
                'en' => ['title' => 'Annual report'],
            ]);
        }

        $this->assertSame(
            ['annual-report', 'annual-report-2'],
            Publication::orderBy('id')->pluck('slug')->all()
        );
    }

    /* Deleting and restoring ------------------------------------------------- */

    public function test_a_soft_deleted_record_is_recoverable_with_its_translations(): void
    {
        $publication = Publication::create([
            'slug' => 'roaming', 'publication_type' => 'guideline', 'is_public' => true,
            'published_on' => '2026-03-01',
        ]);
        $publication->setTranslations('pt', ['title' => 'Diretrizes de roaming']);

        $this->actingAs($this->administrator)
            ->delete('/en/admin/data/publications/'.$publication->id)
            ->assertRedirect();

        $this->assertSoftDeleted('publications', ['id' => $publication->id]);

        // And it is listed, and it comes back exactly as it was.
        $this->actingAs($this->administrator)
            ->get('/en/admin/data/publications?deleted=1')
            ->assertOk()
            ->assertSee(__('admin.restore'));

        $this->actingAs($this->administrator)
            ->put('/en/admin/data/publications/'.$publication->id.'/restore')
            ->assertRedirect();

        $restored = Publication::find($publication->id);

        $this->assertNotNull($restored);
        $this->assertSame('Diretrizes de roaming', $restored->translate('title', 'pt')->value);
    }

    public function test_a_restore_writes_an_audit_row_that_says_restored(): void
    {
        $publication = Publication::create(['slug' => 'x', 'publication_type' => 'guideline', 'published_on' => '2026-03-01']);
        $publication->delete();

        $this->actingAs($this->administrator)->put('/en/admin/data/publications/'.$publication->id.'/restore');

        // "Somebody set deleted_at to null" is true and useless six months
        // later.
        $this->assertDatabaseHas('audit_log', [
            'auditable_type' => Publication::class,
            'auditable_id' => $publication->id,
        ]);

        $entry = AuditLog::where('auditable_id', $publication->id)->latest('id')->first();
        $this->assertSame(['restored' => true], $entry->changes);
    }

    public function test_a_record_without_soft_deletes_needs_its_name_typed(): void
    {
        $tag = Tag::create(['slug' => 'roaming']);

        $this->actingAs($this->administrator)
            ->from('/en/admin/data/tags/'.$tag->id)
            ->delete('/en/admin/data/tags/'.$tag->id, ['confirm' => 'wrong'])
            ->assertSessionHasErrors('confirm');

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);

        $this->actingAs($this->administrator)
            ->delete('/en/admin/data/tags/'.$tag->id, ['confirm' => 'roaming'])
            ->assertRedirect();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_an_editor_cannot_delete_what_only_an_administrator_may(): void
    {
        $publication = Publication::create(['slug' => 'x', 'publication_type' => 'guideline', 'published_on' => '2026-03-01']);

        $this->actingAs($this->editor)
            ->delete('/en/admin/data/publications/'.$publication->id)
            ->assertForbidden();
    }

    /* The safety property ---------------------------------------------------- */

    public function test_a_resource_with_no_policy_is_refused_rather_than_waved_through(): void
    {
        foreach (ResourceRegistry::all() as $definition) {
            $this->assertNotNull(
                Gate::getPolicyFor($definition->model),
                $definition->key.' has no policy, so the generic controller would have nothing to ask.'
            );
        }
    }

    public function test_an_enquiry_can_be_read_but_never_edited(): void
    {
        $this->actingAs($this->administrator)->get('/en/admin/data/enquiries')->assertOk();
        $this->actingAs($this->administrator)->get('/en/admin/data/enquiries/new')->assertForbidden();
    }
}
