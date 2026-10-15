<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\PageBlock;
use App\Support\Locales;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every internal link the site renders must lead somewhere.
 *
 * A dead link in the footer is on every page of the site at once, which is
 * why this crawls rather than listing routes: a route can exist while the
 * markup points at a slug that does not.
 */
class SiteNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(LegalPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
    }

    public function test_no_internal_link_on_any_public_page_is_dead(): void
    {
        $seen = [];
        $dead = [];

        foreach ($this->publicPaths() as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            foreach ($this->internalLinksIn($html) as $link) {
                if (isset($seen[$link])) {
                    continue;
                }

                $seen[$link] = true;
                $status = $this->get($link)->getStatusCode();

                // A redirect is a live destination. The extranet link goes
                // to sign-in, which is where an unauthenticated visitor
                // belongs.
                if (! in_array($status, [200, 302], true)) {
                    $dead[] = "{$link} returned {$status} (linked from {$path})";
                }
            }
        }

        $this->assertNotEmpty($seen, 'The crawl found no internal links at all, so it proved nothing.');
        $this->assertSame([], $dead, "Dead internal links:\n".implode("\n", $dead));
    }

    public function test_every_footer_link_to_a_page_fragment_lands_on_a_real_anchor(): void
    {
        // Unwritten sections are not shown and not linked (15 October 2026),
        // so write one, as the Secretariat will, and check its link lands.
        $written = PageBlock::whereNotNull('anchor')->where('block_type', BlockType::RichText->value)
            ->where('anchor', 'governance')->whereHas('page', fn ($q) => $q->where('slug', 'about'))->firstOrFail();
        $written->setTranslation('body', 'en', 'The text of this section, as written by the Secretariat.');
        $unwritten = PageBlock::unwrittenAnchors()['about'] ?? [];

        $html = $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('/about#'.$written->anchor.'"', $html, 'A written section has no footer link.');
        foreach ($unwritten as $anchor) {
            $this->assertStringNotContainsString('/about#'.$anchor.'"', $html, "The footer links to the unwritten section #{$anchor}.");
        }

        preg_match_all('~href="[^"]*/([a-z0-9\-/]+)#([a-z0-9\-]+)"~i', $html, $matches, PREG_SET_ORDER);

        $this->assertNotEmpty($matches, 'The footer renders no fragment links, so the About column has lost its sections.');

        foreach ($matches as [, $path, $fragment]) {
            $target = $this->get('/en/'.$path)->assertOk()->getContent();

            $this->assertStringContainsString(
                'id="'.$fragment.'"',
                $target,
                "/en/{$path} has no element with id=\"{$fragment}\", so that footer link scrolls nowhere."
            );
        }
    }

    public function test_the_legal_pages_resolve_in_every_locale(): void
    {
        foreach (['privacy', 'terms', 'accessibility'] as $slug) {
            foreach (Locales::SUPPORTED as $locale) {
                $this->get("/{$locale}/{$slug}")
                    ->assertOk()
                    ->assertSee('<h1', false);
            }
        }
    }

    public function test_the_accessibility_statement_names_the_standard_and_the_exceptions(): void
    {
        $html = $this->get('/en/accessibility')->assertOk()->getContent();

        // The conformance target, stated rather than implied.
        $this->assertStringContainsString('2.1', $html);
        $this->assertStringContainsString('Level AA', $html);

        // And the disclosed variations. A statement that claims an
        // unqualified pass while OPEN_ITEMS.md records two altered token
        // values is a statement that is not true.
        $this->assertStringContainsString('1.4.11', $html);
        $this->assertStringContainsString('1.4.3', $html);

        // A route for reporting a barrier is required by the statement to
        // be worth anything.
        $this->assertStringContainsString('crasa@crasa.org', $html);
    }

    /** @return list<string> */
    private function publicPaths(): array
    {
        return [
            '/en', '/en/about', '/en/committees', '/en/members',
            '/en/publications', '/en/newsroom', '/en/contact', '/en/search',
            '/en/privacy', '/en/terms', '/en/accessibility',
            '/en/events', '/en/comparator',
        ];
    }

    /** @return list<string> */
    private function internalLinksIn(string $html): array
    {
        preg_match_all('~<a[^>]+href="([^"]+)"~i', $html, $matches);

        $links = [];

        foreach ($matches[1] as $href) {
            $href = strtok($href, '#');

            if ($href === false || $href === '' || $href === null) {
                continue;
            }

            // External schemes and mailto are somebody else's uptime.
            if (preg_match('~^(https?:)?//|^(mailto|tel):~i', $href)) {
                $host = parse_url($href, PHP_URL_HOST);

                if ($host !== null && $host !== parse_url(config('app.url'), PHP_URL_HOST)) {
                    continue;
                }

                $href = parse_url($href, PHP_URL_PATH) ?: '/';
            }

            if (! str_starts_with($href, '/')) {
                continue;
            }

            $links[] = $href;
        }

        return array_values(array_unique($links));
    }
}
