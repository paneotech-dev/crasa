<?php

namespace Tests\Feature;

use App\Console\Commands\FetchLegacyAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Tests\TestCase;

/**
 * Named article pictures survive the chrome and size filters.
 *
 * Four news items are illustrated only by a lock-up whose filename says
 * "logo" or whose pixels are small. Without the exception the renderer
 * drops them and the article loses its only picture.
 */
class LegacyArticleImageTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://cdn.prod.website-files.com/5fb8ce4adbd6ad2ccc1423e7/';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Imagick::class)) {
            $this->markTestSkipped('Imagick is not available on this host.');
        }

        Storage::fake('local');
        Storage::fake('public');

        $this->app->instance(FetchLegacyAssets::class, new class extends FetchLegacyAssets
        {
            protected function fetch(string $url): ?string
            {
                $image = new Imagick;
                $image->newImage(120, 126, new ImagickPixel('#1b3a5c'));
                $image->setImageFormat('png');

                return $image->getImageBlob();
            }
        });
    }

    private function page(array $images): void
    {
        Storage::disk('local')->put('legacy/content.json', json_encode(['pages' => [
            'https://www.crasa.org/post-articles/an-article' => ['title' => 'An article', 'images' => $images],
        ]]));
    }

    public function test_a_named_article_picture_is_kept_despite_its_name_and_size(): void
    {
        $this->page([self::BASE.'6a1e82e3577af77cee9c54e4_CRASA%20LOGS.png']);

        $this->artisan('legacy:assets', ['--only' => 'images', '--seconds' => 0])->assertSuccessful();

        $manifest = json_decode(Storage::disk('local')->get('legacy/assets.json'), true);

        $this->assertArrayHasKey(self::BASE.'6a1e82e3577af77cee9c54e4_CRASA%20LOGS.png', $manifest['images']);
        $this->assertSame([], $manifest['skipped']);
        $this->assertSame(
            '/storage/images/legacy/an-article/crasa-logs.png',
            parse_url((string) app(\App\Support\LegacyImages::class)->localUrl(self::BASE.'6a1e82e3577af77cee9c54e4_CRASA%20LOGS.png'), PHP_URL_PATH)
        );
    }

    public function test_a_named_picture_skipped_by_an_earlier_run_is_reconsidered(): void
    {
        $url = self::BASE.'65c22829f6451822c5421bdf_Doc2.png';
        $this->page([$url]);
        Storage::disk('local')->put('legacy/assets.json', json_encode([
            'documents' => [], 'images' => [], 'skipped' => [$url => ['reason' => 'too small: 273x75', 'name' => 'Doc2.png']],
        ]));

        $this->artisan('legacy:assets', ['--only' => 'images', '--seconds' => 0])->assertSuccessful();

        $manifest = json_decode(Storage::disk('local')->get('legacy/assets.json'), true);

        $this->assertArrayHasKey($url, $manifest['images']);
        $this->assertArrayNotHasKey($url, $manifest['skipped']);
    }

    public function test_an_unnamed_logo_is_still_skipped_as_chrome(): void
    {
        $url = self::BASE.'5fb8ce4adbd6ad2ccc1423f0_site-logo.png';
        $this->page([$url]);

        $this->artisan('legacy:assets', ['--only' => 'images', '--seconds' => 0])->assertSuccessful();

        $manifest = json_decode(Storage::disk('local')->get('legacy/assets.json'), true);

        $this->assertSame('chrome', $manifest['skipped'][$url]['reason']);
        $this->assertSame([], $manifest['images']);
    }

    public function test_an_unnamed_small_image_is_still_skipped(): void
    {
        $url = self::BASE.'5fb8ce4adbd6ad2ccc1423f1_arrow.png';
        $this->page([$url]);

        $this->artisan('legacy:assets', ['--only' => 'images', '--seconds' => 0])->assertSuccessful();

        $manifest = json_decode(Storage::disk('local')->get('legacy/assets.json'), true);

        $this->assertStringStartsWith('too small', $manifest['skipped'][$url]['reason']);
    }
}
