<?php

namespace Tests\Feature;

use App\Enums\CommitteeRole;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
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
 * The chrome around the work: the top bar and the tables.
 *
 * BOTH OF THESE WERE REPORTED AS ONE PAGE MISBEHAVING AND WERE ONE CAUSE.
 * On the audit log the top bar's right end -- the pill group, the bell, the
 * help icon, the avatar -- was off screen, and on the publications table
 * the Status column was cut off. The markup was identical on every page.
 * A table wider than the space beside the sidebar pushed the document past
 * the viewport, and .a-top is sticky, so it scrolled sideways with
 * everything else.
 *
 * The shell body clips its own horizontal overflow now, and every table
 * scrolls inside its own wrapper. Both halves are held here.
 */
class ShellChromeTest extends TestCase
{
    use RefreshDatabase;

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
        $this->seed(IndicatorValueSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(LegalPagesSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
    }

    /** @return list<array{string}> */
    public static function shellPages(): array
    {
        $urls = [
            '/en/admin', '/en/admin/pages', '/en/admin/audit', '/en/admin/media',
            '/en/admin/media/upload', '/en/admin/menus', '/en/admin/translations',
            '/en/admin/submissions', '/en/admin/reports', '/en/admin/confirmations',
            '/en/admin/users', '/en/admin/users/invite', '/en/admin/meetings',
            '/en/admin/settings', '/en/admin/samples', '/en/admin/help',
            '/en/admin/data', '/en/admin/search?q=roaming', '/en/completeness',
        ];

        foreach (array_keys(\App\Admin\ResourceRegistry::all()) as $resource) {
            $urls[] = '/en/admin/data/'.$resource;
        }

        return array_map(fn (string $url) => [$url], $urls);
    }

    private function topBar(string $html): string
    {
        $start = strpos($html, '<header class="a-top">');

        return $start === false ? '' : substr($html, $start, strpos($html, '</header>', $start) - $start);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shellPages')]
    public function test_the_top_bar_is_whole_on_every_page(string $url): void
    {
        $top = $this->topBar($this->actingAs($this->administrator)->get($url)->assertOk()->getContent());

        $this->assertNotSame('', $top, $url.' has no top bar.');

        $this->assertStringContainsString('a-top__search', $top, $url.' has no search field.');
        $this->assertSame(3, substr_count($top, 'a-pills__item'), $url.' does not carry all three languages.');
        $this->assertStringContainsString('/admin/help', $top, $url.' has no help icon.');
        $this->assertStringContainsString('a-top__avatar', $top, $url.' has no avatar.');
        // The bell, by its own path rather than by a class it shares.
        $this->assertStringContainsString('#notifications', $top, $url.' has no notifications icon.');
    }

    public function test_the_top_bar_is_whole_across_the_extranet(): void
    {
        $rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();
        $committee = Committee::where('slug', 'spectrum-and-frequency-management')->sole();

        $rep->committees()->attach($committee->id, [
            'role' => CommitteeRole::Member->value,
            'is_sample' => true,
        ]);

        foreach ([
            '/en/extranet/dashboard', '/en/extranet/folders', '/en/extranet/calendar',
            '/en/extranet/committees/'.$committee->slug, '/en/submissions',
        ] as $url) {
            $top = $this->topBar($this->actingAs($rep)->get($url)->assertOk()->getContent());

            $this->assertStringContainsString('a-top__search', $top, $url);
            $this->assertSame(3, substr_count($top, 'a-pills__item'), $url);
            $this->assertStringContainsString('a-top__avatar', $top, $url);
        }
    }

    /**
     * The layout half. A sticky bar inside a document that scrolls sideways
     * scrolls sideways with it, so the shell body contains its own
     * overflow -- with clip rather than hidden, because hidden would make
     * it a scrollport and stop the bar sticking at all.
     */
    public function test_the_shell_body_cannot_scroll_sideways(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        preg_match('/\.a-shell__body \{([^}]*)\}/', $css, $rule);

        $this->assertNotEmpty($rule);
        $this->assertStringContainsString('overflow-x: clip', $rule[1]);
        $this->assertStringNotContainsString('overflow-x: hidden', $rule[1]);
        $this->assertStringContainsString('min-width: 0', $rule[1]);
    }

    /* Tables --------------------------------------------------------------- */

    /**
     * Every table on a shell page is inside a wrapper that scrolls.
     *
     * .a-table carries a min-width, which is right: a regulatory table
     * squeezed to 320px is unreadable. What that requires is somewhere for
     * the excess to go, and a card with overflow:hidden and no wrapper
     * simply loses the last column.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shellPages')]
    public function test_every_table_scrolls_inside_its_own_wrapper(string $url): void
    {
        $html = $this->actingAs($this->administrator)->get($url)->assertOk()->getContent();

        preg_match_all('/<table\b[^>]*class="[^"]*\ba-table\b[^"]*"[^>]*>/i', $html, $tables, PREG_OFFSET_CAPTURE);

        foreach ($tables[0] as [$tag, $offset]) {
            /*
             * A table that has cancelled its own min-width fits whatever
             * holds it and has nothing to scroll. The chart tables under
             * the dashboard's details elements are the case: they are the
             * text equivalent of a bar chart, three columns wide.
             */
            if (str_contains(str_replace(' ', '', $tag), 'min-width:0')) {
                continue;
            }

            $before = substr($html, max(0, $offset - 400), min(400, $offset));

            $this->assertStringContainsString(
                'a-tablewrap',
                $before,
                $url.' has a table that is not inside a scrolling wrapper: '.substr($tag, 0, 80)
            );
        }
    }

    public function test_the_table_wrapper_scrolls_and_the_card_only_clips_its_corners(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        preg_match('/\.a-tablewrap \{([^}]*)\}/', $css, $wrap);

        $this->assertNotEmpty($wrap);
        $this->assertStringContainsString('overflow-x: auto', $wrap[1]);
        $this->assertStringContainsString('min-width: 0', $wrap[1]);
    }
}
