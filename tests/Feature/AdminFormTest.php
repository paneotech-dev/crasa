<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\Country;
use App\Models\MediaAsset;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\User;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every form in the administration wears the same clothes.
 *
 * The navigation builder was the case that made this a test. Its add form
 * mixed .c-input, drawn for the public site, with .s-field, drawn for the
 * submission e-Form, inside an admin panel, and came out looking like a
 * browser default with a full-width green button under it. Three component
 * sets on one form is how the fourth one gets written.
 *
 * WHAT IS CHECKED. Every text, date, number, email, url, search, select and
 * textarea served on an administered page carries a component class.
 * Checkboxes and radios are excluded: they are drawn by the browser on
 * purpose, because a custom one has to reimplement focus, the indeterminate
 * state and the platform's own high-contrast rendering, and the platform
 * already styles them where they sit.
 */
class AdminFormTest extends TestCase
{
    use RefreshDatabase;

    /** The classes a control may carry. */
    private const COMPONENTS = ['a-input', 'a-select', 'a-filter', 'a-search__field', 'a-top__field', 'a-tbox'];

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(LegalPagesSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
    }

    /**
     * Every control on a page that is not styled by a component.
     *
     * @return list<string>
     */
    private function unstyled(string $html, string $url): array
    {
        preg_match_all('/<(input|select|textarea)\b[^>]*>/i', $html, $matches);

        $offenders = [];

        foreach ($matches[0] as $tag) {
            // Hidden fields, and the buttons that happen to be inputs, are
            // not controls a reader types into.
            if (preg_match('/type\s*=\s*"(hidden|submit|button|image)"/i', $tag)) {
                continue;
            }

            // Checkboxes and radios keep the browser's own drawing. See the
            // class note.
            if (preg_match('/type\s*=\s*"(checkbox|radio)"/i', $tag)) {
                continue;
            }

            preg_match('/\sclass\s*=\s*"([^"]*)"/i', $tag, $class);
            $classes = preg_split('/\s+/', trim($class[1] ?? ''));

            if (array_intersect($classes, self::COMPONENTS) !== []) {
                continue;
            }

            $offenders[] = $url.' -> '.substr(preg_replace('/\s+/', ' ', $tag), 0, 120);
        }

        return $offenders;
    }

    /** @return list<array{string}> */
    public static function formPages(): array
    {
        return array_map(fn (string $url) => [$url], [
            '/en/admin', '/en/admin/pages', '/en/admin/audit', '/en/admin/media',
            '/en/admin/media/upload', '/en/admin/menus', '/en/admin/translations',
            '/en/admin/submissions', '/en/admin/reports', '/en/admin/confirmations',
            '/en/admin/users', '/en/admin/users/invite', '/en/admin/meetings',
            '/en/admin/settings', '/en/admin/samples', '/en/admin/search?q=x',
            '/en/admin/data', '/en/admin/data/publications', '/en/admin/data/news',
            '/en/admin/data/countries', '/en/admin/data/authorities',
            '/en/admin/data/committees', '/en/admin/data/indicators',
            '/en/admin/data/instruments', '/en/admin/data/indicator-values',
            '/en/admin/data/tags', '/en/admin/data/enquiries', '/en/admin/data/sectors',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formPages')]
    public function test_no_control_on_an_administration_page_is_browser_default(string $url): void
    {
        $html = $this->actingAs($this->administrator)->get($url)->assertOk()->getContent();

        $this->assertSame([], $this->unstyled($html, $url));
    }

    /** The editors, which take a route parameter. */
    public function test_no_control_on_a_record_editor_is_browser_default(): void
    {
        $page = Page::first();
        $block = $page->blocks()->first();

        $asset = MediaAsset::create([
            'disk' => 'public',
            'path' => 'media/plenary.jpg',
            'original_name' => 'plenary.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => str_repeat('a', 64),
            'uploaded_by' => $this->administrator->id,
            'is_sample' => true,
        ]);

        $urls = [
            '/en/admin/pages/'.$page->slug,
            '/en/admin/pages/'.$page->slug.'/blocks/add',
            '/en/admin/data/publications/'.Publication::first()->id,
            '/en/admin/data/news/'.NewsItem::first()->id,
            '/en/admin/data/countries/'.Country::first()->id,
            '/en/admin/data/committees/'.Committee::first()->id,
            '/en/admin/translations/news/'.NewsItem::first()->id,
            '/en/admin/media/'.$asset->id,
            '/en/admin/menus?edit='.\App\Models\MenuItem::first()?->id,
        ];

        if ($block !== null) {
            $urls[] = '/en/admin/pages/'.$page->slug.'/blocks/'.$block->id;
        }

        $offenders = [];

        foreach ($urls as $url) {
            $html = $this->actingAs($this->administrator)->get($url)->assertOk()->getContent();
            $offenders = array_merge($offenders, $this->unstyled($html, $url));
        }

        $this->assertSame([], $offenders);
    }

    public function test_no_control_in_the_extranet_is_browser_default(): void
    {
        $rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();

        $offenders = [];

        foreach (['/en/extranet/dashboard', '/en/extranet/folders', '/en/submissions', '/en/completeness'] as $url) {
            $html = $this->actingAs($rep)->get($url)->assertOk()->getContent();
            $offenders = array_merge($offenders, $this->unstyled($html, $url));
        }

        $this->assertSame([], $offenders);
    }

    /* The pattern itself ---------------------------------------------------- */

    public function test_the_form_pattern_is_written_once(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        preg_match('/\.a-form \{([^}]*)\}/', $css, $form);
        $this->assertNotEmpty($form);
        $this->assertStringContainsString('max-width: 640px', $form[1]);
        $this->assertStringContainsString('gap: 20px', $form[1]);

        preg_match('/\.a-input,\s*\.a-select \{([^}]*)\}/', $css, $control);
        $this->assertNotEmpty($control);
        $this->assertStringContainsString('height: 40px', $control[1]);
        $this->assertStringContainsString('border: 1.5px solid var(--c-border)', $control[1]);
        $this->assertStringContainsString('border-radius: var(--r-sm)', $control[1]);
        $this->assertStringContainsString('padding: 0 12px', $control[1]);

        preg_match('/\.a-label \{([^}]*)\}/', $css, $label);
        $this->assertStringContainsString('font-size: 13.5px', $label[1]);
        $this->assertStringContainsString('font-weight: 600', $label[1]);

        // Amber focus ring, as everywhere else on the platform.
        $this->assertMatchesRegularExpression(
            '/\.a-input:focus-visible,\s*\.a-select:focus-visible \{[^}]*outline: 3px solid var\(--c-amber\)/',
            $css
        );
    }

    /**
     * Actions at the bottom right, auto width. A full-width green button is
     * a phone pattern on a screen that is not a phone, and it makes the
     * least reversible control on the form the largest thing on it.
     */
    public function test_form_actions_are_never_full_width(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        preg_match('/\.a-form__actions \{([^}]*)\}/', $css, $actions);

        $this->assertNotEmpty($actions);
        $this->assertStringContainsString('justify-content: flex-end', $actions[1]);
        $this->assertMatchesRegularExpression('/\.a-form__actions \.a-btn \{[^}]*width: auto/', $css);
    }

    /** Required is a glyph and a word, never colour alone. SC 1.4.1. */
    public function test_required_is_marked_in_more_than_colour(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin/menus')->assertOk()->getContent();

        $this->assertStringContainsString('class="a-required"', $html);
        $this->assertMatchesRegularExpression(
            '/<span class="a-required" aria-hidden="true">\*<\/span>\s*<span class="c-sr">/',
            $html
        );
    }
}
