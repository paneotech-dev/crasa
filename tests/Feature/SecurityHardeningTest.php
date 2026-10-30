<?php

namespace Tests\Feature;

use App\Console\Commands\HardenUploads;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Hardening, and the findings that produced it.
 *
 * This host had two web shells dropped on a sibling account in September
 * 2026, so none of this is routine. Each test below corresponds to
 * something that was actually wrong when it was written, not to a
 * precaution somebody imagined.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    /* Finding 1: the uploads directory executed PHP ------------------------- */

    /**
     * Confirmed by planting a file and requesting it over HTTPS, which
     * returned "EXECUTED:8.3.33". That is a web shell with one bad MIME
     * check between it and an attacker.
     */
    public function test_the_uploads_directory_refuses_to_execute_anything(): void
    {
        $rules = File::get(resource_path(HardenUploads::SOURCE));

        // Three layers, because cPanel may run PHP through any of several
        // handlers and only one of these works for each.
        $this->assertStringContainsString('php_flag engine off', $rules);
        $this->assertStringContainsString('RemoveHandler', $rules);
        $this->assertStringContainsString('Require all denied', $rules);

        // And the extensions that matter, including the ones people forget.
        foreach (['phtml', 'phar', 'phps', 'cgi', 'shtml'] as $extension) {
            $this->assertStringContainsString($extension, $rules);
        }

        $this->assertStringContainsString('Options -Indexes', $rules);

        // Images only, and nothing else served at all (as nginx does on the
        // ai host): a non-image name is a 404, the review folder refused.
        $this->assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !\\.(?:jpe?g|png|webp|gif|avif|svg)$ [NC]', $rules);
        $this->assertStringContainsString('RewriteRule ^ - [R=404,L]', $rules);
        $this->assertStringContainsString('_review-needed/ - [F,L]', $rules);
        $this->assertStringContainsString("sandbox", $rules);
    }

    /* Moving hosts: the database clock and logged mail (19 October 2026) ---- */

    public function test_the_database_connection_is_pinned_to_utc(): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            $this->assertSame('+00:00', config("database.connections.{$connection}.timezone"));
        }
    }

    public function test_logged_mail_is_recorded_whatever_the_log_level(): void
    {
        $this->assertSame('mail', config('mail.mailers.log.channel'));
        $this->assertSame('debug', config('logging.channels.mail.level'));
        $this->assertStringEndsWith('logs/mail.log', config('logging.channels.mail.path'));
    }

    /**
     * The rules live in a directory the repository excludes, so a
     * deployment to a fresh directory would arrive without them. The
     * command exists so that cannot happen quietly.
     */
    public function test_the_protection_survives_a_deployment(): void
    {
        $this->assertFileExists(resource_path(HardenUploads::SOURCE));

        $this->artisan('security:harden-uploads')->assertSuccessful();
        $this->artisan('security:harden-uploads --check')->assertSuccessful();

        $this->assertSame(
            File::get(resource_path(HardenUploads::SOURCE)),
            File::get(storage_path('app/public/.htaccess'))
        );
    }

    /* Finding 2: debug was on on a public host ------------------------------ */

    public function test_debug_is_never_on_outside_local_development(): void
    {
        // Laravel's debug page prints environment variables, including the
        // database password, to anyone who can cause an error.
        $this->assertFalse(
            config('app.debug') && config('app.env') === 'production',
            'APP_DEBUG is true in production.'
        );
    }

    /* Finding 3: no security headers at all --------------------------------- */

    public function test_every_response_carries_the_security_headers(): void
    {
        $response = $this->get('/en/login');

        foreach ([
            'Content-Security-Policy',
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ] as $header) {
            $this->assertNotNull($response->headers->get($header), $header.' is missing.');
        }

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    /**
     * The back office must not be framable. Framing an administration
     * interface and steering clicks through it is the attack this closes.
     */
    public function test_the_policy_forbids_framing_and_external_code(): void
    {
        $policy = $this->get('/en')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
    }

    /**
     * The contact map's tiles come from one third-party host, loaded only
     * when the reader asks for the map. The policy refused them, so the map
     * was a pin on a blank square. That host is allowed for images, nothing
     * else is, and it never reaches script-src or connect-src.
     */
    public function test_the_policy_allows_the_map_tile_host_for_images_only(): void
    {
        config(['crasa.map.tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png']);
        $policy = $this->get('/en')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("~img-src 'self' data: blob: https://tile\\.openstreetmap\\.org(;|$)~", $policy);
        $this->assertSame(1, substr_count($policy, 'openstreetmap'));
        // blob: is for the media upload preview and its early size check,
        // which draw the chosen file before it is sent; it is images only.
        $this->assertSame(1, substr_count($policy, 'blob:'));

        config(['crasa.map.tile_url' => 'javascript:alert(1)']);
        $this->assertStringContainsString("img-src 'self' data: blob:;", $this->get('/en')->headers->get('Content-Security-Policy'));
    }

    /**
     * The policy is a statement of what is already true. Every script,
     * style, font and image is served from this platform, which is the
     * platform's own stated position, so the policy costs nothing and a
     * script injected by any means will not run.
     */
    public function test_the_pages_do_not_load_anything_the_policy_would_block(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);

        foreach (['/en/members', '/en/comparator', '/en/contact'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            preg_match_all('~(?:src|href)="(https?://[^"]+)"~', $html, $matches);

            foreach ($matches[1] as $url) {
                // Anchors to social accounts are navigation, not resources.
                if (preg_match('~<a[^>]+href="'.preg_quote($url, '~').'"~', $html)) {
                    continue;
                }

                $this->assertStringStartsWith(
                    config('app.url'),
                    $url,
                    $path.' loads '.$url.' from another origin, which the policy blocks.'
                );
            }
        }
    }

    /* Finding 4: the session cookie was not marked secure -------------------- */

    public function test_the_session_cookie_is_secure_http_only_and_same_site(): void
    {
        // On a platform holding confidential committee papers, a session
        // cookie that travels over plain HTTP is a session anyone on the
        // path can take.
        $this->assertTrue(config('session.secure'), 'The session cookie is not marked secure.');
        $this->assertTrue(config('session.http_only'));
        // Lax, not strict (14 October 2026). With strict, a signed-in
        // reader who opened a link from Teams or an email arrived signed
        // out, because the browser withholds a strict cookie on any
        // navigation that starts on another site. Lax still withholds it
        // from a cross-site POST, which is what protects the forms, and
        // every form also carries a CSRF token.
        $this->assertSame('lax', config('session.same_site'));

        // Encrypted at rest as well: session payloads carry the user id and
        // whatever a controller flashed into them.
        $this->assertTrue(config('session.encrypt'));

        // And not named after the framework.
        $this->assertNotSame('laravel-session', config('session.cookie'));
    }

    /* Finding 5: two public write paths were unthrottled --------------------- */

    public function test_every_public_write_path_is_rate_limited(): void
    {
        $unprotected = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('POST', $route->methods(), true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            // A path behind authentication is protected by the account, not
            // by a limiter. These are the ones a stranger can reach.
            if (in_array('auth', $middleware, true)) {
                continue;
            }

            $throttled = collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'throttle'));

            if (! $throttled && $route->getName() !== 'logout') {
                $unprotected[] = $route->getName() ?? $route->uri();
            }
        }

        $this->assertSame([], $unprotected, 'Unthrottled public write paths: '.implode(', ', $unprotected));
    }

    public function test_the_authentication_paths_are_throttled_at_the_route_as_well(): void
    {
        // The controllers throttle per account. This is the layer above:
        // one address trying one password against many accounts never
        // reaches a per-account counter.
        foreach (['login.store', 'two-factor.challenge.store', 'password.email', 'password.update'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, $name.' does not exist.');
            $this->assertTrue(
                collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'throttle')),
                $name.' is not rate limited.'
            );
        }
    }

    /* Standing guarantees ---------------------------------------------------- */

    public function test_no_secret_is_committed(): void
    {
        $tracked = trim(shell_exec('cd '.escapeshellarg(base_path()).' && git ls-files 2>/dev/null') ?? '');

        foreach (explode("\n", $tracked) as $file) {
            $this->assertNotSame('.env', $file, '.env is tracked in git.');
            $this->assertStringNotContainsString('.env.production', $file);
            $this->assertStringNotContainsString('.sql', $file);
        }
    }

    public function test_documents_are_never_served_from_the_filesystem(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        // Every byte of a document leaves through the controller, which
        // checks clearance, country and committee first. A path under
        // public/ would bypass all three.
        $public = File::exists(public_path('storage'))
            ? (is_link(public_path('storage')) ? readlink(public_path('storage')) : public_path('storage'))
            : null;

        if ($public !== null) {
            $this->assertStringNotContainsString(
                'documents',
                implode(',', array_map('basename', File::directories($public))),
                'The documents directory is reachable from the web root.'
            );
        }
    }
}
