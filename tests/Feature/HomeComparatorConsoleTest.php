<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C8: the home comparison console. Every select is answered by the same
 * fragment, which carries the table, the chart and the comparator link
 * together, and the form works with no script.
 */
class HomeComparatorConsoleTest extends TestCase
{
    use RefreshDatabase;

    private Indicator $mobile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->mobile = Indicator::where('slug', 'mobile-cellular-subscriptions')->firstOrFail();

        foreach (['za' => 172.4, 'mu' => 118.5, 'bw' => 42] as $iso => $value) {
            IndicatorValue::create([
                'indicator_id' => $this->mobile->id,
                'country_id' => Country::where('iso_code', $iso)->value('id'),
                'period' => '2025',
                'value_numeric' => $value,
                'confidence' => $iso === 'za' ? 'validated' : 'reported',
                'is_sample' => false,
            ]);
        }
    }

    public function test_the_fragment_answers_every_select_with_table_chart_and_link_without_a_page_block(): void
    {
        $html = $this->get('/en/home/comparison?view=console&cmp_indicator=mobile-cellular-subscriptions&cmp_a=za&cmp_b=mu')
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-cmp-part="table"', $html);
        $this->assertStringContainsString('data-cmp-part="chart"', $html);
        $this->assertStringContainsString('data-a="za"', $html);
        $this->assertStringContainsString('data-b="mu"', $html);
        $this->assertStringContainsString('South Africa 172.4 (2025); Mauritius 118.5 (2025)', $html);
        $this->assertStringContainsString('class="c-cmp-chart"', $html);
        $this->assertStringContainsString('indicator=mobile-cellular-subscriptions&amp;countries=za%2Cmu', $html);
        $this->assertStringContainsString(__('comparator.note_above_100'), $html);

        // Changing only the second state changes the table and the chart.
        $other = $this->get('/en/home/comparison?view=console&cmp_indicator=mobile-cellular-subscriptions&cmp_a=za&cmp_b=bw')->getContent();
        $this->assertStringContainsString('data-b="bw"', $other);
        $this->assertStringContainsString('Botswana 42 (2025)', $other);
        $this->assertStringNotContainsString('Mauritius 118.5', $other);
    }

    public function test_the_partial_is_a_get_form_that_works_without_script(): void
    {
        $this->app['request']->query->replace(['cmp_indicator' => 'mobile-cellular-subscriptions', 'cmp_a' => 'mu', 'cmp_b' => 'bw']);
        $html = view('partials.home.comparator')->render();

        $this->assertMatchesRegularExpression('~<form class="c-hcmp__form" method="get" action="[^"]*#home-compare">~', $html);
        foreach (['cmp_indicator', 'cmp_a', 'cmp_b'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html);
        }
        $this->assertStringContainsString('data-cmp-submit', $html);
        $this->assertStringContainsString('role="status" aria-live="polite"', $html);
        $this->assertMatchesRegularExpression('~<option value="mu" selected>~', $html);
        $this->assertStringContainsString('Mauritius 118.5 (2025)', $html);
        $this->assertStringContainsString(__('d.open_full'), $html);
    }
}
