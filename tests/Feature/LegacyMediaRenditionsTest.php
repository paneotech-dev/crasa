<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Legacy\Renditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Legacy photographs in the media library, in the library's own shape.
 *
 * The import catalogued them with the manifest's "480.jpg" => path list and
 * the media library screen answered 500 from the first one.
 */
class LegacyMediaRenditionsTest extends TestCase
{
    use RefreshDatabase;

    private const MANIFEST = [
        '480.jpg' => 'images/legacy/news/photo-480.jpg',
        '480.webp' => 'images/legacy/news/photo-480.webp',
        '960.jpg' => 'images/legacy/news/photo-960.jpg',
        '960.webp' => 'images/legacy/news/photo-960.webp',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach ([...array_values(self::MANIFEST), 'images/legacy/news/photo.jpg', 'images/legacy/news/logo.png'] as $path) {
            Storage::disk('public')->put($path, str_repeat('x', 2048));
        }
    }

    private function asset(array $renditions, string $path = 'images/legacy/news/photo.jpg', int $width = 1200, ?string $source = 'https://cdn.example/photo.jpg'): MediaAsset
    {
        $id = DB::table('media_assets')->insertGetId([
            'disk' => 'public',
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'width' => $width,
            'height' => 800,
            'checksum' => hash('sha256', $path.microtime()),
            'source_url' => $source === null ? null : $source.'?'.microtime(),
            'renditions' => json_encode($renditions),
            'rights_holder' => 'CRASA',
            'licence' => "The client's own material, from crasa.org",
            'is_sample' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return MediaAsset::findOrFail($id);
    }

    public function test_the_manifest_list_becomes_one_rendition_per_width_preferring_webp(): void
    {
        $out = Renditions::fromManifest(self::MANIFEST, 'images/legacy/news/photo.jpg', 1200);

        $this->assertSame(['480', '960'], array_map('strval', array_keys($out)));
        $this->assertSame('images/legacy/news/photo-960.webp', $out['960']['path']);
        $this->assertSame('webp', $out['960']['format']);
        $this->assertSame(2048, $out['480']['bytes']);
    }

    public function test_an_image_too_small_for_a_derivative_is_served_at_its_own_width(): void
    {
        $out = Renditions::fromManifest([], 'images/legacy/news/logo.png', 120);

        $this->assertSame(['120'], array_map('strval', array_keys($out)));
        $this->assertSame('images/legacy/news/logo.png', $out['120']['path']);
        $this->assertSame('png', $out['120']['format']);
    }

    public function test_a_legacy_asset_in_the_library_shape_is_servable_and_listed(): void
    {
        $asset = $this->asset(Renditions::fromManifest(self::MANIFEST, 'images/legacy/news/photo.jpg', 1200));

        $this->assertStringEndsWith('photo-960.webp', (string) $asset->url(960));

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get('/en/admin/media')->assertOk();
        $this->actingAs($admin)->get('/en/admin/media/'.$asset->id)->assertOk()->assertSee('WEBP');
    }

    public function test_the_migration_rewrites_rows_in_the_manifest_shape_and_leaves_the_rest(): void
    {
        $old = $this->asset(self::MANIFEST);
        $small = $this->asset([], 'images/legacy/news/logo.png', 120);
        $uploaded = $this->asset([], 'media/originals/upload.jpg', 120, null);
        $canonical = $this->asset(['960' => ['path' => 'media/renditions/9/960.webp', 'bytes' => 10, 'format' => 'webp']], source: null);

        $migration = require database_path('migrations/2026_09_17_050000_normalise_legacy_media_renditions.php');
        $migration->up();

        $this->assertSame('images/legacy/news/photo-480.webp', $old->fresh()->renditions['480']['path']);
        $this->assertSame('images/legacy/news/logo.png', $small->fresh()->renditions['120']['path'], 'An imported photograph with no derivative is served at its own width.');
        $this->assertSame([], $uploaded->fresh()->renditions, 'An upload waiting for its renditions is the upload pipeline\'s business.');
        $this->assertSame(10, $canonical->fresh()->renditions['960']['bytes']);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get('/en/admin/media')->assertOk();
    }
}
