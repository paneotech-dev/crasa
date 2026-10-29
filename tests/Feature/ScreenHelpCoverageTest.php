<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ScreenHelp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every back-office and member-area screen has its own help (client brief,
 * item 7), in English, French and Portuguese, in one of the
 * lang/{locale}/screenhelp_*.php files. A screen is a named GET route behind
 * sign-in; the allow-list holds only routes that are not screens.
 */
class ScreenHelpCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** Downloads, exports and redirects: not screens, so no help page. */
    private const NOT_SCREENS = [
        'admin.delegates.csv',
        'admin.reports.pdf',
        'extranet.calendar.ics',
        'submissions.template',
        'submissions.export.csv',
        'submissions.export.json',
        'notifications.open',
        'admin.registrations.csv',
        'admin.registrations.xlsx',
        'extranet.version',
        'extranet.shared',
    ];

    /** @return list<string> */
    private function screens(): array
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! in_array('auth', $route->gatherMiddleware(), true) || in_array($name, self::NOT_SCREENS, true)) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return array_values(array_unique($names));
    }

    public function test_every_back_office_and_member_area_screen_has_help_in_three_languages(): void
    {
        $screens = $this->screens();
        $this->assertContains('admin.dashboard', $screens);
        $this->assertContains('extranet.dashboard', $screens);

        $missing = [];

        foreach (['en', 'fr', 'pt'] as $locale) {
            ScreenHelp::forget();

            foreach ($screens as $screen) {
                $entry = ScreenHelp::entries($locale)[$screen] ?? null;

                if ($entry === null || ! filled($entry['purpose'] ?? null) || count($entry['actions'] ?? []) !== 3) {
                    $missing[] = "{$locale}: {$screen}";
                }
            }
        }

        $this->assertSame([], $missing, 'These screens have no help entry (purpose and three actions) in lang/{locale}/screenhelp_*.php.');
    }

    public function test_the_allow_list_holds_only_routes_that_exist(): void
    {
        foreach (self::NOT_SCREENS as $name) {
            $this->assertTrue(Route::has($name), "{$name} is on the allow-list but is not a route.");
        }
    }

    public function test_a_screen_without_an_entry_shows_its_sections_general_help(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $admin = ScreenHelp::for('admin.something-new', $locale);
            $member = ScreenHelp::for('extranet.something-new', $locale);

            $this->assertTrue($admin['general']);
            $this->assertSame(trans('screenhelp_core', [], $locale)['_back_office']['purpose'], $admin['purpose']);
            $this->assertCount(3, $admin['actions']);
            $this->assertSame(trans('screenhelp_core', [], $locale)['_member']['purpose'], $member['purpose']);
        }

        $own = ScreenHelp::for('notifications', 'en');
        $this->assertFalse($own['general']);
    }

    public function test_the_fallback_page_says_it_is_the_sections_help(): void
    {
        // A real route with no entry of its own: a download, served by the
        // help page as the member area's general help.
        $user = User::factory()->editor()->create();

        $this->actingAs($user)->get('/en/help/submissions.template')
            ->assertOk()
            ->assertSee(__('d3_notify.help_general_note'))
            ->assertSee(trans('screenhelp_core')['_member']['purpose']);
    }

    /**
     * Help for a screen is open to anyone who may open the screen. A member
     * state representative builds reports under /admin/reports, so reads its
     * help; the rest of the back office's help stays refused to them.
     */
    public function test_help_for_a_shared_back_office_screen_follows_the_screen(): void
    {
        $rep = User::factory()->memberStateRep(\App\Models\Country::first() ?? \App\Models\Country::factory()->create())->create();
        $committee = User::factory()->committeeMember()->create();

        $this->actingAs($rep)->get('/en/admin/reports')->assertOk();
        $this->actingAs($rep)->get('/en/help/admin.reports')->assertOk();
        $this->actingAs($rep)->get('/en/help/admin.dashboard')->assertForbidden();

        $this->actingAs($committee)->get('/en/help/admin.reports')->assertForbidden();
        $this->actingAs($committee)->get('/en/help/extranet.dashboard')->assertOk();
    }

    public function test_help_files_from_other_parts_of_the_platform_are_read(): void
    {
        $this->assertContains('screenhelp_core', ScreenHelp::files());

        // A file another part of the platform adds is read with no change here.
        $path = lang_path('en/screenhelp_zztest.php');
        file_put_contents($path, "<?php\n\nreturn ['admin.zztest' => ['purpose' => 'A screen added elsewhere.', 'actions' => ['One', 'Two', 'Three']]];\n");

        try {
            ScreenHelp::forget();
            app('translator')->setLoaded([]);
            $this->assertContains('screenhelp_zztest', ScreenHelp::files());
            $this->assertSame('A screen added elsewhere.', ScreenHelp::for('admin.zztest', 'en')['purpose']);
            $this->assertFalse(ScreenHelp::for('admin.zztest', 'en')['general']);
        } finally {
            @unlink($path);
            ScreenHelp::forget();
        }
    }
}
