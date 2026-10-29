<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\NewsItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The news form's "Upload a photo" and the focal point (Day 3, taxo).
 */
class NewsPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->editor = User::factory()->editor()->create();
    }

    /** @return array<string, mixed> */
    private function form(array $extra = []): array
    {
        return array_merge([
            'category' => 'news', 'status' => 'draft',
            'en' => ['title' => 'Spectrum workshop in Gaborone', 'summary' => 'A summary.', 'body' => 'A body.'],
        ], $extra);
    }

    private function alt(): array
    {
        return ['en' => 'Delegates at the spectrum workshop.', 'fr' => 'Délégués à l\'atelier sur le spectre.', 'pt' => 'Delegados no seminário sobre o espetro.'];
    }

    public function test_the_form_offers_both_ways_and_the_focal_point(): void
    {
        $this->actingAs($this->editor)->get('/en/admin/data/news/new')->assertOk()
            ->assertSee('Choose from the media library')
            ->assertSee('Upload a photo')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="hero_focal_x"', false);
    }

    public function test_an_upload_with_alt_in_three_languages_goes_through_the_media_library(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/data/news', $this->form([
            'photo_upload' => UploadedFile::fake()->image('workshop.jpg', 1600, 900),
            'photo_alt' => $this->alt(),
            'hero_focal_x' => 30,
            'hero_focal_y' => 70,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $asset = MediaAsset::sole();
        $item = NewsItem::sole();

        $this->assertSame($asset->path, $item->hero_image_path);
        $this->assertSame(30, $item->hero_focal_x);
        $this->assertSame(70, $item->hero_focal_y);
        $this->assertSame($this->alt(), array_intersect_key($asset->translationValuesFor('alt'), $this->alt()));
        Storage::disk('public')->assertExists($asset->path);

        if (extension_loaded('imagick')) {
            $this->assertNotEmpty($asset->renditions);
            Storage::disk('public')->assertExists($asset->renditions['800']['path']);
        }
    }

    public function test_an_upload_is_refused_without_alt_in_every_language_and_nothing_is_saved(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/data/news', $this->form([
            'photo_upload' => UploadedFile::fake()->image('workshop.jpg', 800, 600),
            'photo_alt' => ['en' => 'Delegates.', 'fr' => '', 'pt' => ''],
        ]))->assertSessionHasErrors(['photo_alt.fr', 'photo_alt.pt']);

        $this->assertSame(0, MediaAsset::count());
        $this->assertSame(0, NewsItem::count());
    }

    public function test_only_jpg_png_and_webp_up_to_10_mb_are_accepted(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/data/news', $this->form([
            'photo_upload' => UploadedFile::fake()->image('animation.gif', 100, 100),
            'photo_alt' => $this->alt(),
        ]))->assertSessionHasErrors('photo_upload');

        $this->actingAs($this->editor)->post('/en/admin/data/news', $this->form([
            'photo_upload' => UploadedFile::fake()->create('huge.jpg', 11 * 1024, 'image/jpeg'),
            'photo_alt' => $this->alt(),
        ]))->assertSessionHasErrors('photo_upload');

        $this->assertSame(0, NewsItem::count());
    }

    public function test_the_focal_point_is_kept_in_range(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/data/news', $this->form([
            'hero_focal_x' => 140, 'hero_focal_y' => -5,
        ]))->assertSessionHasErrors(['hero_focal_x', 'hero_focal_y']);
    }

    public function test_the_card_and_the_article_hero_use_the_focal_point(): void
    {
        $item = NewsItem::factory()->create(['hero_image_path' => 'media/originals/2026/10/w.jpg', 'hero_focal_x' => 25, 'hero_focal_y' => 80]);
        NewsItem::factory()->create(['hero_image_path' => 'media/originals/2026/10/v.jpg', 'published_at' => now()->subYear()]);

        $this->get('/en/newsroom')->assertOk()->assertSee('style="object-position: 25% 80%"', false);
        $this->get('/en/newsroom/'.$item->slug)->assertOk()->assertSee('object-position: 25% 80%', false);

        // No focal point: the framing each surface used before.
        $plain = NewsItem::factory()->create(['hero_image_path' => 'media/originals/2026/10/x.jpg']);
        $this->assertSame('50% 30%', $plain->heroPosition('50% 30%'));
        $this->get('/en/newsroom/'.$plain->slug)->assertOk()->assertSee('object-position: 50% 30%', false);
    }

    public function test_choosing_no_photograph_clears_the_focal_point(): void
    {
        $item = NewsItem::factory()->draft()->create(['hero_image_path' => 'media/originals/2026/10/w.jpg', 'hero_focal_x' => 25, 'hero_focal_y' => 80]);

        $this->actingAs($this->editor)->put('/en/admin/data/news/'.$item->id, $this->form([
            'hero_image_path' => '', 'hero_focal_x' => 25, 'hero_focal_y' => 80,
        ]))->assertSessionHasNoErrors();

        $this->assertNull($item->fresh()->hero_focal_x);
        $this->assertNull($item->fresh()->hero_image_path);
    }
}
