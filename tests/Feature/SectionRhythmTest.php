<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Support\Locales;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\PublicPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * The ground each section sits on, and the rhythm the sequence makes.
 *
 * Screen 02 assigns a ground to what a section is, not to where it falls.
 * The build used to rotate cream, white, sand, white on a positional cycle,
 * which emitted no navy at all: every section between the hero and the
 * footer was cream, white or sand, and the page read as one column.
 */
class SectionRhythmTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();

        // About and Committees are composed too, and the run-of-one-type
        // failure only shows on a page that has such a run.
        $this->seed(PublicPagesSeeder::class);
        $this->seed(CommitteeSeeder::class);
    }

    /** The grounds a page emits, in document order, hero excluded. */
    private function grounds(string $path): array
    {
        $html = $this->get($path)->assertOk()->getContent();
        preg_match_all('/data-ground="([a-z-]+)"/', $html, $m);

        return $m[1];
    }

    public function test_the_homepage_carries_the_grounds_screen_02_draws(): void
    {
        // Screen 02: hero navy (line 93), key figures navy-mid (186),
        // pillars on the cream page ground (227), featured comparison navy
        // (272), publications cream (332), newsroom (399), members sand
        // (604), social feed white (535).
        $this->assertSame(
            ['navy-mid', 'cream', 'navy', 'cream', 'white', 'sand', 'white'],
            $this->grounds('/en'),
        );
    }

    /**
     * Every composed page, not only the homepage.
     *
     * The About page is six rich-text blocks. They all resolve to cream,
     * blocks.css drops the padding between two matching grounds, and the
     * whole page fused into one column with no seam anywhere in it.
     */
    public static function composedPages(): array
    {
        return [
            'homepage' => ['/en'],
            'about' => ['/en/about'],
            'committees' => ['/en/committees'],
        ];
    }

    #[DataProvider('composedPages')]
    public function test_no_two_adjacent_sections_share_a_ground(string $path): void
    {
        $grounds = $this->grounds($path);

        $this->assertNotEmpty($grounds, "{$path} emits no grounds at all.");

        foreach ($grounds as $i => $ground) {
            if ($i === 0) {
                continue;
            }

            $this->assertNotSame(
                $grounds[$i - 1],
                $ground,
                "Two adjacent sections on {$path} both sit on {$ground}, at positions "
                .($i - 1)." and {$i}. A repeated ground merges them into one region."
            );
        }
    }

    public function test_a_run_of_one_block_type_still_separates(): void
    {
        // Six rich-text blocks in a row must not read as one wall.
        $grounds = $this->grounds('/en/about');

        $this->assertGreaterThan(3, count($grounds));
        $this->assertContains('cream', $grounds);
        $this->assertContains('white', $grounds);
    }

    public function test_the_page_reaches_navy_between_the_hero_and_the_footer(): void
    {
        // The failure the positional cycle produced. A page whose only dark
        // is the hero has no rhythm below the fold.
        $grounds = $this->grounds('/en');

        $darks = array_filter($grounds, fn ($g) => str_starts_with($g, 'navy'));

        $this->assertGreaterThanOrEqual(
            2,
            count($darks),
            'The homepage returns to navy twice on screen 02, at lines 186 and 272.',
        );
    }

    public function test_every_block_type_resolves_to_a_ground_the_stylesheet_defines(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        foreach (BlockType::cases() as $type) {
            $ground = $type->ground();

            if ($ground === null) {
                continue;
            }

            $this->assertStringContainsString(
                ".b[data-ground=\"{$ground}\"] {",
                $css,
                "{$type->value} asks for the {$ground} ground, which blocks.css does not define. "
                .'The section would render transparent.',
            );
        }
    }

    public function test_a_dark_ground_inverts_its_headings(): void
    {
        // A navy section that kept charcoal headings fails SC 1.4.3 at
        // roughly 1.6:1. The inversion is not decoration.
        $css = file_get_contents(public_path('css/blocks.css'));

        foreach (['navy', 'navy-mid'] as $dark) {
            $this->assertMatchesRegularExpression(
                '/\.b\[data-ground="'.$dark.'"\] \.b__heading[^{]*\{[^}]*color: var\(--c-white\)/',
                $css,
                "The {$dark} ground does not lift its headings to white.",
            );
        }
    }

    public function test_the_rhythm_holds_in_every_locale(): void
    {
        $reference = $this->grounds('/en');

        foreach (array_keys(Locales::all()) as $code) {
            $this->assertSame(
                $reference,
                $this->grounds("/{$code}"),
                "The section rhythm differs in {$code}. Ground follows the block, not the translation.",
            );
        }
    }
}
