<?php

namespace Tests\Feature;

use App\Support\Assets;
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
 * The guard that should have existed three runs ago.
 *
 * On 9 September a set of screenshots showed the site rendering with almost
 * no styling: the map as black polygons, the section bands as stacked text,
 * the committee list as browser bullets. Every check the build had said the
 * rules were present, and they were. The origin was correct and had been
 * correct throughout.
 *
 * What was wrong was the URL. Cloudflare serves the stylesheets with
 * max-age=31536000 and the href never changed, so a cached copy answered
 * every request for a year. /css/chrome.css was being served 17 hours stale,
 * missing the entire responsive header block, while the origin held the
 * current file. curl on a fresh connection always saw the good one.
 *
 * Nothing in a test suite can inspect a browser cache. What this can do is
 * remove the mechanism: assert that every asset href carries a version, so
 * a changed file is always a changed URL, and assert that every class in
 * the served markup has a rule in a stylesheet that page actually links.
 */
class StylesheetDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Injected by Cloudflare's email obfuscation into markup no template of
     * ours contains. Not ours to style.
     */
    private const NOT_OURS = ['__cf_email__'];

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

        Assets::flush();
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

    /** Every class name any rule in a stylesheet selects on. */
    private function definedIn(string $publicPath): array
    {
        $file = public_path(ltrim(parse_url($publicPath, PHP_URL_PATH) ?? '', '/'));

        if (! is_file($file)) {
            return [];
        }

        $css = (string) file_get_contents($file);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        // Selectors are everything before a brace that is not a declaration
        // block's interior. Good enough to enumerate class names, which is
        // all this needs.
        preg_match_all('/([^{}]+)\{/', $css, $blocks);

        $names = [];

        foreach ($blocks[1] as $selector) {
            preg_match_all('/\.(-?[_a-zA-Z][\w-]*)/', $selector, $found);
            foreach ($found[1] as $name) {
                $names[$name] = true;
            }
        }

        return $names;
    }

    #[DataProvider('publicPages')]
    public function test_every_class_on_the_page_has_a_rule_in_a_stylesheet_the_page_links(string $path): void
    {
        foreach (array_keys(Locales::all()) as $locale) {
            $html = $this->get("/{$locale}{$path}")->assertOk()->getContent();

            preg_match_all('/<link rel="stylesheet" href="([^"]+)"/', $html, $links);
            $this->assertNotEmpty($links[1], "/{$locale}{$path} links no stylesheet at all.");

            $defined = [];

            foreach ($links[1] as $href) {
                $defined += $this->definedIn($href);
            }

            $body = preg_match('/<body.*<\/body>/s', $html, $m) ? $m[0] : $html;
            preg_match_all('/class="([^"]*)"/', $body, $used);

            $missing = [];

            foreach ($used[1] as $attribute) {
                foreach (preg_split('/\s+/', trim($attribute)) as $class) {
                    if ($class === '' || in_array($class, self::NOT_OURS, true)) {
                        continue;
                    }

                    if (! isset($defined[$class])) {
                        $missing[$class] = true;
                    }
                }
            }

            $this->assertSame(
                [],
                array_keys($missing),
                "/{$locale}{$path} uses classes with no rule in any stylesheet it links. "
                .'Either the rule is in a file this page does not link, or it was never written.',
            );
        }
    }

    #[DataProvider('publicPages')]
    public function test_every_asset_href_carries_a_version(string $path): void
    {
        // The whole failure in one assertion. Cloudflare's max-age is a
        // year and is not ours to change; an unversioned href means a
        // browser and an edge can both answer from a copy made before the
        // last several runs.
        $html = $this->get('/en'.$path)->assertOk()->getContent();

        preg_match_all('/<(?:link[^>]+href|script[^>]+src)="([^"]*\/(?:css|js)\/[^"]*)"/', $html, $assets);

        $this->assertNotEmpty($assets[1], "/en{$path} serves no versioned asset at all.");

        foreach ($assets[1] as $href) {
            $this->assertMatchesRegularExpression(
                '/\?v=\d+$/',
                $href,
                "{$href} carries no version. A changed file must be a changed URL.",
            );
        }
    }

    public function test_the_version_changes_when_the_file_does(): void
    {
        $path = 'css/blocks.css';
        $before = Assets::version($path);

        $this->assertNotSame('0', $before, 'blocks.css is not on disk.');

        touch(public_path($path), time() + 60);
        Assets::flush();

        $this->assertNotSame(
            $before,
            Assets::version($path),
            'Editing a stylesheet does not change its URL, which is the whole defect.',
        );

        touch(public_path($path), time());
        Assets::flush();
    }

    public function test_every_linked_stylesheet_exists_on_disk(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        preg_match_all('/<link rel="stylesheet" href="([^"]+)"/', $html, $links);

        foreach ($links[1] as $href) {
            $file = public_path(ltrim((string) parse_url($href, PHP_URL_PATH), '/'));

            $this->assertFileExists($file, "{$href} is linked but not on disk.");
            $this->assertGreaterThan(0, filesize($file), "{$href} is empty.");
        }
    }

    public function test_every_stylesheet_closes_every_brace(): void
    {
        // One unclosed brace early in an 89KB file silently kills every rule
        // after it, and the page still returns 200 with the right markup.
        foreach (glob(public_path('css/*.css')) as $file) {
            $css = (string) file_get_contents($file);
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

            $this->assertSame(
                substr_count($css, '{'),
                substr_count($css, '}'),
                basename($file).' has unbalanced braces. Every rule after the '
                .'first unclosed one is discarded by the browser.',
            );
        }
    }
}
