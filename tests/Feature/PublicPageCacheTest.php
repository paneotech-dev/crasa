<?php

namespace Tests\Feature;

use App\Http\Middleware\PublicPageCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * Cache-Control agrees with the Cloudflare rule: an anonymous public page
 * may be kept for ten minutes and carries no cookie; anything with a
 * session, a form or a signed-in reader is private.
 */
class PublicPageCacheTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();
    }

    private function cacheControl($response): string
    {
        $directives = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
        sort($directives);

        return implode(', ', $directives);
    }

    public function test_an_anonymous_public_page_is_public_for_ten_minutes(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $response = $this->get("/{$locale}")->assertOk();

            $this->assertSame('max-age=0, public, s-maxage=600', $this->cacheControl($response), $locale);
        }
    }

    public function test_a_cacheable_page_sets_no_cookie(): void
    {
        $response = $this->get('/en')->assertOk();

        // A cached response that set a session cookie would give the next
        // visitor this visitor's session.
        $this->assertSame([], $response->headers->getCookies());
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    public function test_a_visitor_with_a_session_cookie_gets_a_private_page(): void
    {
        $response = $this->withCookie(config('session.cookie'), 'anything')->get('/en')->assertOk();

        $this->assertSame('no-store, private', $this->cacheControl($response));
        $this->assertNotEmpty($response->headers->getCookies());
    }

    public function test_a_visitor_with_an_xsrf_cookie_gets_a_private_page(): void
    {
        $response = $this->withCookie('XSRF-TOKEN', 'anything')->get('/en')->assertOk();

        $this->assertSame('no-store, private', $this->cacheControl($response));
    }

    public function test_a_page_with_a_csrf_form_stays_private_and_keeps_its_session(): void
    {
        foreach (['/en/contact', '/en/login'] as $path) {
            $response = $this->get($path)->assertOk();

            $this->assertStringContainsString('name="_token"', $response->getContent(), $path);
            $this->assertSame('no-store, private', $this->cacheControl($response), $path);
            // The token on the form must be backed by a session cookie, or
            // the form fails with 419.
            $this->assertNotEmpty($response->headers->getCookies(), $path);
        }
    }

    public function test_a_signed_in_reader_gets_a_private_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/en')->assertOk();

        $this->assertSame('no-store, private', $this->cacheControl($response));
    }

    public function test_a_redirect_or_a_missing_page_is_private(): void
    {
        $this->assertSame('no-store, private', $this->cacheControl($this->get('/')));
        $this->assertSame('no-store, private', $this->cacheControl($this->get('/en/no-such-page-anywhere')));
    }

    public function test_a_post_is_never_public(): void
    {
        $response = $this->post('/en/contact', []);

        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_header_values_are_the_documented_ones(): void
    {
        $this->assertSame('public, max-age=0, s-maxage=600', PublicPageCache::PUBLIC);
        $this->assertSame('private, no-store', PublicPageCache::PRIVATE);
    }
}
