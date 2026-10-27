<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\Sector;
use App\Support\ImageLibrary;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * The supplied photograph library, and what reaches a page from it.
 *
 * These tests have moved twice. The homepage hero lost its photograph, so
 * they ran through the newsroom; since 27 October 2026 a news item without
 * a photograph of its own shows its type's placeholder, so they run through
 * the homepage again, whose other blocks still draw from the library.
 *
 * The pipeline is unchanged and still has to hold: derivatives rather than
 * archive originals, WebP with a JPEG fallback, and a crop per breakpoint
 * rather than one image resized.
 */
class ImagePipelineTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();
        $this->seed(SectorSeeder::class);
        $this->seed(NewsroomSeeder::class);
    }

    public function test_a_library_image_is_served_as_derivatives_never_the_original(): void
    {
        // Was /en/newsroom; see the class comment.
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('images/derivatives/', $html);
        // The archive originals are 20 MB files and none of them is a URL.
        $this->assertStringNotContainsString('crasa-images/', $html);
    }

    public function test_a_library_image_offers_a_webp_source_and_a_jpeg_fallback(): void
    {
        // Was /en/newsroom; see the test above.
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('.jpg', $html);
        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringContainsString('sizes=', $html);
    }

    /**
     * Art direction, not resizing. A 21:9 band at desktop is a strip of sky
     * with the subject cropped out of it at phone width, so narrow
     * renditions are cut squarer and each crop is gated by its own media
     * query. A single srcset would tell the browser these are the same image
     * at different sizes and let it pick a crop the layout was not built for.
     */
    public function test_each_breakpoint_gets_its_own_crop(): void
    {
        $ratios = [];

        foreach ([480, 960, 1440] as $width) {
            $path = ImageLibrary::derivativePath('sector-telecom', $width, 'jpg');
            $size = getimagesize(Storage::disk('public')->path($path));
            $ratios[$width] = round($size[0] / $size[1], 2);
        }

        // Squarer at phone width, wider at desktop.
        $this->assertLessThan($ratios[960], $ratios[480]);
        $this->assertLessThan($ratios[1440], $ratios[960]);
    }

    public function test_no_derivative_is_larger_than_a_mobile_connection_can_carry(): void
    {
        $path = ImageLibrary::derivativePath('sector-telecom', 480, 'webp');
        $bytes = Storage::disk('public')->size($path);

        $this->assertLessThan(120_000, $bytes, 'The 480px derivative is too heavy for a mobile connection.');
    }

    /**
     * Broadcasting has no supplied photograph, and borrowing one from
     * telecoms would caption a laboratory as broadcasting.
     */
    public function test_no_sector_borrows_another_sectors_photograph(): void
    {
        $broadcasting = NewsItem::factory()->create([
            'sector_id' => Sector::where('slug', 'broadcasting')->value('id'),
        ]);

        $this->assertNull($broadcasting->fallbackImageSlot());
    }

    /**
     * Every image that reaches a page is described, in all three languages,
     * and not with the English description repeated.
     */
    public function test_alt_text_is_present_in_all_three_languages(): void
    {
        foreach (['sector-telecom', 'sector-postal'] as $slot) {
            $descriptions = [];

            foreach (['en', 'fr', 'pt'] as $locale) {
                $alt = __('images.'.$slot, [], $locale);

                $this->assertNotSame('images.'.$slot, $alt, $slot.' has no '.$locale.' alt text.');
                $this->assertGreaterThan(30, mb_strlen($alt), 'The '.$locale.' alt text for '.$slot.' describes nothing.');

                $descriptions[] = $alt;
            }

            $this->assertCount(3, array_unique($descriptions), $slot.' repeats one description in three languages.');
        }
    }

    /**
     * The designed absence. Screen 02 draws a hatched panel where a
     * photograph is still to come rather than a broken img or a grey box.
     *
     * Nothing renders it today: the hero was its last caller and the hero
     * now has no aside at all. It is kept and tested as a component because
     * it is an approved screen element, and because the day a block needs
     * one the answer should already exist.
     */
    public function test_the_designed_absence_renders_rather_than_a_broken_image(): void
    {
        $this->blade(
            "@include('blocks._slot', ['note' => \$note, 'ratio' => '3:2'])",
            ['note' => 'A photograph of the plenary is still to come.']
        )
            ->assertSee('b__slot', false)
            ->assertSee('A photograph of the plenary is still to come.')
            ->assertSee('3:2')
            ->assertDontSee('<img', false);
    }
}
