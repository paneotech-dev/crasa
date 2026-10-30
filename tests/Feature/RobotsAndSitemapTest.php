<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\NewsItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * robots.txt and sitemap.xml follow CRASA_NOINDEX and name APP_URL's host,
 * never the host of the request and never the staging host.
 */
class RobotsAndSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_allows_indexing_and_names_its_own_sitemap(): void
    {
        config(['crasa.noindex' => false, 'app.url' => 'https://crasa.africa']);

        $response = $this->get('/robots.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString("User-agent: *\nDisallow:\n", $body);
        $this->assertStringNotContainsString('Disallow: /', $body);
        $this->assertStringContainsString('Sitemap: https://crasa.africa/sitemap.xml', $body);
    }

    public function test_a_noindex_host_disallows_everything_and_has_no_sitemap(): void
    {
        config(['crasa.noindex' => true, 'app.url' => 'https://crasadev.paneo.tech']);

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertSame("User-agent: *\nDisallow: /\n", $body);
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_the_sitemap_uses_app_url_not_the_request_host(): void
    {
        config(['crasa.noindex' => false, 'app.url' => 'https://crasa.africa']);

        $page = Page::factory()->create(['slug' => 'spectrum-policy', 'status' => PageStatus::Published]);
        Page::factory()->create(['slug' => 'unpublished-draft', 'status' => PageStatus::Draft]);
        $news = NewsItem::factory()->create(['slug' => 'harmonised-roaming', 'status' => 'published', 'published_at' => now()->subDay()]);

        $response = $this->get('http://crasadev.paneo.tech/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $xml = $response->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringNotContainsString('paneo.tech', $xml);

        foreach (['en', 'fr', 'pt'] as $locale) {
            $this->assertStringContainsString('<loc>https://crasa.africa/'.$locale.'</loc>', $xml);
            $this->assertStringContainsString('<loc>https://crasa.africa/'.$locale.'/newsroom/harmonised-roaming</loc>', $xml);
            $this->assertStringContainsString('<loc>https://crasa.africa/'.$locale.'/spectrum-policy</loc>', $xml);
        }
        $this->assertStringContainsString('<loc>https://crasa.africa/fr/accessibilite</loc>', $xml);
        $this->assertStringNotContainsString('unpublished-draft', $xml);

        // Every listed address is on APP_URL's host.
        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $loc) {
            $this->assertStringStartsWith('https://crasa.africa/', $loc);
        }
    }

    public function test_no_code_view_config_or_lang_file_names_the_staging_host(): void
    {
        $allowed = 'workshopcrasa.paneo.tech/training.html';
        $found = [];

        foreach (['app', 'config', 'resources', 'lang', 'routes', 'public'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|js|css|json|txt|xml|html|webmanifest)$/', $file->getFilename())) {
                    continue;
                }
                $text = str_replace($allowed, '', (string) file_get_contents($file->getPathname()));
                if (str_contains($text, 'paneo.tech')) {
                    $found[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $found, 'The staging host is named in: '.implode(', ', $found));
    }
}
