<?php

namespace Tests\Feature;

use App\Enums\CommitteeRole;
use App\Models\Committee;
use App\Models\Country;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\User;
use App\Support\ShellNavigation;
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
 * The shell, and every page that renders inside it.
 *
 * Screen 09 draws a horizontal section bar and it is overruled, on a ruling
 * of 10 September 2026 and logged as a disclosed variation. With
 * twenty-four destinations the bar wrapped two and three rows deep before
 * any content began, and the back office is the surface the Secretariat
 * uses every day.
 *
 * THE POINT OF THE SECOND HALF OF THIS FILE. A shell rebuild that leaves
 * one screen rendering without its sidebar is a rebuild nobody notices
 * until somebody opens that screen. Every administered route is walked and
 * asserted to carry the shell.
 */
class ShellTest extends TestCase
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
        $this->seed(PublicPagesSeeder::class);
        $this->seed(LegalPagesSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
    }

    /* The shell ---------------------------------------------------------- */

    public function test_the_sidebar_carries_every_group_and_the_signed_in_reader(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->assertOk()->getContent();

        // 264px navy, fixed, full height.
        $css = file_get_contents(public_path('css/admin.css'));
        preg_match('/\.a-side \{([^}]*)\}/', $css, $rule);

        $this->assertNotEmpty($rule);
        $this->assertStringContainsString('width: 264px', $rule[1]);
        $this->assertStringContainsString('background: var(--c-navy)', $rule[1]);
        $this->assertStringContainsString('height: 100vh', $rule[1]);

        // The mark, the five groups, the pinned reader, the way out.
        $this->assertStringContainsString('a-side__mark', $html);
        $this->assertStringContainsString(__('admin.back_office'), $html);

        foreach (['overview', 'content', 'regulatory', 'people', 'platform'] as $group) {
            $this->assertStringContainsString('>'.__('admin.side_group_'.$group).'</h2>', $html);
        }

        $this->assertStringContainsString('a-side__me', $html);
        $this->assertStringContainsString($this->administrator->name, $html);
        $this->assertStringContainsString(__('admin.sign_out'), $html);
    }

    public function test_the_top_bar_carries_the_search_the_languages_and_nothing_else(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->assertOk()->getContent();

        $this->assertStringContainsString('a-top__search', $html);
        $this->assertStringContainsString(__('admin.search_placeholder'), $html);

        // The language pill group, all three, the current one marked.
        $this->assertStringContainsString('a-pills', $html);
        foreach (['EN', 'FR', 'PT'] as $short) {
            $this->assertStringContainsString('>'.$short.'</span>', $html);
        }

        // The bell, the help icon, the initials.
        $this->assertStringContainsString(__('help.title'), $html);
        $this->assertStringContainsString('a-top__avatar', $html);

        /*
         * And not the second row of chips it replaced. "Interface:
         * English", the theme toggle and a labelled help button were three
         * controls a reader touches once a month, in a row in front of the
         * work.
         */
        $this->assertStringNotContainsString('a-chip', $html);
        $this->assertStringNotContainsString(__('admin.interface', ['language' => 'English']), $html);
    }

    /** The current row is marked by ground, an edge and weight, not colour. */
    public function test_the_current_row_is_marked_in_more_than_colour(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin/pages')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/class="a-side__link is-current"[^>]*aria-current="page"/',
            $html
        );

        $css = file_get_contents(public_path('css/admin.css'));
        preg_match('/\.a-side__link\.is-current \{([^}]*)\}/', $css, $rule);

        $this->assertNotEmpty($rule);
        $this->assertStringContainsString('border-left-color: var(--c-green)', $rule[1]);
        $this->assertStringContainsString('font-weight: 600', $rule[1]);
    }

    /** One row current at a time, not seven because they share a route. */
    public function test_only_one_row_is_current(): void
    {
        foreach ([
            '/en/admin',
            '/en/admin/pages',
            '/en/admin/data/publications',
            '/en/admin/data/news',
            '/en/admin/users',
        ] as $url) {
            $html = $this->actingAs($this->administrator)->get($url)->assertOk()->getContent();

            $this->assertSame(
                1,
                substr_count($html, 'class="a-side__link is-current"'),
                $url.' marks more or fewer than one row as current.'
            );
        }
    }

    /** The drawer is an improvement, not the way the navigation exists. */
    public function test_the_navigation_is_in_the_document_without_a_script(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->assertOk()->getContent();

        // Every link is an anchor in the markup, not built by script.
        $this->assertGreaterThan(15, substr_count($html, 'class="a-side__link'));

        $css = file_get_contents(public_path('css/admin.css'));
        $this->assertStringContainsString('html:not(.has-js) .a-side { position: static;', $css);

        $js = file_get_contents(public_path('js/shell.js'));
        $this->assertStringContainsString("addEventListener('keydown'", $js);
        $this->assertStringContainsString("event.key === 'Escape'", $js);
    }

    /* Every page, in the shell -------------------------------------------- */

    /** @return list<string> */
    public static function administrationPages(): array
    {
        $urls = [
            '/en/admin',
            '/en/admin/pages',
            '/en/admin/audit',
            '/en/admin/media',
            '/en/admin/media/upload',
            '/en/admin/menus',
            '/en/admin/translations',
            '/en/admin/submissions',
            '/en/admin/reports',
            '/en/admin/confirmations',
            '/en/admin/users',
            '/en/admin/users/invite',
            '/en/admin/meetings',
            '/en/admin/settings',
            '/en/admin/samples',
            '/en/admin/search?q=roaming',
            '/en/admin/help',
            '/en/admin/data',
        ];

        foreach (array_keys(\App\Admin\ResourceRegistry::all()) as $resource) {
            $urls[] = '/en/admin/data/'.$resource;
        }

        return array_map(fn (string $url) => [$url], $urls);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('administrationPages')]
    public function test_every_administration_page_renders_in_the_shell(string $url): void
    {
        $html = $this->actingAs($this->administrator)->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('class="a-side"', $html, $url.' has no sidebar.');
        $this->assertStringContainsString('class="a-top"', $html, $url.' has no top bar.');
        $this->assertStringContainsString('class="a-main"', $html, $url.' has no content area.');

        // One h1, and the old header gone.
        $this->assertSame(1, substr_count($html, '<h1>'), $url.' does not carry exactly one h1.');
        $this->assertStringNotContainsString('class="a-header"', $html, $url.' still draws the old header.');
    }

    /** The record editors, which take a route parameter. */
    public function test_every_record_editor_renders_in_the_shell(): void
    {
        $page = Page::first();
        $block = $page->blocks()->first();
        $publication = Publication::first();
        $news = NewsItem::first();

        $urls = [
            '/en/admin/pages/'.$page->slug,
            '/en/admin/pages/'.$page->slug.'/blocks/add',
            '/en/admin/data/publications/'.$publication->id,
            '/en/admin/data/news/'.$news->id,
            '/en/admin/data/countries/'.Country::first()->id,
            '/en/admin/data/committees/'.Committee::first()->id,
            '/en/admin/translations/news/'.$news->id,
        ];

        if ($block !== null) {
            $urls[] = '/en/admin/pages/'.$page->slug.'/blocks/'.$block->id;
        }

        foreach ($urls as $url) {
            $html = $this->actingAs($this->administrator)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('class="a-side"', $html, $url.' has no sidebar.');
            $this->assertStringContainsString('class="a-top"', $html, $url.' has no top bar.');
        }
    }

    public function test_every_extranet_page_renders_in_the_shell(): void
    {
        $rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();
        $committee = Committee::where('slug', 'spectrum-and-frequency-management')->sole();

        $rep->committees()->attach($committee->id, [
            'role' => CommitteeRole::Member->value,
            'is_sample' => true,
        ]);

        foreach ([
            '/en/extranet/dashboard',
            '/en/extranet/folders',
            '/en/extranet/calendar',
            '/en/extranet/committees/'.$committee->slug,
            '/en/submissions',
            // A signed-in page both surfaces reach. See layouts.workspace.
            '/en/completeness',
        ] as $url) {
            $html = $this->actingAs($rep)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('class="a-side"', $html, $url.' has no sidebar.');
            $this->assertStringContainsString('class="a-top"', $html, $url.' has no top bar.');
            $this->assertStringNotContainsString('class="e-rail"', $html, $url.' still draws the old rail.');
        }
    }

    /* The search --------------------------------------------------------- */

    public function test_the_search_finds_records_and_groups_them_by_type(): void
    {
        $publication = Publication::first();
        $title = $publication->translated('title', '');
        $word = explode(' ', trim($title))[0];

        $html = $this->actingAs($this->administrator)
            ->get('/en/admin/search?q='.urlencode($word))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('admin.search_group_publications'), $html);

        // Member states, from a two-letter code.
        $this->actingAs($this->administrator)->get('/en/admin/search?q=Botswana')
            ->assertOk()
            ->assertSee(__('admin.search_group_countries'))
            ->assertSee('Botswana');
    }

    /**
     * Every group is gated separately. A search that lists what you cannot
     * open is a directory of what you are not allowed to see.
     */
    public function test_an_editor_does_not_find_people(): void
    {
        $editor = User::factory()->editor()->create();

        /*
         * Asserted on the result group's own heading rather than on the
         * word: the sidebar's PEOPLE group says "People" too, and an
         * editor sees that group because Confirmations is in it. A test
         * that trips on the word is testing English rather than gating.
         */
        $heading = '<h2>'.__('admin.search_group_users').'</h2>';

        $this->actingAs($this->administrator)->get('/en/admin/search?q='.urlencode($editor->email))
            ->assertOk()
            ->assertSee($heading, false);

        $this->actingAs($editor)->get('/en/admin/search?q='.urlencode($editor->email))
            ->assertOk()
            ->assertDontSee($heading, false)
            /*
             * And nothing at all comes back, rather than the record under
             * another heading. The term is echoed into the search field,
             * which is why this asserts the empty state rather than the
             * absence of the address.
             */
            ->assertSee(__('admin.search_empty_title', ['term' => $editor->email]));
    }

    public function test_the_search_says_so_when_nothing_matches(): void
    {
        $this->actingAs($this->administrator)
            ->get('/en/admin/search?q=zzzznothing')
            ->assertOk()
            ->assertSee(__('admin.search_empty_title', ['term' => 'zzzznothing']));
    }

    /* The dashboard ------------------------------------------------------- */

    public function test_the_dashboard_tiles_count_the_database_and_link_to_it(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->assertOk()->getContent();

        foreach ([
            Publication::count(),
            NewsItem::count(),
            Country::whereHas('indicatorValues')->count(),
            User::count(),
        ] as $figure) {
            $this->assertStringContainsString('>'.number_format($figure).'<', $html);
        }

        // Every figure is a link to what it counts.
        $this->assertSame(4, substr_count($html, 'class="a-tile"'));
        $this->assertStringContainsString(
            'href="'.route('admin.resources', ['locale' => 'en', 'resource' => 'publications']).'"',
            $html
        );
    }

    /** The header date is the platform's timezone, not the server's. */
    public function test_the_dashboard_date_uses_the_configured_timezone(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->assertOk()->getContent();

        // The platform's own timezone, not the server's and not UTC.
        // Storage stays UTC; this is what a reader is shown.
        $expected = now()->timezone(config('crasa.timezone'))->translatedFormat('l j F Y');

        $this->assertSame('Africa/Gaborone', config('crasa.timezone'));
        $this->assertSame('UTC', config('app.timezone'), 'Storage must stay UTC.');

        $this->assertStringContainsString($expected, $html);
    }

    /** Every button on the platform carries a label. */
    public function test_no_button_on_the_dashboard_is_empty(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->assertOk()->getContent();

        preg_match_all('/<(?:button|a)[^>]*class="[^"]*\ba-btn\b[^"]*"[^>]*>(.*?)<\/(?:button|a)>/s', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $label) {
            $this->assertNotSame(
                '',
                trim(strip_tags($label)),
                'A button on the dashboard has no text in it.'
            );
        }
    }

    /* The navigation itself ------------------------------------------------ */

    public function test_a_group_with_nothing_in_it_is_not_drawn(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer);

        // A viewer reaches no administration at all, so every group is
        // empty and none is built.
        $this->assertSame([], ShellNavigation::administration());
    }

    public function test_the_extranet_sidebar_carries_a_row_per_seat(): void
    {
        $member = User::factory()->committeeMember(Country::where('iso_code', 'zm')->sole())->create();

        $spectrum = Committee::where('slug', 'spectrum-and-frequency-management')->sole();
        $postal = Committee::where('slug', 'postal-and-logistics')->sole();

        $member->committees()->attach($spectrum->id, [
            'role' => CommitteeRole::Member->value,
            'is_sample' => true,
        ]);

        $html = $this->actingAs($member)->get('/en/extranet/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('<span>'.$spectrum->name.'</span>', $html);
        $this->assertStringNotContainsString('<span>'.$postal->name.'</span>', $html);
    }
}
