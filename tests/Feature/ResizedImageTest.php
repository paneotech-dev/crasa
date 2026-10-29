<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Support\ResizedImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * News photographs are served as their WebP renditions, never as the
 * multi-megabyte original, and with their dimensions.
 */
class ResizedImageTest extends TestCase
{
    use RefreshDatabase;

    private function asset(string $path, int $width, int $height, array $renditions): MediaAsset
    {
        return MediaAsset::query()->forceCreate([
            'disk' => 'public',
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'size_bytes' => 3_000_000,
            'width' => $width,
            'height' => $height,
            'checksum' => hash('sha256', $path),
            'renditions' => $renditions,
        ]);
    }

    public function test_a_card_gets_renditions_up_to_960_with_dimensions(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('images/a.jpg', str_repeat('x', 3_000_000));

        $this->asset('images/a.jpg', 3984, 2656, [
            '480' => ['path' => 'images/a-480.webp', 'format' => 'webp'],
            '960' => ['path' => 'images/a-960.webp', 'format' => 'webp'],
            '1440' => ['path' => 'images/a-1440.webp', 'format' => 'webp'],
        ]);

        $card = ResizedImage::attributes('images/a.jpg', 'card');

        $this->assertStringEndsWith('images/a-960.webp', $card['src']);
        $this->assertStringContainsString('a-480.webp 480w', $card['srcset']);
        $this->assertStringNotContainsString('1440', $card['srcset']);
        $this->assertSame([3984, 2656], [$card['width'], $card['height']]);

        $hero = ResizedImage::attributes('images/a.jpg', 'hero');
        $this->assertStringEndsWith('images/a-1440.webp', $hero['src']);
        $this->assertStringNotContainsString('a.jpg', $hero['srcset']);

        $this->assertSame('images/a-960.webp', ResizedImage::cardPath('images/a.jpg'));
    }

    public function test_an_unknown_path_falls_back_to_the_original(): void
    {
        Storage::fake('public');

        $image = ResizedImage::attributes('images/unknown.jpg');

        $this->assertStringEndsWith('images/unknown.jpg', $image['src']);
        $this->assertNull($image['srcset']);
        $this->assertSame('images/unknown.jpg', ResizedImage::cardPath('images/unknown.jpg'));
    }
}
