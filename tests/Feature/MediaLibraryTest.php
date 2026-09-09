<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\User;
use App\Services\MediaLibrary;
use App\Support\MediaUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The media library, CM-03.
 *
 * Two of these matter more than the rest. EXIF stripping, because a
 * photograph taken at a CRASA meeting carries the coordinates of the room
 * and cannot be un-published once a crawler has it. And the alt text rule,
 * because screen 14 states it as a rule -- "this asset cannot appear on the
 * Portuguese site until it is filled" -- and a rule that is only a warning
 * is not a rule.
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->editor = User::factory()->editor()->create();
    }

    /** A small JPEG carrying a GPS tag and an author comment. */
    private function photograph(int $width = 1200, int $height = 800): UploadedFile
    {
        $image = new \Imagick;
        $image->newImage($width, $height, new \ImagickPixel('#1A8C5A'));
        $image->setImageFormat('jpeg');
        $image->setImageProperty('exif:GPSLatitude', '24/1, 39/1, 0/1');
        $image->setImageProperty('comment', 'Photographer: a member of the Secretariat');

        $path = tempnam(sys_get_temp_dir(), 'crasa').'.jpg';
        $image->writeImage($path);
        $image->clear();

        return new UploadedFile($path, 'agm-2025-plenary.jpg', 'image/jpeg', null, true);
    }

    /* Access ---------------------------------------------------------------- */

    public function test_a_viewer_reaches_no_part_of_the_library(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/media')->assertForbidden();
        $this->actingAs($viewer)->post('/en/admin/media', ['file' => $this->photograph()])->assertForbidden();
    }

    /* Storing --------------------------------------------------------------- */

    public function test_an_upload_is_stored_with_its_renditions(): void
    {
        $this->actingAs($this->editor)
            ->post('/en/admin/media', ['file' => $this->photograph()])
            ->assertRedirect();

        $asset = MediaAsset::sole();

        $this->assertSame('agm-2025-plenary.jpg', $asset->original_name);
        $this->assertSame(1200, $asset->width);
        Storage::disk('public')->assertExists($asset->path);

        // 400, 800 and 1200 fit inside a 1200px original. 1600 does not, and
        // upscaling would produce a blurred file, not a bigger one.
        $this->assertSame([400, 800, 1200], array_keys($asset->renditions));

        foreach ($asset->renditions as $rendition) {
            Storage::disk('public')->assertExists($rendition['path']);
            $this->assertSame('webp', $rendition['format']);
        }
    }

    public function test_exif_is_stripped_from_the_stored_file(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/media', ['file' => $this->photograph()]);

        $asset = MediaAsset::sole();

        $stored = new \Imagick;
        $stored->readImageBlob(Storage::disk('public')->get($asset->path));

        // Nothing about where the photograph was taken, on what, or by whom.
        $this->assertSame([], array_filter(
            $stored->getImageProperties('exif:*'),
            fn ($value) => trim((string) $value) !== ''
        ));
        $this->assertSame('', (string) $stored->getImageProperty('comment'));
    }

    public function test_the_same_file_uploaded_twice_is_one_asset(): void
    {
        $file = $this->photograph();

        $this->actingAs($this->editor)->post('/en/admin/media', ['file' => $file]);
        $this->actingAs($this->editor)->post('/en/admin/media', ['file' => $this->photograph()]);

        // A library holding the same plenary photograph under two names is
        // a library nobody searches twice.
        $this->assertSame(1, MediaAsset::count());
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->editor)
            ->post('/en/admin/media', [
                'file' => UploadedFile::fake()->create('return.xlsx', 40, 'application/vnd.ms-excel'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MediaAsset::count());
    }

    /* Alt text -------------------------------------------------------------- */

    public function test_an_asset_may_not_appear_in_a_language_it_has_no_alt_text_for(): void
    {
        $asset = $this->uploadedAsset();
        $asset->setTranslations('en', ['alt' => 'Delegates seated in the plenary hall.']);
        $asset->setTranslations('fr', ['alt' => 'Delegues assis dans la salle pleniere.']);

        $asset->refresh()->load('translations');

        $this->assertTrue($asset->mayAppearIn('en'));
        $this->assertTrue($asset->mayAppearIn('fr'));
        $this->assertFalse($asset->mayAppearIn('pt'));
        $this->assertFalse($asset->hasCompleteAlt());
    }

    public function test_english_alt_text_does_not_stand_in_for_a_missing_language(): void
    {
        $asset = $this->uploadedAsset();
        $asset->setTranslations('en', ['alt' => 'Delegates seated in the plenary hall.']);
        $asset->refresh()->load('translations');

        // The fallback chain is right for body copy and wrong here: it
        // would put English alt text on the Portuguese site and call the
        // asset done.
        $this->assertSame('Delegates seated in the plenary hall.', $asset->translate('alt', 'pt')?->value);
        $this->assertFalse($asset->mayAppearIn('pt'));
    }

    public function test_the_library_counts_and_filters_the_assets_missing_alt_text(): void
    {
        $complete = $this->uploadedAsset();

        foreach (['en', 'fr', 'pt'] as $locale) {
            $complete->setTranslations($locale, ['alt' => 'Described in '.$locale]);
        }

        $incomplete = MediaAsset::create([
            'disk' => 'public', 'path' => 'media/originals/x.jpg', 'original_name' => 'x.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 10, 'checksum' => str_repeat('b', 64),
        ]);
        $incomplete->setTranslations('en', ['alt' => 'English only']);

        $this->assertSame([$incomplete->id], MediaAsset::missingAlt()->pluck('id')->all());

        $this->actingAs($this->editor)
            ->get('/en/admin/media?missing_alt=1')
            ->assertOk()
            ->assertSee('x.jpg')
            ->assertDontSee($complete->original_name);
    }

    /* Usage ----------------------------------------------------------------- */

    public function test_the_library_says_where_an_asset_is_used(): void
    {
        $asset = $this->uploadedAsset();

        $page = Page::create(['slug' => 'about-us', 'template' => 'standard', 'status' => 'published']);
        $page->setTranslations('en', ['title' => 'About us']);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'position' => 0,
            'is_visible' => true,
            'settings' => ['image' => $asset->reference()],
        ]);

        $usage = MediaUsage::for($asset);

        $this->assertCount(1, $usage);
        $this->assertStringContainsString('About us', $usage[0]['label']);
    }

    public function test_an_asset_id_that_is_only_a_substring_is_not_reported_as_usage(): void
    {
        $asset = $this->uploadedAsset();

        $page = Page::create(['slug' => 'other', 'template' => 'standard', 'status' => 'published']);

        // A block referencing a different asset whose reference contains
        // this one's as a substring.
        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'position' => 0,
            'is_visible' => true,
            'settings' => ['image' => 'media:'.$asset->id.'00'],
        ]);

        $this->assertSame([], MediaUsage::for($asset));
    }

    public function test_deleting_is_refused_while_the_asset_is_on_a_page(): void
    {
        $administrator = User::factory()->administrator()->create();
        $asset = $this->uploadedAsset();

        $page = Page::create(['slug' => 'live', 'template' => 'standard', 'status' => 'published']);
        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'position' => 0,
            'is_visible' => true,
            'settings' => ['image' => $asset->reference()],
        ]);

        $this->actingAs($administrator)
            ->from('/en/admin/media/'.$asset->id)
            ->delete('/en/admin/media/'.$asset->id)
            ->assertSessionHasErrors('asset');

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'deleted_at' => null]);
    }

    public function test_an_editor_cannot_delete_an_asset_at_all(): void
    {
        $asset = $this->uploadedAsset();

        $this->actingAs($this->editor)
            ->delete('/en/admin/media/'.$asset->id)
            ->assertForbidden();
    }

    /* Replacing ------------------------------------------------------------- */

    public function test_replacing_the_file_keeps_the_record_its_alt_text_and_its_references(): void
    {
        $asset = $this->uploadedAsset();
        $asset->setTranslations('pt', ['alt' => 'Delegados na sala plenaria.']);

        $page = Page::create(['slug' => 'live', 'template' => 'standard', 'status' => 'published']);
        PageBlock::create([
            'page_id' => $page->id, 'block_type' => BlockType::Hero, 'position' => 0,
            'is_visible' => true, 'settings' => ['image' => $asset->reference()],
        ]);

        $before = $asset->path;

        $this->actingAs($this->editor)
            ->from('/en/admin/media/'.$asset->id)
            ->post('/en/admin/media/'.$asset->id.'/replace', ['file' => $this->photograph(900, 600)])
            ->assertRedirect();

        $asset->refresh()->load('translations');

        $this->assertNotSame($before, $asset->path);
        $this->assertSame(900, $asset->width);
        $this->assertSame('Delegados na sala plenaria.', $asset->translate('alt', 'pt')->value);
        $this->assertCount(1, MediaUsage::for($asset));
        Storage::disk('public')->assertMissing($before);
    }

    /* Serving --------------------------------------------------------------- */

    public function test_the_url_is_always_a_rendition_and_never_the_original(): void
    {
        $asset = $this->uploadedAsset();

        foreach ([100, 400, 800, 2400] as $width) {
            $url = $asset->url($width);

            $this->assertNotNull($url);
            $this->assertStringContainsString('media/renditions/', $url);
            $this->assertStringNotContainsString('media/originals/', $url);
        }
    }

    public function test_an_asset_with_no_renditions_has_no_url_rather_than_a_broken_one(): void
    {
        $asset = MediaAsset::create([
            'disk' => 'public', 'path' => 'media/originals/y.jpg', 'original_name' => 'y.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 10, 'checksum' => str_repeat('c', 64),
        ]);

        $this->assertNull($asset->url());
    }

    /* Rendering on the public site ------------------------------------------ */

    public function test_an_uploaded_image_renders_on_a_page_in_the_languages_it_is_described_in(): void
    {
        $asset = $this->uploadedAsset();
        $asset->setTranslations('en', ['alt' => 'Delegates seated in the plenary hall.']);
        $asset->setTranslations('fr', ['alt' => 'Delegues assis dans la salle pleniere.']);

        $page = Page::create(['slug' => 'hero-page', 'template' => 'standard', 'status' => 'published', 'published_at' => now()]);
        $page->setTranslations('en', ['title' => 'Hero page']);

        $block = PageBlock::create([
            'page_id' => $page->id, 'block_type' => BlockType::Hero, 'position' => 0,
            'is_visible' => true, 'settings' => ['image' => $asset->reference()],
        ]);
        $block->setTranslations('en', ['headline' => 'Harmonisation across fourteen member states']);
        $block->setTranslations('fr', ['headline' => 'Harmonisation dans quatorze Etats membres']);
        $block->setTranslations('pt', ['headline' => 'Harmonizacao em catorze Estados membros']);

        $this->get('/en/hero-page')
            ->assertOk()
            ->assertSee('Delegates seated in the plenary hall.', false)
            ->assertSee('media/renditions/', false);

        $this->get('/fr/hero-page')
            ->assertOk()
            ->assertSee('Delegues assis dans la salle pleniere.', false);

        // Portuguese has no alt text, so the image is held rather than
        // published with an empty alt attribute or an English sentence.
        $portuguese = $this->get('/pt/hero-page')->assertOk()->getContent();

        $this->assertStringNotContainsString('media/renditions/', $portuguese);
        $this->assertStringNotContainsString('Delegates seated', $portuguese);
        // The page itself still renders. One missing alt string does not
        // take a page down.
        $this->assertStringContainsString('Harmonizacao em catorze Estados membros', $portuguese);
    }

    private function uploadedAsset(): MediaAsset
    {
        return app(MediaLibrary::class)->store($this->photograph(), $this->editor->id);
    }
}
