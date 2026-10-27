<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\NewsItem;
use App\Models\User;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The editor picks a news item's photograph from the media library, and the
 * public newsroom shows it.
 */
class NewsPhotographFieldTest extends TestCase
{
    use RefreshDatabase;

    private function asset(string $path): MediaAsset
    {
        Storage::disk('public')->put($path, 'jpeg');

        return MediaAsset::query()->forceCreate([
            'disk' => 'public', 'path' => $path, 'original_name' => basename($path),
            'mime_type' => 'image/jpeg', 'size_bytes' => 4, 'width' => 1600, 'height' => 1000,
            'checksum' => sha1($path), 'is_sample' => true,
        ]);
    }

    public function test_an_editor_chooses_a_library_photograph_and_the_newsroom_shows_it(): void
    {
        Storage::fake('public');
        $this->seed(SectorSeeder::class);
        $asset = $this->asset('media/originals/2026/10/workshop.jpg');
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->get('/en/admin/data/news/new')
            ->assertOk()
            ->assertSee('name="hero_image_path"', false)
            ->assertSee('workshop.jpg');

        $this->actingAs($editor)->post('/en/admin/data/news', [
            'category' => 'news',
            'status' => 'published',
            'published_at' => now()->toDateString(),
            'hero_image_path' => $asset->path,
            'en' => ['title' => 'Workshop opens in Lilongwe', 'summary' => 'Delegates met.', 'body' => 'Delegates met.'],
            'fr' => ['title' => 'Ouverture de l\'atelier a Lilongwe', 'summary' => 'Les delegues.', 'body' => 'Les delegues.'],
        ])->assertRedirect();

        $item = NewsItem::query()->latest('id')->firstOrFail();
        $this->assertSame($asset->path, $item->hero_image_path);

        $this->get('/en/newsroom')->assertOk()->assertSee('workshop.jpg', false)->assertSee('Workshop opens in Lilongwe');
    }

    public function test_a_path_outside_the_library_is_refused(): void
    {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post('/en/admin/data/news', [
            'category' => 'news', 'status' => 'draft',
            'hero_image_path' => '../../.env',
            'en' => ['title' => 'x', 'summary' => 'x', 'body' => 'x'],
        ])->assertSessionHasErrors('hero_image_path');
    }
}
