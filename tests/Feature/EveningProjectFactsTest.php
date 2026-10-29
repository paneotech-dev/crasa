<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S8 remainder: programme, funding partner, countries, lead committee and
 * milestones in the back office; the public card and page show them only
 * when they hold something.
 */
class EveningProjectFactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
    }

    private function project(array $attributes = []): Project
    {
        $project = Project::create($attributes + [
            'slug' => 'harmonised-numbering', 'state' => 'current', 'status' => 'published', 'published_at' => now()->subDay(),
        ]);
        $project->setTranslations('en', ['title' => 'Harmonised numbering plan']);

        return $project->fresh();
    }

    public function test_an_empty_project_shows_no_timeline_and_no_empty_facts(): void
    {
        $this->project();

        $page = $this->get('/en/projects/harmonised-numbering')->assertOk();
        $page->assertDontSee('cp-proj-milestones', false)
            ->assertDontSee(__('evening.project_funding_partner'))
            ->assertDontSee(__('evening.project_lead_committee'))
            ->assertDontSee(__('evening.project_countries'));
        $this->get('/en/projects')->assertOk()->assertDontSee('cp-proj-time__bar', false);
    }

    public function test_filled_facts_show_on_the_card_and_page_with_a_milestones_timeline(): void
    {
        $committee = Committee::factory()->create(['name' => 'Numbering Committee']);
        $project = $this->project([
            'programme' => 'EGEE-ICT', 'funding_partner' => 'Example Fund', 'countries' => 'Botswana, Malawi',
            'lead_committee_id' => $committee->id, 'starts_on' => '2025-01-01', 'ends_on' => '2027-12-31',
        ]);
        $project->setTranslations('en', ['milestones' => "January 2025 | Inception\nMarch 2026: Validation workshop\nFinal report"]);

        $this->get('/en/projects')->assertOk()->assertSee('EGEE-ICT')->assertSee('cp-proj-time__bar', false);

        $html = $this->get('/en/projects/harmonised-numbering')->assertOk()->getContent();
        foreach (['EGEE-ICT', 'Example Fund', 'Botswana, Malawi', 'Numbering Committee', 'cp-proj-milestones', 'Inception', 'Validation workshop', 'Final report'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertSame([
            ['when' => 'January 2025', 'label' => 'Inception', 'lang' => null],
            ['when' => 'March 2026', 'label' => 'Validation workshop', 'lang' => null],
            ['when' => null, 'label' => 'Final report', 'lang' => null],
        ], $project->fresh()->milestoneList());
    }

    public function test_the_back_office_edits_the_new_fields(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = $this->project();

        $this->actingAs($admin)->get('/en/admin/data/projects')->assertOk()->assertSee('Harmonised numbering plan');
        $this->actingAs($admin)->get("/en/admin/data/projects/{$project->id}")->assertOk()
            ->assertSee('name="programme"', false)->assertSee('name="funding_partner"', false)
            ->assertSee('name="countries"', false)->assertSee('name="lead_committee_id"', false)
            ->assertSee('name="en[milestones]"', false);

        $this->actingAs($admin)->put("/en/admin/data/projects/{$project->id}", [
            'state' => 'current', 'status' => 'published', 'programme' => 'EGEE-ICT', 'countries' => 'Namibia',
            'starts_on' => '2026-01-01', 'ends_on' => '2025-01-01',
            'en' => ['title' => 'Harmonised numbering plan', 'milestones' => 'May 2026 | Draft plan'],
        ])->assertSessionHasErrors('ends_on');

        $this->actingAs($admin)->put("/en/admin/data/projects/{$project->id}", [
            'state' => 'current', 'status' => 'published', 'programme' => 'EGEE-ICT', 'countries' => 'Namibia',
            'en' => ['title' => 'Harmonised numbering plan', 'milestones' => 'May 2026 | Draft plan'],
        ])->assertSessionHasNoErrors();

        $fresh = $project->fresh();
        $this->assertSame('EGEE-ICT', $fresh->programme);
        $this->assertSame(['Namibia'], $fresh->countryList());
        $this->assertSame('Draft plan', $fresh->milestoneList()[0]['label']);
    }

    public function test_an_editor_cannot_delete_and_a_viewer_cannot_open_projects(): void
    {
        $project = $this->project();
        $this->actingAs(User::factory()->viewer()->create())->get('/en/admin/data/projects')->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())->get('/en/admin/data/projects')->assertOk();
        $this->actingAs(User::factory()->editor()->create())->delete("/en/admin/data/projects/{$project->id}")->assertForbidden();
    }

    public function test_the_migration_adds_columns_and_no_values(): void
    {
        $this->project();
        $row = \DB::table('projects')->first();
        foreach (['programme', 'funding_partner', 'countries', 'lead_committee_id'] as $column) {
            $this->assertTrue(property_exists($row, $column), $column);
            $this->assertNull($row->{$column});
        }
    }
}
