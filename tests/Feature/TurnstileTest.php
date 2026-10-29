<?php

namespace Tests\Feature;

use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\Turnstile;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Cloudflare Turnstile on the public forms, behind a configuration flag.
 *
 * Off (either key missing), the forms behave exactly as before: no widget,
 * no script, no request to Cloudflare. On, every public POST form carries
 * the widget and the server verifies the token before the controller runs.
 */
class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        Mail::fake();
        RateLimiter::clear('enquiries');
        RateLimiter::clear('login:');
    }

    private function enable(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key-for-tests',
            'services.turnstile.secret' => 'secret-for-tests',
        ]);
    }

    /** @return array<string, string> */
    private function enquiry(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Thandiwe Moyo',
            'email' => 'thandiwe@example.test',
            'subject' => 'Roaming guidelines',
            'message' => 'We would like clarification on the roaming guidelines before the next committee meeting.',
            'website' => '',
        ], $overrides);
    }

    /* Off ---------------------------------------------------------------------- */

    public function test_it_is_off_unless_both_keys_are_set(): void
    {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret' => null]);
        $this->assertFalse(Turnstile::enabled());

        config(['services.turnstile.site_key' => 'site-key-for-tests']);
        $this->assertFalse(Turnstile::enabled());

        config(['services.turnstile.secret' => 'secret-for-tests']);
        $this->assertTrue(Turnstile::enabled());
    }

    public function test_off_the_forms_carry_no_widget_and_no_script(): void
    {
        foreach (['/en/contact', '/en/login', '/en/password/forgotten'] as $path) {
            $this->get($path)->assertOk()
                ->assertDontSee('cf-turnstile', false)
                ->assertDontSee('challenges.cloudflare.com', false);
        }
    }

    public function test_off_a_form_posts_as_before_and_cloudflare_is_never_asked(): void
    {
        Http::fake();

        $this->post('/en/contact', $this->enquiry())->assertSessionHasNoErrors();

        $this->assertSame(1, Enquiry::count());
        Mail::assertQueued(EnquiryReceived::class);
        Http::assertNothingSent();
    }

    public function test_off_the_policy_does_not_name_cloudflare_challenges(): void
    {
        $policy = $this->get('/en/contact')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('challenges.cloudflare.com', $policy);
        $this->assertStringNotContainsString('frame-src', $policy);
    }

    public function test_off_the_honeypot_still_rejects(): void
    {
        $this->post('/en/contact', $this->enquiry(['website' => 'http://spam.test']))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, Enquiry::count());
    }

    /* On ----------------------------------------------------------------------- */

    public function test_on_every_public_form_carries_the_widget_and_the_script(): void
    {
        $this->enable();

        foreach (['/en/contact', '/en/login', '/en/password/forgotten', '/fr/password/reset/some-token'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('class="cf-turnstile" data-sitekey="site-key-for-tests"', $html, $path);
            $this->assertSame(1, substr_count($html, Turnstile::SCRIPT), $path);
        }
    }

    public function test_on_pages_without_a_form_do_not_load_the_script(): void
    {
        $this->enable();

        $this->get('/en/search')->assertOk()->assertDontSee(Turnstile::SCRIPT, false);
    }

    public function test_on_the_policy_allows_the_challenge_script_and_frame(): void
    {
        $this->enable();

        $policy = $this->get('/en/contact')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("#script-src [^;]*https://challenges\.cloudflare\.com#", $policy);
        $this->assertStringContainsString("frame-src 'self' https://challenges.cloudflare.com", $policy);
    }

    public function test_on_a_confirmed_token_lets_the_enquiry_through(): void
    {
        $this->enable();
        Http::fake([Turnstile::VERIFY => Http::response(['success' => true])]);

        $this->post('/en/contact', $this->enquiry([Turnstile::FIELD => 'token-ok']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Enquiry::count());
        Http::assertSent(fn (Request $request) => $request->url() === Turnstile::VERIFY
            && $request['secret'] === 'secret-for-tests'
            && $request['response'] === 'token-ok');
    }

    public function test_on_a_refused_token_sends_the_reader_back_with_a_message_in_their_language(): void
    {
        $this->enable();
        Http::fake([Turnstile::VERIFY => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $this->from('/fr/contact')
            ->post('/fr/contact', $this->enquiry([Turnstile::FIELD => 'token-bad']))
            ->assertRedirect('/fr/contact')
            ->assertSessionHasErrors([Turnstile::FIELD => trans('captcha.failed', [], 'fr')]);

        $this->assertSame(0, Enquiry::count());
        Mail::assertNothingQueued();
    }

    public function test_on_a_missing_token_fails_without_asking_cloudflare(): void
    {
        $this->enable();
        Http::fake();

        $this->post('/en/contact', $this->enquiry())->assertSessionHasErrors(Turnstile::FIELD);

        Http::assertNothingSent();
        $this->assertSame(0, Enquiry::count());
    }

    public function test_on_a_refused_token_never_reaches_sign_in(): void
    {
        $this->enable();
        Http::fake([Turnstile::VERIFY => Http::response(['success' => false])]);
        $user = User::factory()->editor()->create(['password' => Hash::make('a-long-test-password')]);

        $this->post('/en/login', ['email' => $user->email, 'password' => 'a-long-test-password', Turnstile::FIELD => 'token-bad'])
            ->assertSessionHasErrors(Turnstile::FIELD);

        $this->assertGuest();
    }

    public function test_on_a_confirmed_token_signs_in(): void
    {
        $this->enable();
        Http::fake([Turnstile::VERIFY => Http::response(['success' => true])]);
        $user = User::factory()->editor()->create(['password' => Hash::make('a-long-test-password')]);

        $this->post('/en/login', ['email' => $user->email, 'password' => 'a-long-test-password', Turnstile::FIELD => 'token-ok']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_on_cloudflare_unreachable_the_form_falls_back_to_its_other_checks(): void
    {
        $this->enable();
        Http::fake([Turnstile::VERIFY => fn () => throw new ConnectionException('timed out')]);

        $this->post('/en/contact', $this->enquiry([Turnstile::FIELD => 'token']))->assertSessionHasNoErrors();

        $this->assertSame(1, Enquiry::count());
    }

    public function test_every_public_form_route_is_guarded_and_every_form_draws_the_widget(): void
    {
        $forms = [
            'contact.store' => 'pages/contact',
            'events.register.store' => 'pages/events/register',
            'login.store' => 'pages/auth/login',
            'password.email' => 'pages/auth/password-request',
            'password.update' => 'pages/auth/password-reset',
        ];

        foreach ($forms as $route => $view) {
            $this->assertContains(
                \App\Http\Middleware\VerifyTurnstile::class,
                app('router')->getRoutes()->getByName($route)->gatherMiddleware(),
                $route
            );
            $this->assertSame(1, substr_count(
                file_get_contents(resource_path("views/{$view}.blade.php")),
                "@include('partials.turnstile')"
            ), $view);
        }
    }

    public function test_the_three_languages_carry_the_same_keys(): void
    {
        $en = array_keys(require lang_path('en/captcha.php'));

        foreach (['fr', 'pt'] as $locale) {
            $this->assertSame($en, array_keys(require lang_path($locale.'/captcha.php')));
        }
    }
}
