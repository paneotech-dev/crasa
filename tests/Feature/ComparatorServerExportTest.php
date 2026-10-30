<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Services\ComparisonExport;
use App\Support\Comparison;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Imagick;
use Tests\TestCase;

/**
 * Day 3, item C: the PNG and PDF are drawn on the server, so every browser
 * receives the same file. These tests open the files rather than trusting
 * the headers: the PNG has the drawn size and is not one colour, the PDF
 * carries the indicator's name as text, in both comparison modes.
 */
class ComparatorServerExportTest extends TestCase
{
    use RefreshDatabase;

    private Indicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);
        RateLimiter::clear('comparator-export');

        $this->indicator = Indicator::where('is_active', true)->orderBy('display_order')->firstOrFail();
        foreach (['bw' => 168.4, 'mz' => 92.1] as $iso => $value) {
            IndicatorValue::create([
                'indicator_id' => $this->indicator->id,
                'country_id' => Country::where('iso_code', $iso)->value('id'),
                'period' => '2025',
                'value_numeric' => $value,
                'confidence' => 'validated',
                'source_note' => 'Annual report 2025',
                'is_sample' => $iso === 'mz',
            ]);
        }
    }

    /** @return array<string, array<string, string>> */
    private function modes(): array
    {
        return [
            'compare' => ['mode' => 'compare', 'indicator' => $this->indicator->slug, 'countries' => 'bw,mz'],
            'browse' => ['mode' => 'browse', 'indicator' => $this->indicator->slug],
        ];
    }

    public function test_the_png_is_a_png_of_the_drawn_size_and_not_blank_in_each_mode(): void
    {
        foreach ($this->modes() as $mode => $query) {
            $response = $this->get('/en/comparator/export.png?'.http_build_query($query))->assertOk();
            $blob = $response->getContent();
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $blob, $mode);

            $svg = app(ComparisonExport::class)->svg(Comparison::fromQuery($query));
            preg_match('/<svg[^>]* width="(\d+)" height="(\d+)"/', $svg, $size);

            $image = new Imagick;
            $image->readImageBlob($blob);
            $this->assertSame((int) $size[1], $image->getImageWidth(), $mode);
            $this->assertSame((int) $size[2], $image->getImageHeight(), $mode);

            // Sample a grid of pixels: a blank export is one colour.
            $colours = [];
            for ($x = 10; $x < $image->getImageWidth(); $x += 37) {
                for ($y = 10; $y < $image->getImageHeight(); $y += 23) {
                    $colours[$image->getImagePixelColor($x, $y)->getColorAsString()] = true;
                }
            }
            $this->assertGreaterThan(3, count($colours), $mode.': the PNG is blank');
            $this->assertStringStartsWith('attachment; filename="crasa-'.$this->indicator->slug, $response->headers->get('Content-Disposition'));
        }
    }

    public function test_the_pdf_is_a_pdf_carrying_the_title_table_and_date_in_each_mode(): void
    {
        foreach ($this->modes() as $mode => $query) {
            $response = $this->get('/en/comparator/export.pdf?'.http_build_query($query))->assertOk();
            $pdf = $response->getContent();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', $pdf, $mode);
            $this->assertGreaterThan(5000, strlen($pdf), $mode);
            $this->assertStringContainsString('%%EOF', $pdf);
            $this->assertStringContainsString('/Subtype /Image', $pdf);
            $title = strtr(iconv('UTF-8', 'Windows-1252//TRANSLIT', $this->indicator->name), ['(' => '\\(', ')' => '\\)']);
            $this->assertStringContainsString('('.$title.') Tj', $pdf, $mode);
            $this->assertStringContainsString('(Botswana) Tj', $pdf, $mode);
            $this->assertStringContainsString(now()->isoFormat('YYYY'), $pdf, $mode);

            // ImageMagick can open it, so a reader can.
            $check = new Imagick;
            $check->setResolution(36, 36);
            $check->readImageBlob($pdf);
            $this->assertGreaterThanOrEqual(2, $check->getNumberImages(), $mode);
        }
    }

    public function test_an_unknown_indicator_or_member_state_is_a_404_never_a_500(): void
    {
        foreach (['png', 'pdf'] as $format) {
            foreach ([
                ['mode' => 'compare', 'indicator' => 'no-such-indicator', 'countries' => 'bw'],
                ['mode' => 'browse', 'indicator' => 'no-such-indicator'],
                ['mode' => 'compare', 'indicator' => $this->indicator->slug, 'countries' => 'bw,xx'],
                ['mode' => 'compare', 'indicator' => ['a' => 'b']],
            ] as $query) {
                $this->get("/en/comparator/export.{$format}?".http_build_query($query))->assertNotFound();
            }
        }
    }

    public function test_the_image_exports_are_rate_limited(): void
    {
        $url = '/en/comparator/export.csv?'.http_build_query($this->modes()['compare']);
        $this->get($url)->assertOk();

        $route = app('router')->getRoutes()->getByName('comparator.png');
        $this->assertContains('throttle:comparator-export', $route->gatherMiddleware());
        $this->assertContains('throttle:comparator-export', app('router')->getRoutes()->getByName('comparator.pdf')->gatherMiddleware());
    }

    public function test_the_buttons_link_to_the_server_routes_with_the_same_file_names(): void
    {
        $html = $this->get('/en/comparator?'.http_build_query($this->modes()['compare']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#data-export="png" href="[^"]*/en/comparator/export\.png\?#', $html);
        $this->assertMatchesRegularExpression('#data-export="pdf" href="[^"]*/en/comparator/export\.pdf\?#', $html);
        $this->assertStringNotContainsString('toDataURL', $html);
    }
}
