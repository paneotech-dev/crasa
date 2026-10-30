<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Day 3, item C: the phone matrix's fixes, held in place. The matrix itself
 * runs in a browser (day3/proof/mobile); these check the markup and the
 * rules it found missing.
 */
class PhoneLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);
    }

    public function test_the_member_area_language_pills_name_their_language_for_any_reader(): void
    {
        $member = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
        ]);

        $html = $this->actingAs($member)->get('/en/extranet/dashboard')->assertOk()->getContent();

        foreach (['en', 'fr', 'pt'] as $code) {
            $this->assertMatchesRegularExpression('#class="a-pills__item[^"]*"[^>]*lang="'.$code.'" hreflang="'.$code.'"#s', $html, $code);
        }
    }

    public function test_the_month_grid_and_the_comparator_table_scroll_inside_their_own_frame(): void
    {
        $events = $this->get('/en/events')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<div class="e-month"[^>]*>\s*<table class="e-grid"#', $events);
        $this->assertMatchesRegularExpression('/\.e-month\s*\{[^}]*overflow-x:\s*auto/', file_get_contents(public_path('css/events.css')));

        $comparator = $this->get('/en/comparator')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<div class="u-table-frame c-cmp__tableframe">\s*<table#', $comparator);
        $css = file_get_contents(public_path('css/comparator.css'));
        $this->assertStringNotContainsString('.c-cmp__tableframe { border: 0; overflow: visible; }', $css);
    }

    public function test_phone_tap_targets_are_44_pixels_in_the_public_and_member_stylesheets(): void
    {
        $k = file_get_contents(public_path('css/k.css'));
        $this->assertMatchesRegularExpression('/@media \(max-width: 768px\) \{[^@]*\.k-crumb li > a[^@]*min-height: 44px/s', $k);
        $this->assertMatchesRegularExpression('/@media \(max-width: 768px\) \{[^@]*\.k-dot \{ min-width: 44px; min-height: 44px; \}/s', $k);

        $admin = file_get_contents(public_path('css/admin.css'));
        $this->assertMatchesRegularExpression('/\.a-top__burger \{ min-width: 44px; min-height: 44px; \}/', $admin);
        $this->assertMatchesRegularExpression('/\.a-pills__item \{ min-height: 44px;/', $admin);
        $this->assertStringContainsString('body .a-top__icon, body .a-top__avatar { min-width: 44px; min-height: 44px; }', $admin);
        $this->assertStringContainsString('html body .k-langs a,', $k);
        $this->assertMatchesRegularExpression('/\.e-grid \.e-chip \{[^}]*min-height: 44px/', file_get_contents(public_path('css/events.css')));
    }
}
