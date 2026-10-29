<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\PageBlock;
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
        // Since 29 October 2026 the home page is the Concept 3 composition
        // (pages/home.blade.php): its sections set their own grounds in
        // k.css, alternating white, the green tint and the blue tint, so it
        // no longer uses data-ground. What holds the rhythm is the order of
        // the sections, checked here; the composed inner pages below keep
        // the data-ground rule.
        $html = $this->get('/en')->assertOk()->getContent();
        preg_match_all('~<section class="([^"]+)"~', $html, $m);
        $order = array_map(fn ($c) => explode(' ', $c)[0], $m[1]);

        $expected = ['k-hero', 'k-glance', 'k-sec', 'k-sec'];
        $at = 0;
        foreach ($order as $section) {
            if ($at < count($expected) && $section === $expected[$at]) {
                $at++;
            }
        }
        $this->assertSame(count($expected), $at, 'The home page sections are out of the Concept 3 order: '.implode(', ', $order));
        $this->assertSame([], $this->grounds('/en'), 'The Concept 3 home sets its grounds in k.css, not with data-ground.');
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
        // Six rich-text blocks in a row must not read as one wall. Written
        // first, as the Secretariat will: unwritten sections are not shown.
        foreach (PageBlock::whereHas('page', fn ($q) => $q->where('slug', 'about'))->get() as $block) {
            if ($block->isUnwritten()) {
                $block->setTranslation('body', 'en', 'Text written by the Secretariat for this section.');
            }
        }
        $grounds = $this->grounds('/en/about');

        // Since the UI system (29 October 2026) About alternates white with
        // the green and blue tints rather than cream.
        $this->assertGreaterThan(3, count($grounds));
        $this->assertContains('tint-green', $grounds);
        $this->assertContains('white', $grounds);
        foreach ($grounds as $i => $ground) {
            if ($i > 0) {
                $this->assertNotSame($grounds[$i - 1], $ground, "About repeats {$ground} at position {$i}.");
            }
        }
    }

    public function test_the_figures_and_the_comparison_sit_on_light_grounds(): void
    {
        // 29 October 2026: the room found the homepage too dark. The band
        // under the hero and the comparison are light now, and stay light.
        $this->assertSame('white', BlockType::KeyFigures->ground());
        $this->assertNotContains(BlockType::FeaturedComparison->ground(), ['navy', 'navy-mid']);
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
