<?php

namespace Tests\Feature;

use App\Console\Commands\StripSampleData;
use App\Models\IndicatorValue;
use App\Models\User;
use App\Support\Locales;
use App\Support\SampleData;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sample data is flagged in the database and disclosed verbally, not badged
 * on the page. Decision of 9 September 2026, recorded in OPEN_ITEMS.md.
 *
 * The point of this test is not that the banners are gone. It is that
 * removing them removed nothing else: the flags, the strip, the provenance
 * column and the export notice all still work, and the Secretariat has a
 * count of what is left.
 */
class SampleDisclosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);
    }

    public function test_no_public_page_badges_demonstration_data(): void
    {
        $this->assertTrue(
            IndicatorValue::where('is_sample', true)->exists(),
            'The fixture has no sample rows, so this test proves nothing.',
        );

        foreach (array_keys(Locales::all()) as $locale) {
            foreach (['', '/comparator', '/members', '/publications', '/about'] as $path) {
                $html = $this->get("/{$locale}{$path}")->assertOk()->getContent();

                foreach ([
                    'b-hero__panel-notice',
                    'c-cmp__notice',
                    'c-notice--pending',
                ] as $marker) {
                    $this->assertStringNotContainsString(
                        $marker,
                        $html,
                        "/{$locale}{$path} still badges demonstration data with {$marker}.",
                    );
                }
            }
        }
    }

    public function test_the_flags_themselves_are_untouched(): void
    {
        // The whole decision rests on this. If removing the notice had also
        // removed the flag, the strip would silently miss those rows and
        // go-live would ship demonstration figures as real ones.
        $this->assertGreaterThan(0, SampleData::total());
        $this->assertArrayHasKey('IndicatorValue', SampleData::counts());
    }

    public function test_the_count_reads_the_strip_commands_own_list(): void
    {
        // Two lists would drift, and the one that drifted would be the
        // dashboard, which would then under-report a platform that is not
        // clean. There is one list.
        $this->assertNotEmpty(StripSampleData::ORDER);

        foreach (array_keys(SampleData::counts()) as $table) {
            $this->assertContains(
                $table,
                array_map('class_basename', StripSampleData::ORDER),
                "{$table} is counted but is not in the strip's order, so it would survive the purge.",
            );
        }
    }

    public function test_the_admin_dashboard_says_the_platform_is_not_clean(): void
    {
        $admin = User::factory()->administrator()->create();

        $html = $this->actingAs($admin)
            ->get('/en/admin')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('a-band--pending', $html);
        $this->assertStringContainsString('content:strip-samples', $html);
        $this->assertStringContainsString('IndicatorValue', $html);
    }

    public function test_a_clean_platform_shows_no_band_at_all(): void
    {
        // Not a green all-clear. The absence is the signal.
        foreach (StripSampleData::ORDER as $class) {
            $model = new $class;
            $model->newQuery()->where('is_sample', true)->update(['is_sample' => false]);
        }

        $this->assertSame(0, SampleData::total());

        $admin = User::factory()->administrator()->create();
        $html = $this->actingAs($admin)->get('/en/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('a-band--pending', $html);
    }

    public function test_an_export_still_carries_the_notice_in_writing(): void
    {
        // Kept deliberately. An export leaves the platform and can reach a
        // ministerial brief; the ruling was about pages.
        $service = file_get_contents(base_path('app/Services/ComparisonExport.php'));

        $this->assertStringContainsString('comparator.sample_notice_short', $service);
    }

    public function test_the_presenter_is_told_to_say_it_once(): void
    {
        $script = file_get_contents(base_path('docs/DEMO_SCRIPT.md'));

        $this->assertStringContainsString('demonstration data', $script);
        $this->assertStringContainsString('confirmed by any national regulator', $script);
        $this->assertStringContainsString('Say it once', $script);
    }
}
