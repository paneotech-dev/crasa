<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\Submission;
use App\Models\User;
use App\Services\IndicatorImport;
use App\Services\SubmissionWorkflow;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Bulk import and export, RP-08 and BUILD_SPEC 6.2.
 *
 * The property that matters: an upload with one bad row writes nothing at
 * all. A half-imported dataset is worse than a rejected one, because
 * nobody can tell which half is real, and the platform's whole claim is
 * that its figures can be defended.
 */
class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    private User $representative;

    private Submission $submission;

    private Indicator $indicator;

    private Indicator $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $botswana = Country::where('iso_code', 'bw')->sole();
        $this->representative = User::factory()->memberStateRep($botswana)->create();
        $this->submission = app(SubmissionWorkflow::class)->open($botswana->id, '2026', $this->representative);

        [$this->indicator, $this->second] = Indicator::active()->orderBy('display_order')->take(2)->get()->all();
    }

    private function csv(array $rows): UploadedFile
    {
        $lines = ['Indicator,Reference,Unit,Value,Source'];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"', $row));
        }

        $path = tempnam(sys_get_temp_dir(), 'crasa').'.csv';
        file_put_contents($path, implode("\n", $lines));

        return new UploadedFile($path, 'return.csv', 'text/csv', null, true);
    }

    /* The template ------------------------------------------------------------ */

    public function test_the_template_carries_every_indicator_with_its_reference(): void
    {
        $csv = $this->actingAs($this->representative)
            ->get('/en/submissions/template.csv')
            ->assertOk()
            ->streamedContent();

        foreach (Indicator::active()->get() as $indicator) {
            $this->assertStringContainsString($indicator->slug, $csv);
            $this->assertStringContainsString($indicator->name, $csv);
        }

        // Pre-filled with the reference, so nobody has to know a slug and
        // nobody can invent one.
        $this->assertStringContainsString(__('import.col_slug'), $csv);
    }

    public function test_a_template_can_be_taken_one_category_at_a_time(): void
    {
        $csv = $this->actingAs($this->representative)
            ->get('/en/submissions/template.csv?category='.$this->indicator->category)
            ->assertOk()
            ->streamedContent();

        $other = Indicator::active()->where('category', '!=', $this->indicator->category)->first();

        $this->assertStringContainsString($this->indicator->slug, $csv);

        if ($other) {
            $this->assertStringNotContainsString($other->slug, $csv);
        }
    }

    /* Reading before writing --------------------------------------------------- */

    public function test_an_upload_writes_nothing_until_it_is_confirmed(): void
    {
        $this->actingAs($this->representative)
            ->from('/en/submissions/'.$this->submission->id.'/import')
            ->post('/en/submissions/'.$this->submission->id.'/import', [
                'file' => $this->csv([[$this->indicator->name, $this->indicator->slug, '', '42', 'BOCRA register']]),
            ])
            ->assertRedirect();

        // Read and reported on. Nothing in the return yet.
        $this->assertDatabaseCount('submission_values', 0);
    }

    public function test_confirming_writes_the_rows(): void
    {
        $this->actingAs($this->representative)
            ->from('/en/submissions/'.$this->submission->id.'/import')
            ->post('/en/submissions/'.$this->submission->id.'/import', [
                'file' => $this->csv([
                    [$this->indicator->name, $this->indicator->slug, '', '42', 'BOCRA register'],
                    [$this->second->name, $this->second->slug, '', '7', 'Annual report'],
                ]),
            ]);

        $this->actingAs($this->representative)
            ->post('/en/submissions/'.$this->submission->id.'/import/commit')
            ->assertRedirect();

        $this->assertDatabaseCount('submission_values', 2);
        $this->assertDatabaseHas('submission_values', [
            'indicator_id' => $this->indicator->id,
            'source_note' => 'BOCRA register',
        ]);
    }

    /* The property that matters ------------------------------------------------ */

    public function test_one_bad_row_stops_the_whole_file(): void
    {
        $this->actingAs($this->representative)
            ->from('/en/submissions/'.$this->submission->id.'/import')
            ->post('/en/submissions/'.$this->submission->id.'/import', [
                'file' => $this->csv([
                    [$this->indicator->name, $this->indicator->slug, '', '42', 'BOCRA register'],
                    [$this->second->name, $this->second->slug, '', 'n/a', 'Annual report'],
                ]),
            ]);

        $this->actingAs($this->representative)
            ->from('/en/submissions/'.$this->submission->id.'/import')
            ->post('/en/submissions/'.$this->submission->id.'/import/commit')
            ->assertSessionHasErrors('file');

        // Not one row. The good row is not written either, because a
        // half-imported dataset is worse than a rejected one.
        $this->assertDatabaseCount('submission_values', 0);
    }

    /* The error report --------------------------------------------------------- */

    public function test_an_error_names_the_row_the_column_and_the_reason(): void
    {
        $result = app(IndicatorImport::class)->inspect(
            $this->csv([[$this->indicator->name, $this->indicator->slug, '', 'n/a', 'BOCRA register']]),
            $this->submission
        );

        $error = $result['errors']->sole();

        // CLAUDE.md's own example: "Row 14: mobile termination rate must be
        // a number in local currency. Found 'n/a'."
        $this->assertSame(2, $error['line']);
        $this->assertSame(__('import.col_value'), $error['column']);
        $this->assertSame($this->indicator->name, $error['indicator']);
        $this->assertStringContainsString('n/a', $error['reason']);
    }

    public function test_an_unknown_reference_is_reported_rather_than_ignored(): void
    {
        $result = app(IndicatorImport::class)->inspect(
            $this->csv([['Something else', 'not-an-indicator', '', '5', 'A source']]),
            $this->submission
        );

        $this->assertStringContainsString('not-an-indicator', $result['errors']->sole()['reason']);
        $this->assertCount(0, $result['rows']);
    }

    public function test_a_figure_without_a_source_is_an_error_here_too(): void
    {
        $result = app(IndicatorImport::class)->inspect(
            $this->csv([[$this->indicator->name, $this->indicator->slug, '', '42', '']]),
            $this->submission
        );

        // The same rule as the form. The import is not a way around it.
        $this->assertSame(__('import.col_source_note'), $result['errors']->sole()['column']);
    }

    public function test_an_empty_row_is_not_an_error(): void
    {
        $result = app(IndicatorImport::class)->inspect(
            $this->csv([
                [$this->indicator->name, $this->indicator->slug, '', '', ''],
                [$this->second->name, $this->second->slug, '', '7', 'Annual report'],
            ]),
            $this->submission
        );

        // A member state with nothing to report for an indicator is a
        // legitimate answer. It publishes as Not reported.
        $this->assertCount(0, $result['errors']);
        $this->assertCount(1, $result['rows']);
    }

    /* Language ----------------------------------------------------------------- */

    public function test_a_template_downloaded_in_french_imports_without_translation(): void
    {
        $lines = [
            implode(',', [__('import.col_indicator', [], 'fr'), __('import.col_slug', [], 'fr'), __('import.col_unit', [], 'fr'), __('import.col_value', [], 'fr'), __('import.col_source_note', [], 'fr')]),
            '"'.$this->indicator->name.'","'.$this->indicator->slug.'","","42","Registre BOCRA"',
        ];

        $path = tempnam(sys_get_temp_dir(), 'crasa').'.csv';
        file_put_contents($path, implode("\n", $lines));

        $result = app(IndicatorImport::class)->inspect(
            new UploadedFile($path, 'retour.csv', 'text/csv', null, true),
            $this->submission
        );

        // Nobody should have to know that the English word was the real one.
        $this->assertCount(0, $result['errors']);
        $this->assertCount(1, $result['rows']);
    }

    /* Export, RP-08 ------------------------------------------------------------ */

    public function test_the_export_carries_every_indicator_and_says_which_are_unreported(): void
    {
        $this->submission->values()->create([
            'indicator_id' => $this->indicator->id,
            'value_numeric' => 42,
            'source_note' => 'BOCRA register',
        ]);

        $this->submission->refresh()->load('values.indicator', 'country');

        $csv = app(IndicatorImport::class)->exportCsv($this->submission);

        $this->assertStringContainsString('42', $csv);
        $this->assertStringContainsString('BOCRA register', $csv);

        // An exported blank comes back as a zero the moment anyone totals
        // the column.
        $this->assertStringContainsString(__('comparator.not_reported'), $csv);
    }

    public function test_the_json_export_is_the_same_figures(): void
    {
        $this->submission->values()->create([
            'indicator_id' => $this->indicator->id,
            'value_numeric' => 42,
            'source_note' => 'BOCRA register',
        ]);

        $this->submission->refresh()->load('values.indicator', 'country');

        $json = json_decode(app(IndicatorImport::class)->exportJson($this->submission), true);

        $this->assertSame('bw', $json['country']);
        $this->assertSame('2026', $json['period']);

        $row = collect($json['values'])->firstWhere('slug', $this->indicator->slug);

        $this->assertSame('42', $row['value']);
        $this->assertSame('BOCRA register', $row['source_note']);
    }

    /* Access -------------------------------------------------------------------- */

    public function test_a_representative_cannot_import_into_another_countrys_return(): void
    {
        $zambia = Country::where('iso_code', 'zm')->sole();
        $other = User::factory()->memberStateRep($zambia)->create();
        $zambian = app(SubmissionWorkflow::class)->open($zambia->id, '2026', $other);

        $this->actingAs($this->representative)
            ->post('/en/submissions/'.$zambian->id.'/import', [
                'file' => $this->csv([[$this->indicator->name, $this->indicator->slug, '', '999', 'invented']]),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('submission_values', 0);
    }

    public function test_a_submitted_return_cannot_be_imported_into(): void
    {
        $this->submission->values()->create([
            'indicator_id' => $this->indicator->id,
            'value_numeric' => 1,
            'source_note' => 'x',
        ]);

        app(SubmissionWorkflow::class)->transition(
            $this->submission->refresh()->load('values.indicator'),
            SubmissionStatus::Submitted,
            $this->representative
        );

        $this->actingAs($this->representative)
            ->post('/en/submissions/'.$this->submission->id.'/import', [
                'file' => $this->csv([[$this->indicator->name, $this->indicator->slug, '', '999', 'changed']]),
            ])
            ->assertForbidden();
    }
}
