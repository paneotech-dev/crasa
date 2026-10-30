<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\User;
use App\Services\MediaLibrary;
use App\Support\ImageSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Item 1i: recommended sizes per image slot, a preview before upload and a
 * warning (never a refusal) when an image is smaller than its slot.
 */
class MediaSlotSizesTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->editor = User::factory()->editor()->create();
    }

    private function image(int $width, int $height, string $name = 'slide.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    public function test_the_upload_form_shows_the_recommended_size_of_every_slot(): void
    {
        $this->actingAs($this->editor)->get('/en/admin/media/upload')->assertOk()
            ->assertSee('Used for')
            ->assertSee('Hero slider: at least 1600 x 1200 px, landscape 4:3')
            ->assertSee('News card and article: at least 1600 x 1000 px, landscape 16:10')
            ->assertSee('People photo: at least 400 x 400 px, square 1:1')
            ->assertSee('Other: no recommended size')
            ->assertSee('name="used_for" value="hero"', false)
            ->assertSee('data-min-w="1600" data-min-h="1200"', false);
    }

    public function test_the_sizes_are_translated(): void
    {
        $this->actingAs($this->editor)->get('/fr/admin/media/upload')->assertOk()
            ->assertSee('Diaporama d’accueil : au moins 1600 x 1200 px', false);
        $this->actingAs($this->editor)->get('/pt/admin/media/upload')->assertOk()
            ->assertSee('Carrossel da página inicial: pelo menos 1600 x 1200 px', false);
    }

    public function test_the_preview_is_in_the_markup_and_hidden_until_script_fills_it(): void
    {
        $response = $this->actingAs($this->editor)->get('/en/admin/media/upload')->assertOk();

        // Without script the form is a plain upload: preview and early
        // warning stay hidden, the sizes are printed text.
        $response->assertSee('data-media-slots data-file-input="file"', false)
            ->assertSee('<figure class="m3-slots__preview" data-slot-preview hidden>', false)
            ->assertSee('data-slot-warning hidden', false)
            ->assertSee('js/media-slots.js', false);

        $this->assertFileExists(public_path('js/media-slots.js'));
    }

    public function test_a_small_image_is_stored_with_a_warning(): void
    {
        $this->actingAs($this->editor)
            ->post('/en/admin/media', ['file' => $this->image(800, 450), 'used_for' => 'hero'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('m3_media_warning', fn ($w) => str_contains($w, '800 x 450 px') && str_contains($w, 'Hero slider'));

        $asset = MediaAsset::sole();

        $this->actingAs($this->editor)
            ->withSession(['m3_media_warning' => ImageSlots::warning($asset, 'hero')])
            ->get('/en/admin/media/'.$asset->id)
            ->assertOk()
            ->assertSee('smaller than the 1600 x 1200 px recommended for Hero slider');
    }

    public function test_a_large_image_has_no_warning(): void
    {
        $this->actingAs($this->editor)
            ->post('/en/admin/media', ['file' => $this->image(2000, 1500), 'used_for' => 'hero'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('m3_media_warning');

        $this->assertFalse(ImageSlots::tooSmall(2000, 1500, 'hero'));
        $this->assertTrue(ImageSlots::tooSmall(1600, 900, 'hero'));
        $this->assertFalse(ImageSlots::tooSmall(10, 10, 'other'));
        $this->assertFalse(ImageSlots::tooSmall(null, null, 'hero'));
    }

    public function test_an_unknown_slot_is_refused(): void
    {
        $this->actingAs($this->editor)
            ->post('/en/admin/media', ['file' => $this->image(800, 450), 'used_for' => 'banner'])
            ->assertSessionHasErrors('used_for');
    }

    public function test_the_list_notes_an_image_too_small_for_the_slot_it_is_in(): void
    {
        $small = app(MediaLibrary::class)->store($this->image(800, 450, 'small.jpg'), $this->editor->id);
        $large = app(MediaLibrary::class)->store($this->image(2000, 1500, 'large.jpg'), $this->editor->id);
        $page = Page::create(['slug' => 'home-test', 'template' => 'standard', 'status' => 'published']);

        foreach ([$small, $large] as $i => $asset) {
            PageBlock::create([
                'page_id' => $page->id, 'block_type' => BlockType::Hero, 'position' => $i,
                'is_visible' => true, 'settings' => ['image' => $asset->reference()],
            ]);
        }

        foreach (['grid', 'list'] as $view) {
            $response = $this->actingAs($this->editor)->get('/en/admin/media?view='.$view)->assertOk()
                ->assertSee('800 x 450 px')
                ->assertSee('2000 x 1500 px')
                ->assertSee('Smaller than recommended for Hero slider');

            $this->assertSame(1, substr_count($response->getContent(), 'data-small-for="hero"'));
        }
    }

    public function test_the_news_form_upload_has_the_slots_and_warns_on_a_small_photo(): void
    {
        $this->actingAs($this->editor)->get('/en/admin/data/news/new')->assertOk()
            ->assertSee('data-file-input="photo_upload"', false)
            ->assertSee('name="photo_used_for" value="news" checked', false);

        $this->actingAs($this->editor)->post('/en/admin/data/news', [
            'category' => 'news', 'status' => 'draft',
            'en' => ['title' => 'Spectrum workshop', 'summary' => 'A summary.', 'body' => 'A body.'],
            'photo_upload' => $this->image(640, 400, 'workshop.jpg'),
            'photo_used_for' => 'news',
            'photo_alt' => ['en' => 'Delegates.', 'fr' => 'Délégués.', 'pt' => 'Delegados.'],
        ])->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('m3_media_warning', fn ($w) => str_contains($w, '640 x 400 px'));
    }

    public function test_the_rendition_widths_are_unchanged(): void
    {
        $this->assertSame([400, 800, 1200, 1600], MediaLibrary::WIDTHS);
    }

    public function test_a_viewer_cannot_upload(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/media/upload')->assertForbidden();
        $this->actingAs($viewer)->post('/en/admin/media', ['file' => $this->image(800, 450), 'used_for' => 'hero'])->assertForbidden();
    }
}
