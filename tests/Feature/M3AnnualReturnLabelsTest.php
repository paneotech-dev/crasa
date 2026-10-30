<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\SubmissionStateChanged;
use App\Support\Notifier;
use App\Support\ShellNavigation;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 1f: the member state's data entry is called "Annual return" everywhere it
 * is offered: the sidebar (under Workspace, first after the dashboard), the
 * list, the form, the quick actions and the bell.
 */
class M3AnnualReturnLabelsTest extends TestCase
{
    use RefreshDatabase;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();
    }

    public function test_the_sidebar_shows_annual_return_under_workspace_after_the_dashboard(): void
    {
        $this->actingAs($this->rep);

        foreach (['en' => 'Annual return', 'fr' => 'Déclaration annuelle', 'pt' => 'Declaração anual'] as $locale => $label) {
            app()->setLocale($locale);
            $groups = ShellNavigation::extranet($this->rep);
            $workspace = collect($groups)->firstWhere('key', 'workspace');

            $this->assertSame(['dashboard', 'submissions'], array_slice(array_column($workspace['items'], 'key'), 0, 2));
            $this->assertSame($label, $workspace['items'][1]['label']);
        }
    }

    public function test_the_list_offers_submit_a_new_return_and_names_the_year_and_member_state(): void
    {
        Submission::create(['country_id' => $this->rep->country_id, 'submitted_by' => $this->rep->id, 'period' => '2025', 'status' => 'draft', 'is_sample' => false]);

        $html = $this->actingAs($this->rep)->get('/en/submissions')->assertOk()->getContent();
        $this->assertStringContainsString('Submit a new return', $html);
        foreach (['Open the return', 'Data submission', 'Regulatory data'] as $old) {
            $this->assertStringNotContainsString($old, $html, $old);
        }

        $this->actingAs($this->rep)->get('/en/submissions?period=2025')->assertOk()
            ->assertSee('<title>Annual return, 2025, Botswana', false)
            ->assertSee('Annual return, 2025, Botswana</h2>', false);

        $this->actingAs($this->rep)->get('/fr/submissions')->assertOk()->assertSee('Soumettre une nouvelle déclaration');
        $this->actingAs($this->rep)->get('/pt/submissions')->assertOk()->assertSee('Submeter uma nova declaração');
    }

    public function test_a_secretariat_account_with_a_country_set_does_not_see_its_country_in_the_heading(): void
    {
        $editor = User::factory()->administrator()->create(['country_id' => Country::where('iso_code', 'zm')->value('id')]);
        Submission::create(['country_id' => $this->rep->country_id, 'submitted_by' => $this->rep->id, 'period' => '2025', 'status' => 'draft', 'is_sample' => false]);

        $html = $this->actingAs($editor)->get('/en/submissions?period=2025')->assertOk()->getContent();
        $this->assertStringNotContainsString('Annual return, 2025, Zambia', $html);
        $this->assertStringContainsString(e(__('d3_look.returns_for', ['period' => '2025'])), $html);
    }

    public function test_the_form_title_and_breadcrumb_read_annual_return(): void
    {
        $submission = Submission::create(['country_id' => $this->rep->country_id, 'submitted_by' => $this->rep->id, 'period' => '2025', 'status' => 'draft', 'is_sample' => false]);

        $html = $this->actingAs($this->rep)->get('/en/submissions/'.$submission->id)->assertOk()->getContent();
        $this->assertStringContainsString('Annual return, 2025, Botswana', $html);
        $this->assertStringNotContainsString('Data submission', $html);
        $this->assertStringNotContainsString('Return for 2025', $html);
    }

    public function test_the_dashboard_and_the_bell_use_the_same_wording(): void
    {
        $submission = Submission::create(['country_id' => $this->rep->country_id, 'submitted_by' => $this->rep->id, 'period' => '2025', 'status' => 'validated', 'is_sample' => false]);

        $html = $this->actingAs($this->rep)->get('/en/extranet/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Open the return', $html);
        $this->assertStringNotContainsString('Data submission', $html);

        $this->rep->notify(new SubmissionStateChanged($submission));
        $notification = $this->rep->notifications()->latest()->first();
        $this->assertSame('Annual return, 2025, Botswana: validated.', Notifier::titleOf($notification));
    }

    public function test_a_viewer_still_cannot_open_the_annual_return(): void
    {
        $this->actingAs(User::factory()->viewer()->create())->get('/en/submissions')->assertForbidden();
    }
}
