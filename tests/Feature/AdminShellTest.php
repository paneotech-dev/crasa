<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Publication;
use App\Models\User;
use App\Support\Locales;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The back office shell and dashboard, screen 09.
 *
 * The point of most of these is that the dashboard tells the truth. A
 * platform whose figures end up in ministerial briefs cannot have a panel
 * that invents a number when its source is missing, because then no figure
 * on the page can be trusted.
 */
class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
    }

    public function test_the_dashboard_is_closed_to_a_guest(): void
    {
        $this->get('/en/admin')->assertRedirect();
    }

    public function test_a_viewer_cannot_open_the_dashboard_or_see_its_sections(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin')->assertForbidden();
    }

    public function test_an_administrator_opens_the_dashboard_in_every_locale(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->actingAs($this->administrator)
                ->get("/{$locale}/admin")
                ->assertOk()
                // The shell, not the header bar screen 09 draws.
                // Ruled out on 10 September 2026; see ShellNavigation.
                ->assertSee('a-side', false)
                ->assertSee('a-top', false);
        }
    }

    public function test_the_shell_uses_the_admin_surface_and_not_the_public_one(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->assertOk()->getContent();

        // Screen 09 sets font-family:var(--f-admin) on the body and gives
        // the back office its own ground.
        $this->assertStringContainsString('data-surface="admin"', $html);
        $this->assertStringContainsString('css/admin.css', $html);

        // And it is not the public chrome: no site footer, no public nav.
        $this->assertStringNotContainsString('c-footer', $html);
    }

    public function test_the_back_office_is_never_indexed(): void
    {
        $this->actingAs($this->administrator)
            ->get('/en/admin')
            ->assertSee('noindex', false);
    }

    public function test_the_sidebar_draws_only_what_this_user_can_open(): void
    {
        $editor = User::factory()->editor()->create();

        $asAdministrator = $this->actingAs($this->administrator)->get('/en/admin')->getContent();
        $this->assertStringContainsString('<span>'.__('admin.nav_users').'</span>', $asAdministrator);
        $this->assertStringContainsString('<span>'.__('admin.nav_settings').'</span>', $asAdministrator);

        /*
         * An editor cannot manage users, so the row is absent rather than
         * disabled: a disabled control is a promise that it will work
         * later. The route refuses them regardless, which is the part that
         * matters.
         */
        $asEditor = $this->actingAs($editor)->get('/en/admin')->getContent();
        $this->assertStringNotContainsString('<span>'.__('admin.nav_users').'</span>', $asEditor);
        $this->assertStringNotContainsString('<span>'.__('admin.nav_settings').'</span>', $asEditor);
        $this->actingAs($editor)->get('/en/admin/users')->assertForbidden();
        $this->actingAs($editor)->get('/en/admin/settings')->assertForbidden();

        // And a group with nothing left in it is not drawn as an empty
        // heading.
        $this->assertStringNotContainsString(__('admin.side_group_platform'), $asEditor);
    }

    /**
     * Grouped, with the group labels in tracked caps. The second sanctioned
     * carve-out from CLAUDE.md's sentence-case rule, and it is written into
     * CLAUDE.md rather than only here.
     */
    public function test_the_sidebar_is_grouped(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->getContent();

        foreach (['overview', 'content', 'regulatory', 'people', 'platform'] as $group) {
            $this->assertStringContainsString(__('admin.side_group_'.$group), $html);
        }

        $this->assertStringContainsString('a-side__grouplabel', $html);

        $css = file_get_contents(public_path('css/admin.css'));
        preg_match('/\.a-side__grouplabel \{([^}]*)\}/', $css, $rule);

        $this->assertNotEmpty($rule);
        $this->assertStringContainsString('text-transform: uppercase', $rule[1]);
        $this->assertStringContainsString('letter-spacing: .1em', $rule[1]);

        // Held in CLAUDE.md too. A carve-out that loses its written scope
        // reads as permission to set anything in caps.
        $claude = file_get_contents(base_path('CLAUDE.md'));
        $this->assertStringContainsString('Two carve-outs for caps', $claude);
        $this->assertStringContainsString("administered sidebar's group labels", $claude);
    }

    public function test_a_panel_with_no_data_source_says_so_instead_of_drawing_a_chart(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->getContent();

        // There is no analytics collection and no submission workflow, so
        // all three of these must be visible gaps.
        $this->assertStringContainsString(__('admin.visitors_empty_title'), $html);
        $this->assertStringContainsString(__('admin.popular_empty_title'), $html);
        $this->assertStringContainsString(__('admin.submissions_empty_title'), $html);
    }

    public function test_every_stat_card_is_counted_from_the_database(): void
    {
        $this->seed(PublicationSeeder::class);

        $html = $this->actingAs($this->administrator)->get('/en/admin')->getContent();

        /*
         * The four tiles the shell draws: publications, news, member states
         * with data, users. Each figure is a link to what it counts.
         */
        $publications = Publication::count();

        $this->assertGreaterThan(0, $publications);
        $this->assertStringContainsString('>'.number_format($publications).'<', $html);
        $this->assertStringContainsString('>'.number_format(\App\Models\NewsItem::count()).'<', $html);
        $this->assertStringContainsString('>'.number_format(\App\Models\User::count()).'<', $html);

        // Committees and member states are counted on the charts and the
        // resource table below, which screen 09 draws and the shell kept.
        $this->assertGreaterThan(0, Committee::count());
        $this->assertSame(14, Country::count());
    }

    public function test_the_year_chart_states_every_count_so_it_reads_without_colour(): void
    {
        $this->seed(PublicationSeeder::class);

        $html = $this->actingAs($this->administrator)->get('/en/admin')->getContent();

        // The bars carry their figure, and the same figures are available as
        // a table. A chart is never the only way to read a number here.
        $this->assertStringContainsString('a-bars__n', $html);
        $this->assertStringContainsString(__('admin.as_table'), $html);
    }

    public function test_the_activity_feed_reads_the_audit_log_and_names_who(): void
    {
        AuditLog::create([
            'user_id' => $this->administrator->id,
            'action' => AuditLog::LOGIN,
            'created_at' => now(),
        ]);

        $this->actingAs($this->administrator)
            ->get('/en/admin')
            ->assertOk()
            ->assertSee(__('audit.action_login'))
            ->assertSee($this->administrator->name);
    }

    public function test_the_resource_table_renders_publications_with_their_languages(): void
    {
        $this->seed(PublicationSeeder::class);

        $html = $this->actingAs($this->administrator)->get('/en/admin')->getContent();

        $this->assertStringContainsString('a-resource', $html);
        $this->assertStringContainsString(__('admin.col_languages'), $html);
        $this->assertStringContainsString('a-pill', $html);
    }

    public function test_the_theme_is_an_account_setting_that_defaults_to_light(): void
    {
        // F11 (29 October 2026): the back office opens light for the
        // workshop; "system" (follow the device) stays one click away.
        $this->assertSame(Theme::Light, $this->administrator->theme);

        $this->actingAs($this->administrator)
            ->get('/en/admin')
            ->assertSee('data-theme="light"', false);

        $this->actingAs($this->administrator)
            ->from('/en/admin')
            ->put('/en/account/theme', ['theme' => 'dark'])
            ->assertRedirect('/en/admin');

        $this->assertSame(Theme::Dark, $this->administrator->fresh()->theme);

        $this->actingAs($this->administrator)
            ->get('/en/admin')
            ->assertSee('data-theme="dark"', false);
    }

    public function test_the_theme_control_refuses_a_value_that_is_not_a_theme(): void
    {
        $this->actingAs($this->administrator)
            ->from('/en/admin')
            ->put('/en/account/theme', ['theme' => 'midnight'])
            ->assertSessionHasErrors('theme');

        $this->assertSame(Theme::Light, $this->administrator->fresh()->theme);
    }
}
