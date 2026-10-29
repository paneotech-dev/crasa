<?php

namespace Tests\Feature;

use App\Support\Locales;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every asset a page references must resolve.
 *
 * Written after the live site rendered alt text where the homepage hero
 * should have been. Every server-side check passed: the markup was right,
 * the files were on disk, the symlink resolved, the URL returned 200 when
 * requested directly. What was wrong was the host in the emitted URL, which
 * nothing in the suite was looking at.
 *
 * These tests read the rendered HTML of every public page, pull out every
 * src and srcset, and check that each one points at a file that exists and
 * at a host the page is actually served from.
 */
class AssetIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(PublicationSeeder::class);
    }

    /** @return list<string> */
    private function pages(): array
    {
        $paths = ['', '/about', '/committees', '/members', '/contact', '/newsroom', '/publications'];
        $urls = [];

        foreach (Locales::SUPPORTED as $locale) {
            foreach ($paths as $path) {
                $urls[] = '/'.$locale.$path;
            }
        }

        return $urls;
    }

    /**
     * Every URL a page references for an asset, from src and from every
     * candidate in a srcset.
     *
     * @return list<string>
     */
    private function assetUrls(string $html): array
    {
        $urls = [];

        preg_match_all('/<(?:img|source|script)[^>]*\ssrc="([^"]+)"/i', $html, $srcs);
        foreach ($srcs[1] as $src) {
            $urls[] = $src;
        }

        preg_match_all('/srcset="([^"]+)"/i', $html, $srcsets);
        foreach ($srcsets[1] as $srcset) {
            foreach (explode(',', $srcset) as $candidate) {
                $url = trim(explode(' ', trim($candidate))[0]);
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
        }

        preg_match_all('/<link[^>]*\shref="([^"]+\.css[^"]*)"/i', $html, $styles);
        foreach ($styles[1] as $href) {
            $urls[] = $href;
        }

        return array_values(array_unique($urls));
    }

    /** The file a same-origin asset URL resolves to, or null if it is external. */
    private function fileFor(string $url): ?string
    {
        if (str_starts_with($url, 'data:')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if ($path === null || $path === false) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // An absolute URL to somewhere else is not ours to resolve, but it is
        // caught by the hardcoded-host test below.
        if ($host !== null && $host !== parse_url(config('app.url'), PHP_URL_HOST)) {
            return null;
        }

        return public_path(ltrim(strtok($path, '?'), '/'));
    }

    public function test_every_asset_on_every_page_resolves_to_a_file_that_exists(): void
    {
        $missing = [];

        foreach ($this->pages() as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            foreach ($this->assetUrls($html) as $url) {
                $file = $this->fileFor($url);

                if ($file === null) {
                    continue;
                }

                if (! file_exists($file)) {
                    $missing[] = $page.' -> '.$url;
                }
            }
        }

        $this->assertSame([], $missing, "Assets referenced but not present:\n".implode("\n", $missing));
    }

    /**
     * Every asset must be same-origin as the page that references it. A URL
     * on another host is a request the browser may refuse, and on an HTTPS
     * page an http:// asset is refused outright as mixed content.
     */
    public function test_no_asset_points_at_a_different_origin_than_the_page(): void
    {
        $expected = parse_url(url('/'), PHP_URL_SCHEME).'://'.parse_url(url('/'), PHP_URL_HOST)
            .(parse_url(url('/'), PHP_URL_PORT) ? ':'.parse_url(url('/'), PHP_URL_PORT) : '');

        $offenders = [];

        foreach ($this->pages() as $page) {
            foreach ($this->assetUrls($this->get($page)->getContent()) as $url) {
                if (str_starts_with($url, '/') || str_starts_with($url, 'data:')) {
                    continue;
                }

                // The social embeds load each network's own widget script
                // (S7, F5, 29 October 2026), named in the CSP too. Nothing
                // else may come from another origin.
                if ($url === 'https://platform.twitter.com/widgets.js') {
                    continue;
                }

                if (! str_starts_with($url, $expected)) {
                    $offenders[] = $page.' -> '.$url;
                }
            }
        }

        $this->assertSame([], $offenders, "Assets on another origin:\n".implode("\n", $offenders));
    }

    /**
     * THE DECISIVE ONE.
     *
     * Building the public disk URL from APP_URL bakes a host into every
     * image on every page, and it drifts silently the moment the app is
     * served from anywhere other than APP_URL says. That is what put
     * http://localhost:8000/storage/... into a live HTTPS page and made the
     * homepage render alt text where the hero should have been.
     *
     * A relative /storage cannot drift.
     */
    public function test_the_public_disk_url_is_host_relative(): void
    {
        $this->assertSame('/storage', config('filesystems.disks.public.url'));

        $this->assertStringStartsWith(
            '/storage/',
            Storage::disk('public')->url('images/x.jpg'),
            'The public disk is emitting absolute URLs again.'
        );
    }

}
