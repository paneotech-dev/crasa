<?php

namespace Tests\Feature;

use App\Enums\CommitteeRole;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Nothing a role is shown leads it to a refusal.
 *
 * On 14 October 2026, in front of the regulators, the member account typed
 * into the search box at the top of the extranet and got "403 Forbidden"
 * twice: the box sent every role to the administration search. The crawls
 * of the night before followed links and never submitted a form, and the
 * tests checked that the administration search refuses a member, which it
 * correctly does, but not that the member was never offered it.
 *
 * So this walks, for every role, every page that role can reach from where
 * it lands, and checks everything on it that leads somewhere: each link,
 * each GET form (submitted, with a word in every text field) and each
 * other form (its address must answer that method). A link or a form that
 * ends in 403, 404, 405 or a server error fails the test, with the page it
 * was found on.
 */
class RoleReachabilityTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_PAGES = 700;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        config(['crasa.two_factor_required' => false]);
    }

    /** @return array<string, array{0: string}> */
    public static function roles(): array
    {
        return ['administrator' => ['administrator'], 'editor' => ['editor'], 'member_state_rep' => ['member_state_rep'], 'committee_member' => ['committee_member'], 'viewer' => ['viewer']];
    }

    /** @dataProvider roles */
    #[DataProvider('roles')]
    public function test_nothing_shown_to_a_role_leads_to_a_refusal(string $role): void
    {
        $user = $this->userFor($role);
        $broken = [];
        $queue = [['/en', 'start'], ['/en/account', 'start'], ['/en/extranet', 'start'], ['/en/admin', 'start']];
        $seen = [];
        $checked = [];

        while ($queue !== [] && count($seen) < self::MAX_PAGES) {
            [$path, $from] = array_shift($queue);
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            $response = $this->actingAs($user)->get($path);
            $status = $response->getStatusCode();

            if ($status >= 300 && $status < 400) {
                $to = $this->localPath((string) $response->headers->get('Location'));
                if ($to !== null && ! isset($seen[$to])) {
                    $queue[] = [$to, $path];
                }

                continue;
            }

            if ($from !== 'start' && ($status >= 400)) {
                $broken[] = "{$status} {$path} (linked from {$from})";

                continue;
            }

            $type = (string) $response->headers->get('Content-Type');
            if ($status !== 200 || ! str_contains($type, 'text/html')) {
                continue;
            }

            $html = (string) $response->getContent();

            foreach ($this->links($html) as $href) {
                if (! isset($seen[$href])) {
                    $queue[] = [$href, $path];
                }
            }

            foreach ($this->forms($html) as [$method, $action, $fields]) {
                $key = $method.' '.$action;
                if (isset($checked[$key])) {
                    continue;
                }
                $checked[$key] = true;

                if ($method === 'GET') {
                    $query = $fields === [] ? '' : '?'.http_build_query(array_fill_keys($fields, 'botswana'));
                    $code = $this->actingAs($user)->get($action.$query)->getStatusCode();
                    if ($code === 403 || $code === 404 || $code >= 500) {
                        $broken[] = "{$code} GET form {$action}{$query} (on {$path})";
                    }

                    continue;
                }

                if (! $this->routeAnswers($method, $action)) {
                    $broken[] = "no route: {$method} form {$action} (on {$path})";
                }
            }
        }

        $this->assertGreaterThan(5, count($seen), "The {$role} walk reached almost nothing.");
        $this->assertSame([], array_values(array_unique($broken)), "Shown to {$role} but refused or missing:\n".implode("\n", array_unique($broken)));
    }

    private function userFor(string $role): User
    {
        $botswana = Country::where('iso_code', 'bw')->sole();

        $user = match ($role) {
            'administrator' => User::factory()->administrator()->create(),
            'editor' => User::factory()->editor()->create(),
            'member_state_rep' => User::factory()->memberStateRep($botswana)->create(),
            'committee_member' => User::factory()->committeeMember($botswana)->create(),
            'viewer' => User::factory()->viewer()->create(),
        };

        if ($role === 'committee_member' && ($committee = Committee::first()) !== null) {
            $user->committees()->attach($committee->id, ['role' => CommitteeRole::Member->value, 'is_sample' => true]);
        }

        return $user;
    }

    /** Same-site paths in this language, without fragments; downloads and sign-out left alone. */
    private function links(string $html): array
    {
        preg_match_all('/<a\b[^>]*\bhref="([^"]+)"/i', $html, $m);
        $out = [];
        foreach ($m[1] as $href) {
            $path = $this->localPath(html_entity_decode($href));
            if ($path === null || ! str_starts_with($path, '/en') || preg_match('#/logout|/sso/#', $path)) {
                continue;
            }
            $out[] = $path;
        }

        return array_unique($out);
    }

    /** @return list<array{0: string, 1: string, 2: list<string>}> */
    private function forms(string $html): array
    {
        preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [$all, $attrs, $body]) {
            preg_match('/\baction="([^"]*)"/i', $attrs, $a);
            preg_match('/\bmethod="([^"]*)"/i', $attrs, $mm);
            $action = $this->localPath(html_entity_decode($a[1] ?? ''));
            if ($action === null || preg_match('#/logout#', $action)) {
                continue;
            }
            $method = strtoupper($mm[1] ?? 'GET');
            if ($method === 'POST' && preg_match('/name="_method"\s+value="([A-Z]+)"/i', $body, $spoof)) {
                $method = strtoupper($spoof[1]);
            }
            preg_match_all('/<input\b[^>]*type="(?:search|text)"[^>]*name="([^"]+)"/i', $body, $f);
            $out[] = [$method, $action, array_values(array_unique($f[1]))];
        }

        return $out;
    }

    private function localPath(string $url): ?string
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, 'javascript:')) {
            return null;
        }
        $parts = parse_url($url);
        if (isset($parts['host']) && $parts['host'] !== parse_url(config('app.url'), PHP_URL_HOST) && $parts['host'] !== 'localhost') {
            return null;
        }
        $path = $parts['path'] ?? '/';
        if (preg_match('#\.(css|js|svg|png|jpe?g|webp|gif|ico|woff2?|pdf|ics|zip|csv|xlsx|docx?)$#i', $path)) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function routeAnswers(string $method, string $path): bool
    {
        try {
            Route::getRoutes()->match(Request::create($path, $method));

            return true;
        } catch (HttpException) {
            return false;
        }
    }
}
