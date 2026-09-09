<?php

namespace Tests\Feature;

use App\Support\Locales;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * No photograph appears twice on one page.
 *
 * The statement band carried the hero's boardroom photograph, so the
 * homepage showed the same picture in two places four sections apart. And
 * two news items in the same sector both fell back to that sector's image,
 * so the strip put it side by side with itself.
 *
 * A reader does not read a repeat as two subjects. They read it as a page
 * that ran out of pictures, or as one that failed to load.
 *
 * Derivatives are resolved back to their original before comparing:
 * hero-delegates-boardroom-01-480.webp and -1920.jpg are the same
 * photograph and the rule is about photographs.
 */
class ImageUniquenessTest extends TestCase
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
        $this->seed(IndicatorValueSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
    }

    /** @return array<string, array{string}> */
    public static function publicPages(): array
    {
        $pages = [];

        foreach (['', '/about', '/committees', '/members', '/publications',
            '/newsroom', '/events', '/comparator', '/search', '/contact'] as $path) {
            $pages[$path ?: 'home'] = [$path];
        }

        return $pages;
    }

    /**
     * Every distinct photograph on a page, by original, with how many
     * separate <img> elements reference it.
     *
     * @return array<string, int>
     */
    private function photographs(string $html): array
    {
        preg_match_all('/<img\b[^>]*>/', $html, $tags);

        $counts = [];

        foreach ($tags[0] as $tag) {
            if (! preg_match('#src="[^"]*derivatives/([a-z0-9-]+?)-\d+\.(?:jpg|webp|png)"#', $tag, $m)) {
                continue;
            }

            $counts[$m[1]] = ($counts[$m[1]] ?? 0) + 1;
        }

        return $counts;
    }

    #[DataProvider('publicPages')]
    public function test_no_photograph_appears_twice_on_one_page(string $path): void
    {
        foreach (array_keys(Locales::all()) as $locale) {
            $html = $this->get("/{$locale}{$path}")->assertOk()->getContent();

            foreach ($this->photographs($html) as $original => $count) {
                $this->assertSame(
                    1,
                    $count,
                    "/{$locale}{$path} uses {$original} {$count} times. A reader does not read a "
                    .'repeat as two subjects; they read it as a page that ran out of pictures. '
                    .'Use the designed alternative for the second surface and record the gap in '
                    .'docs/CLIENT_REQUESTS.md.',
                );
            }
        }
    }

    public function test_the_statement_band_does_not_reuse_the_hero(): void
    {
        // The specific case that prompted the rule.
        $html = $this->get('/en')->assertOk()->getContent();

        // Matched on the media block itself rather than a fixed slice: a
        // <picture> with four widths in two formats runs well past any
        // guess at a length.
        $this->assertMatchesRegularExpression(
            '#<div class="b-statement__media".*?telecom-home-broadband-router-01.*?</div>#s',
            $html,
            'The statement band does not carry the router photograph.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '#<div class="b-statement__media".*?hero-delegates-boardroom-01.*?</div>#s',
            $html,
            'The statement band is reusing the hero photograph again.',
        );
    }

    public function test_the_rule_is_written_down_where_it_will_be_read(): void
    {
        $this->assertStringContainsString(
            'The same photograph twice on one page',
            file_get_contents(base_path('CLAUDE.md')),
        );
    }
}
