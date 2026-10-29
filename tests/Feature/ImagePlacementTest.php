<?php

namespace Tests\Feature;

use App\Support\ImageLibrary;
use App\Support\Locales;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Where the second image batch went, and where it deliberately did not.
 *
 * Four photographs were accepted on 9 September 2026 and four were held. The
 * boardroom shot closed the loudest gap on the site: the homepage hero had
 * been running on navy and typography because the library had no
 * institutional subject and a hero on the wrong picture is a claim about
 * what CRASA is, made on the first screen to a regulator.
 */
class ImagePlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);
    }

    public function test_the_hero_overlays_are_the_ones_screen_02_draws(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '/\.b-hero__tint \{[^}]*linear-gradient\(140deg, var\(--c-navy\) 0%, var\(--c-green-dark\) 100%\)[^}]*mix-blend-mode: color/s',
            $css,
        );

        $this->assertMatchesRegularExpression(
            '/\.b-hero__scrim \{[^}]*rgba\(15, 27, 45, \.94\) 0%,\s*rgba\(15, 27, 45, \.82\) 42%,\s*rgba\(15, 27, 45, \.34\) 100%/s',
            $css,
        );
    }

    public function test_the_photograph_is_not_flipped(): void
    {
        // The gradient runs .94 at the left to .34 at the right, so the
        // subject has to be on the right. Mirroring would put the delegates
        // under the opaque end and the empty end under the headline.
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/\.b-hero__media[^{]*\{[^}]*scaleX\(-1\)/',
            $css,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.c-img--cover[^{]*\{[^}]*transform:[^;}]*scaleX/',
            $css,
        );
    }

    /** @return array<string, array{string, string}> */
    public static function bands(): array
    {
        return [
            'about' => ['/en/about', 'institutional-delegates-boardroom-01'],
            'committees' => ['/en/committees', 'institutional-delegates-boardroom-01'],
            // Events opens on its calendar since Day 2; its hero carries no band photograph.
            'comparator' => ['/en/comparator', 'data-team-reviewing-charts-01'],
        ];
    }

    #[DataProvider('bands')]
    public function test_the_band_carries_its_photograph(string $path, string $file): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertStringContainsString($file, $html, "{$path} has no photograph.");

        // The band rendered an empty div for months: it passed alt="" to a
        // component that refuses an empty alt, which is right for a content
        // image and was wrong for a background.
        $this->assertMatchesRegularExpression(
            '#<img[^>]*'.preg_quote($file, '#').'[^>]*>#',
            $html,
            "{$path} emits the wrapper but no img.",
        );
    }

    public function test_a_band_photograph_is_decorative_and_says_so(): void
    {
        // WCAG H67. At 82 per cent overlay behind a heading the photograph
        // carries nothing the heading does not, so a screen reader passes
        // over it rather than reading a description before every title.
        $html = $this->get('/en/about')->assertOk()->getContent();

        // The UI system's framed hero photograph (C16, 29 October 2026).
        $this->assertMatchesRegularExpression('/u-hero__photo"\s+aria-hidden="true"/', $html);
        $this->assertMatchesRegularExpression(
            '#<img[^>]*institutional-delegates-boardroom-01[^>]*alt=""#',
            $html,
        );
    }

    public function test_every_slot_is_described_in_all_three_languages(): void
    {
        // A slot with no alt text does not render at all, so a missing
        // translation is a missing photograph rather than a silent one.
        foreach (array_keys(Locales::all()) as $locale) {
            $strings = require base_path("lang/{$locale}/images.php");

            foreach (ImageLibrary::slots() as $slot) {
                $this->assertArrayHasKey(
                    $slot,
                    $strings,
                    "The {$slot} slot has no {$locale} alt text, so it will not render.",
                );
                $this->assertNotSame('', trim($strings[$slot]));
            }
        }
    }

    public function test_the_generic_atrium_photograph_leads_nothing(): void
    {
        // It would pass the substitution test for any company on earth.
        foreach (['/en', '/en/about', '/en/committees', '/en/events'] as $path) {
            $this->assertStringNotContainsString(
                'institutional-two-person-meeting',
                $this->get($path)->getContent(),
                "The atrium photograph leads {$path}.",
            );
        }
    }

    public function test_the_held_images_are_not_in_the_library(): void
    {
        $library = file_get_contents(base_path('app/Support/ImageLibrary.php'));

        foreach ([
            'company-hosting-diversity-training-session',  // reads as AI generated
            'hr-experts-look-cvs',                          // green screen
            'Design_sans_titre',                            // construction, and 1200x800
            'dakar',                                        // 500x500, and Senegal is not SADC
        ] as $held) {
            $this->assertStringNotContainsString($held, $library, "{$held} was held and is in the library.");
        }
    }

    public function test_the_new_photographs_are_in_the_licence_register(): void
    {
        $rows = array_map('str_getcsv', file(base_path('docs/LICENCES.csv')));
        $header = array_shift($rows);

        $register = implode("\n", array_map(fn ($r) => implode(',', $r), $rows));

        foreach (['boardroom', 'reviewing charts', 'broadband router', 'atrium'] as $subject) {
            $this->assertStringContainsString($subject, $register, "{$subject} is not in LICENCES.csv.");
        }

        // Both suspected sources require attribution on their free tiers and
        // neither licence has been recorded. The register says so rather
        // than leaving the columns quietly blank.
        $this->assertStringContainsString('NOT YET RECORDED', $register);
    }
}
