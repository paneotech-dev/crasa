<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportDocument;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regional briefs, RP-12.
 *
 * BUILD_SPEC 5.5: "This is the artefact that ends up in ministerial
 * briefings." So the tests are about whether it can be defended: does
 * every figure carry its source, does a missing figure say so rather than
 * read as a zero, and does the demonstration notice reach the PDF.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $secretariat;

    private Indicator $indicator;

    private Country $botswana;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->secretariat = User::factory()->administrator()->create();
        $this->indicator = Indicator::active()->orderBy('display_order')->firstOrFail();
        $this->botswana = Country::where('iso_code', 'bw')->sole();

        IndicatorValue::create([
            'indicator_id' => $this->indicator->id,
            'country_id' => $this->botswana->id,
            'period' => '2025',
            'value_numeric' => 168.4,
            'confidence' => 'validated',
            'source_note' => 'BOCRA annual report 2025',
            'is_sample' => false,
        ]);
    }

    private function report(string $type = Report::COUNTRY, ?string $key = null): Report
    {
        return Report::create([
            'subject_type' => $type,
            'subject_key' => $key ?? ($type === Report::COUNTRY ? 'bw' : $this->indicator->slug),
            'title' => 'Test brief',
            'locale' => 'en',
            'generated_by' => $this->secretariat->id,
        ]);
    }

    /* Generating -------------------------------------------------------------- */

    public function test_a_brief_is_one_click_on_a_member_state(): void
    {
        $this->actingAs($this->secretariat)
            ->post('/en/admin/reports', ['subject_type' => 'country', 'subject_key' => 'bw'])
            ->assertRedirect();

        $report = Report::sole();

        $this->assertSame('country', $report->subject_type);
        $this->assertStringContainsString('Botswana', $report->title);
    }

    public function test_a_brief_is_one_click_on_an_indicator(): void
    {
        $this->actingAs($this->secretariat)
            ->post('/en/admin/reports', ['subject_type' => 'indicator', 'subject_key' => $this->indicator->slug])
            ->assertRedirect();

        $this->assertStringContainsString($this->indicator->name, Report::sole()->title);
    }

    public function test_an_unknown_subject_is_refused(): void
    {
        $this->actingAs($this->secretariat)
            ->post('/en/admin/reports', ['subject_type' => 'country', 'subject_key' => 'xx'])
            ->assertStatus(422);

        $this->assertSame(0, Report::count());
    }

    /* What the brief says ------------------------------------------------------ */

    public function test_every_figure_carries_its_source_and_its_period(): void
    {
        $html = $this->actingAs($this->secretariat)
            ->get('/en/admin/reports/'.$this->report()->id)
            ->assertOk()
            ->getContent();

        // The first question anyone senior asks. A table of unattributed
        // numbers with CRASA's mark on it is worse than no brief.
        $this->assertStringContainsString('BOCRA annual report 2025', $html);
        $this->assertStringContainsString('2025', $html);
        $this->assertStringContainsString('168.4', $html);
    }

    public function test_an_unreported_indicator_says_so_rather_than_reading_as_zero(): void
    {
        $html = $this->actingAs($this->secretariat)
            ->get('/en/admin/reports/'.$this->report()->id)
            ->assertOk()
            ->getContent();

        // Botswana has one figure and thirteen unreported indicators.
        $this->assertStringContainsString(__('comparator.not_reported'), $html);
    }

    public function test_a_brief_on_an_indicator_covers_all_fourteen_member_states(): void
    {
        $html = $this->actingAs($this->secretariat)
            ->get('/en/admin/reports/'.$this->report(Report::INDICATOR)->id)
            ->assertOk()
            ->getContent();

        foreach (Country::pluck('name') as $name) {
            $this->assertStringContainsString($name, $html);
        }
    }

    public function test_the_chart_states_every_figure_so_it_reads_without_colour(): void
    {
        $html = $this->actingAs($this->secretariat)
            ->get('/en/admin/reports/'.$this->report()->id)
            ->assertOk()
            ->getContent();

        // The bar is beside the figure, not instead of it, so the chart
        // needs no separate table equivalent: it is one.
        $this->assertStringContainsString('r-bar', $html);
        $this->assertStringContainsString('168.4', $html);
    }

    /* The document ------------------------------------------------------------- */

    public function test_the_pdf_is_a_real_document_with_a_cover(): void
    {
        $report = $this->report();

        $response = $this->actingAs($this->secretariat)
            ->get('/en/admin/reports/'.$report->id.'/export.pdf')
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // Two pages: a cover and the figures. A one-page table with a logo
        // on top is a printout, not a document.
        $document = app(ReportDocument::class);

        $this->assertStringContainsString(__('reports.cover_kicker'), $document->cover($report));
        $this->assertStringContainsString('BOCRA annual report 2025', $document->body($report));
    }

    public function test_the_pdf_carries_the_date_and_the_permalink(): void
    {
        $report = $this->report();
        $document = app(ReportDocument::class);

        foreach ([$document->cover($report), $document->body($report)] as $page) {
            $this->assertStringContainsString(now()->isoFormat('D MMMM YYYY'), $page);
            $this->assertStringContainsString('/admin/reports/'.$report->id, $page);
        }
    }

    public function test_the_exported_copy_is_stored_so_it_can_be_found_again(): void
    {
        $report = $this->report();

        $this->actingAs($this->secretariat)->get('/en/admin/reports/'.$report->id.'/export.pdf')->assertOk();

        $report->refresh();

        $this->assertNotNull($report->pdf_path);
        $this->assertNotNull($report->exported_at);
        Storage::disk('local')->assertExists($report->pdf_path);
    }

    public function test_the_stored_copy_is_private(): void
    {
        $report = $this->report();
        $this->actingAs($this->secretariat)->get('/en/admin/reports/'.$report->id.'/export.pdf');

        // A brief may carry figures a member state has not yet published,
        // so it goes on the private disk rather than behind a guessable URL.
        $this->assertStringStartsWith('reports/', $report->fresh()->pdf_path);
        Storage::disk('public')->assertMissing($report->fresh()->pdf_path);
    }

    public function test_the_demonstration_notice_reaches_the_document(): void
    {
        IndicatorValue::where('indicator_id', $this->indicator->id)->update(['is_sample' => true]);

        $report = $this->report();
        $document = app(ReportDocument::class);

        $this->assertStringContainsString(__('comparator.sample_notice_short'), $document->cover($report));
        $this->assertStringContainsString(__('comparator.sample_notice_short'), $document->body($report));

        $this->actingAs($this->secretariat)
            ->get('/en/admin/reports/'.$report->id)
            ->assertOk()
            ->assertSee(__('comparator.sample_title'));
    }

    /* Storing and reopening ---------------------------------------------------- */

    public function test_reopening_a_brief_shows_the_current_figures(): void
    {
        $report = $this->report();

        $this->actingAs($this->secretariat)->get('/en/admin/reports/'.$report->id)->assertSee('168.4');

        IndicatorValue::where('indicator_id', $this->indicator->id)->update(['value_numeric' => 171.2]);

        // The report holds what it is about, not what it said. Anyone
        // reopening one wants today's figures; the exported PDF beside it
        // is the copy that was circulated.
        $this->actingAs($this->secretariat)
            ->get('/en/admin/reports/'.$report->id)
            ->assertSee('171.2')
            ->assertDontSee('168.4');
    }

    public function test_the_brief_prints_without_the_interface(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertStringContainsString('.r-brief', $css);
        $this->assertMatchesRegularExpression('/@media print \{[^}]*\.a-header/s', $css);
    }

    /* Access -------------------------------------------------------------------- */

    public function test_an_account_without_the_export_permission_reaches_nothing(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/reports')->assertForbidden();
        $this->actingAs($viewer)
            ->post('/en/admin/reports', ['subject_type' => 'country', 'subject_key' => 'bw'])
            ->assertForbidden();
    }

    /**
     * Screen 13's matrix grants a member state representative ExportReports
     * CONDITIONALLY. The condition is the one running through this whole
     * platform: their own member state and no other.
     */
    public function test_a_representative_may_brief_on_their_own_member_state(): void
    {
        $representative = User::factory()->memberStateRep($this->botswana)->create();

        $this->actingAs($representative)
            ->post('/en/admin/reports', ['subject_type' => 'country', 'subject_key' => 'bw'])
            ->assertRedirect();

        $this->assertSame('bw', Report::sole()->subject_key);
    }

    public function test_a_representative_cannot_brief_on_another_member_state(): void
    {
        $representative = User::factory()->memberStateRep($this->botswana)->create();

        $this->actingAs($representative)
            ->post('/en/admin/reports', ['subject_type' => 'country', 'subject_key' => 'zm'])
            ->assertForbidden();

        $this->assertSame(0, Report::count());
    }

    /**
     * An indicator brief is by definition the figure across all fourteen
     * member states, which is exactly what a conditional grant withholds.
     * There is no version of "your own country's row of a fourteen-country
     * table" that is still that table.
     */
    public function test_a_conditional_holder_cannot_brief_on_an_indicator_at_all(): void
    {
        $representative = User::factory()->memberStateRep($this->botswana)->create();

        $this->actingAs($representative)
            ->post('/en/admin/reports', [
                'subject_type' => 'indicator',
                'subject_key' => $this->indicator->slug,
            ])
            ->assertForbidden();

        // And the form does not offer one either.
        $this->actingAs($representative)
            ->get('/en/admin/reports')
            ->assertOk()
            ->assertSee(__('reports.conditional_note'))
            ->assertDontSee(__('reports.on_indicator'));
    }

    public function test_a_representative_cannot_open_another_member_states_brief(): void
    {
        $representative = User::factory()->memberStateRep($this->botswana)->create();
        $zambian = $this->report(Report::COUNTRY, 'zm');

        $this->actingAs($representative)->get('/en/admin/reports/'.$zambian->id)->assertForbidden();
        $this->actingAs($representative)->get('/en/admin/reports/'.$zambian->id.'/export.pdf')->assertForbidden();
        $this->actingAs($representative)->delete('/en/admin/reports/'.$zambian->id)->assertForbidden();
    }

    public function test_deleting_a_brief_removes_its_exported_copy(): void
    {
        $report = $this->report();
        $this->actingAs($this->secretariat)->get('/en/admin/reports/'.$report->id.'/export.pdf');

        $path = $report->fresh()->pdf_path;

        $this->actingAs($this->secretariat)
            ->delete('/en/admin/reports/'.$report->id)
            ->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, Report::count());
    }
}
