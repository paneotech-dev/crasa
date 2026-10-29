<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every signed-in screen that draws the shell draws its navigation.
 *
 * Found in the room on 29 October: a member state representative on
 * /en/submissions saw the area switcher and nothing else, because the page
 * used the back-office layout and the back-office menu, filtered by a
 * member's permissions, is empty. This walks every parameterless GET route
 * behind sign-in as each of the seven roles and checks the sidebar on every
 * page that renders it: its groups, and the item that is current.
 */
class SidebarEveryScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
    }

    public static function roles(): array
    {
        return [
            'administrator' => ['administrator'], 'moderator' => ['moderator'], 'editor' => ['editor'],
            'auditor' => ['auditor'], 'member' => ['memberStateRep'], 'committee' => ['committeeMember'], 'viewer' => ['viewer'],
        ];
    }

    #[DataProvider('roles')]
    public function test_every_shell_screen_has_its_menu_groups_and_a_current_item(string $state): void
    {
        $country = Country::where('iso_code', 'bw')->first();
        $factory = User::factory()->withoutTwoFactor();
        $user = in_array($state, ['memberStateRep', 'committeeMember'], true)
            ? $factory->{$state}($country)->create()
            : $factory->{$state}()->create();

        $checked = 0;
        $problems = [];
        foreach ($this->screens() as $uri) {
            $response = $this->actingAs($user)->get($uri);
            if ($response->getStatusCode() !== 200) {
                continue;
            }
            $html = $response->getContent();
            if (! str_contains($html, 'class="a-side"')) {
                continue;
            }
            $checked++;
            if (substr_count($html, 'a-side__grouplabel') === 0) {
                $problems[] = $uri.': no menu groups';
            } elseif (! preg_match('/a-side__link[^"]*is-current|aria-current="page"/', $html)) {
                $problems[] = $uri.': no current item';
            }
        }

        $this->assertGreaterThan(0, $checked, 'No shell screen was reached as '.$state);
        $this->assertSame([], $problems, 'Sidebar problems as '.$state);
    }

    /** @return list<string> */
    private function screens(): array
    {
        $uris = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            if (! in_array('auth', $middleware, true)) {
                continue;
            }
            $uri = $route->uri();
            if (! str_starts_with($uri, '{locale}/')) {
                continue;
            }
            $rest = substr($uri, strlen('{locale}/'));
            if (str_contains($rest, '{') || preg_match('/(export|download|\.csv|\.ics|logout|preview)/', $rest)) {
                continue;
            }
            $uris[] = '/en/'.$rest;
        }

        return array_values(array_unique($uris));
    }
}
