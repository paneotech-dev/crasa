<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\User;
use App\Support\CompletenessMatrix;
use App\Support\Locales;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BUILD_SPEC 6.1 and DATA_MODEL section 4.
 */
class CompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);
    }

    /* The taxonomy ---------------------------------------------------------- */

    /**
     * The rule this seeder exists under: the taxonomy goes to fourteen
     * regulators for confirmation, so a wrong definition is worse than a
     * missing one. Every indicator cites a published source.
     */
    public function test_every_indicator_cites_a_published_source(): void
    {
        $this->assertGreaterThan(0, Indicator::count());

        foreach (Indicator::all() as $indicator) {
            $this->assertNotEmpty(trim($indicator->source_standard), $indicator->slug.' has no source.');
            $this->assertMatchesRegularExpression(
                '/(ITU|UPU)/',
                $indicator->source_standard,
                $indicator->slug.' does not name a standards body.'
            );
        }
    }

    public function test_every_definition_is_specific_enough_to_report_against(): void
    {
        foreach (Indicator::all() as $indicator) {
            // A definition a regulator can act on is not one short sentence.
            $this->assertGreaterThan(
                80,
                mb_strlen($indicator->definition),
                $indicator->slug.' has a definition too thin to report against.'
            );
        }
    }

    public function test_the_source_url_points_at_the_body_that_published_it(): void
    {
        foreach (Indicator::whereNotNull('source_url')->get() as $indicator) {
            $this->assertMatchesRegularExpression('#^https://(www\.)?(itu|upu)\.int/#', $indicator->source_url);
        }
    }

    /**
     * Two of the six categories have no sourced indicator. That is a finding
     * to carry to the workshop, not a gap to fill by invention, and this
     * test pins it so nobody quietly fills it.
     */
    public function test_unsourced_categories_are_left_empty_rather_than_invented(): void
    {
        $this->assertSame(0, Indicator::where('category', 'spectrum')->count());
        $this->assertSame(0, Indicator::where('category', 'interconnection')->count());
    }

    public function test_the_unique_index_holds(): void
    {
        $indicator = Indicator::first();
        $country = Country::first();

        IndicatorValue::create([
            'indicator_id' => $indicator->id, 'country_id' => $country->id,
            'period' => '2025', 'value_numeric' => 1, 'is_sample' => true,
        ]);

        $this->expectException(QueryException::class);

        IndicatorValue::create([
            'indicator_id' => $indicator->id, 'country_id' => $country->id,
            'period' => '2025', 'value_numeric' => 2, 'is_sample' => true,
        ]);
    }

    /* The matrix ------------------------------------------------------------ */

    public function test_the_matrix_covers_every_member_state(): void
    {
        $matrix = CompletenessMatrix::build();

        $this->assertCount(14, $matrix['countries']);
        $this->assertCount(14, $matrix['totals']);
    }

    /**
     * The demonstration must be uneven, or the dashboard proves nothing.
     */
    public function test_the_seeded_values_are_genuinely_sparse(): void
    {
        $this->seed(IndicatorValueSeeder::class);
        $matrix = CompletenessMatrix::build();

        $percents = collect($matrix['totals'])->pluck('percent');

        $this->assertContains(100, $percents->all(), 'No member state is complete.');
        $this->assertContains(0, $percents->all(), 'No member state is empty, so no gap is demonstrated.');
        $this->assertGreaterThan(6, $percents->unique()->count(), 'The demonstration is too uniform to show anything.');
    }

    public function test_every_seeded_value_is_flagged_as_sample(): void
    {
        $this->seed(IndicatorValueSeeder::class);

        $this->assertGreaterThan(0, IndicatorValue::count());
        $this->assertSame(IndicatorValue::count(), IndicatorValue::sample()->count());
    }

    public function test_every_seeded_value_says_it_is_not_a_return(): void
    {
        $this->seed(IndicatorValueSeeder::class);

        foreach (IndicatorValue::limit(20)->get() as $value) {
            $this->assertStringContainsString('Demonstration', $value->source_note);
        }
    }

    /**
     * Pure SQL over the schema, and a fixed number of queries whatever the
     * grid size. A matrix that queried per cell would fire 14 x 4 times.
     */
    public function test_the_matrix_is_built_in_a_fixed_number_of_queries(): void
    {
        $this->seed(IndicatorValueSeeder::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        CompletenessMatrix::build();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, $queries, "The matrix fired {$queries} queries.");
    }

    public function test_freshness_reflects_the_period_not_the_timestamp(): void
    {
        // A 2019 figure entered this morning is still a 2019 figure.
        $this->assertSame('current', CompletenessMatrix::freshnessOf((string) now()->year));
        $this->assertSame('ageing', CompletenessMatrix::freshnessOf((string) (now()->year - 2)));
        $this->assertSame('stale', CompletenessMatrix::freshnessOf((string) (now()->year - 6)));
        $this->assertSame('none', CompletenessMatrix::freshnessOf(null));
    }

    /* The page --------------------------------------------------------------- */

    public function test_the_dashboard_is_visible_to_members_and_the_secretariat(): void
    {
        $this->seed(IndicatorValueSeeder::class);

        foreach (['administrator', 'editor', 'memberStateRep'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get('/en/completeness')
                ->assertOk();
        }
    }

    public function test_the_dashboard_is_not_public(): void
    {
        $this->get('/en/completeness')->assertRedirect();
    }

    public function test_the_dashboard_renders_in_every_locale(): void
    {
        $this->seed(IndicatorValueSeeder::class);
        $user = User::factory()->editor()->create();

        foreach (Locales::SUPPORTED as $locale) {
            $this->actingAs($user)->get("/{$locale}/completeness")->assertOk();
        }
    }

    /**
     * A gap is not a zero. A zero is a figure someone reported.
     */
    public function test_an_empty_cell_reads_as_a_gap_rather_than_a_zero(): void
    {
        $this->seed(IndicatorValueSeeder::class);

        $html = $this->actingAs(User::factory()->editor()->create())
            ->get('/en/completeness')->getContent();

        $this->assertStringContainsString('Not reported', $html);
        $this->assertStringContainsString('data-state="empty"', $html);
        $this->assertStringContainsString('data-state="complete"', $html);
        $this->assertStringContainsString('data-state="partial"', $html);
    }

    public function test_the_page_shows_the_definition_and_its_source(): void
    {
        $html = $this->actingAs(User::factory()->editor()->create())
            ->get('/en/completeness')->getContent();

        $this->assertStringContainsString('Handbook for the Collection of Administrative Data', $html);
        $this->assertStringContainsString('UPU Postal Statistics', $html);
        $this->assertStringContainsString('itu.int', $html);
    }

    /**
     * This used to assert the opposite, and the change is a decision rather
     * than a fix.
     *
     * On 9 September 2026 the demonstration notice was removed from every
     * rendered page: sample rows stay flagged in the database and are
     * disclosed verbally by the presenter instead. This page is the one
     * place that removal costs something, and the cost is recorded in
     * OPEN_ITEMS.md rather than left for someone to rediscover.
     *
     * It is behind authentication and read by member state
     * representatives, who cannot see the admin dashboard where the count
     * now lives. Its entire subject is data quality, and it no longer says
     * that the matrix is built on unconfirmed figures. The recommendation
     * is to put the notice back on this page alone.
     */
    public function test_the_page_no_longer_badges_demonstration_data(): void
    {
        $this->seed(IndicatorValueSeeder::class);

        $html = $this->actingAs(User::factory()->editor()->create())
            ->get('/en/completeness')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Demonstration data', $html);
        $this->assertStringNotContainsString('c-notice--pending', $html);

        // The flag is untouched. That is what makes the removal a
        // disclosure decision rather than a data change.
        $this->assertTrue(IndicatorValue::where('is_sample', true)->exists());
    }
}
