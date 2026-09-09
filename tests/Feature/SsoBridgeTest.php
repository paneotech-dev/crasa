<?php

namespace Tests\Feature;

use App\Auth\IdentityProvider;
use App\Auth\PlatformIdentityProvider;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

/**
 * The SSO handoff and its error states, IN-01.
 *
 * Form 3 has never come back from CRASA, so there is no identity source and
 * no test credentials. IN-01 needs a deliverable path regardless, so the
 * handoff and every one of its failure paths are built and tested against a
 * stand-in provider. The failure paths are the part that is hard to add
 * later, and they are the part a regulator actually meets.
 */
class SsoBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
    }

    /** A provider that asserts whatever the test wants it to. */
    private function provider(bool $configured, ?User $user): void
    {
        $this->app->bind(IdentityProvider::class, fn () => new class($configured, $user) implements IdentityProvider
        {
            public function __construct(private bool $configured, private ?User $user) {}

            public function name(): string
            {
                return 'Test provider';
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function redirect(Request $request): RedirectResponse
            {
                return new RedirectResponse('https://idp.example/authorize');
            }

            public function user(Request $request): ?User
            {
                return $this->user;
            }

            public function logoutUrl(): ?string
            {
                return null;
            }
        });
    }

    /* The fallback ------------------------------------------------------------ */

    public function test_the_platform_ships_with_its_own_authentication(): void
    {
        // Not a stub. Accounts, passwords, two-factor and the audit trail
        // all live here, and if Form 3 never arrives this is what ships.
        $provider = app(IdentityProvider::class);

        $this->assertInstanceOf(PlatformIdentityProvider::class, $provider);
        $this->assertFalse($provider->isConfigured());
    }

    public function test_the_password_form_still_works_and_is_the_way_in(): void
    {
        $user = User::factory()->editor()->create(['password' => bcrypt('correct-horse-battery')]);

        $this->post('/en/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    /* The four failure paths --------------------------------------------------- */

    public function test_an_unconfigured_provider_sends_the_visitor_to_the_password_form(): void
    {
        // Not an error the visitor caused, so not an error message. A
        // button that leads to a provider error is worse than no button.
        $this->get('/en/sso/redirect')
            ->assertRedirectContains('/login')
            ->assertSessionHas('status', __('auth.sso_unavailable'));
    }

    public function test_a_callback_with_no_provider_configured_is_refused_by_name(): void
    {
        $this->get('/en/sso/callback')
            ->assertRedirectContains('/login')
            ->assertSessionHasErrors(['email' => __('auth.sso_unavailable')]);

        $this->assertGuest();
    }

    public function test_an_assertion_that_identifies_nobody_is_refused_and_logged(): void
    {
        $this->provider(configured: true, user: null);

        $this->get('/en/sso/callback')
            ->assertRedirectContains('/login')
            ->assertSessionHasErrors(['email' => __('auth.sso_no_account')]);

        $this->assertGuest();

        // The Secretariat needs to know which of the four it was without
        // reading a server log.
        $this->assertDatabaseHas('audit_log', ['action' => AuditLog::LOGIN_FAILED]);
    }

    /**
     * The one with a security consequence. Federated identity does not
     * create accounts here: otherwise anyone the identity provider trusts
     * becomes a user of a platform holding country-scoped data and
     * confidential committee papers, without anyone at the Secretariat
     * deciding so.
     */
    public function test_a_valid_identity_with_no_account_here_is_refused(): void
    {
        $this->provider(configured: true, user: null);

        $before = User::count();

        $this->get('/en/sso/callback')->assertSessionHasErrors('email');

        $this->assertSame($before, User::count());
        $this->assertGuest();
    }

    public function test_a_suspended_account_is_refused_by_name(): void
    {
        $user = User::factory()->suspended()->create();
        $this->provider(configured: true, user: $user);

        $this->get('/en/sso/callback')
            ->assertRedirectContains('/login')
            ->assertSessionHasErrors(['email' => __('auth.sso_suspended')]);

        $this->assertGuest();

        $entry = AuditLog::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('suspended', $entry->changes['reason']);
    }

    /* The happy path ----------------------------------------------------------- */

    public function test_a_recognised_account_is_signed_in_and_recorded(): void
    {
        $user = User::factory()->editor()->create();
        $this->provider(configured: true, user: $user);

        $this->get('/en/sso/callback')->assertRedirect();

        $this->assertAuthenticatedAs($user);

        $entry = AuditLog::where('user_id', $user->id)->where('action', AuditLog::LOGIN)->sole();
        $this->assertSame('Test provider', $entry->changes['provider']);
    }

    public function test_a_configured_provider_hands_the_browser_over(): void
    {
        $this->provider(configured: true, user: null);

        $this->get('/en/sso/redirect')->assertRedirect('https://idp.example/authorize');
    }

    /**
     * CLAUDE.md makes two-factor mandatory for administrators without
     * exception for how they arrived. A federated sign-in is a claim about
     * who someone is; it is not a second factor.
     */
    public function test_two_factor_still_applies_to_an_administrator_arriving_through_the_bridge(): void
    {
        $administrator = User::factory()->administrator()->withoutTwoFactor()->create();
        $this->provider(configured: true, user: $administrator);

        $this->get('/en/sso/callback')->assertRedirect();

        // Signed in, and still held at enrolment by the same middleware
        // that holds a password sign-in.
        $this->assertAuthenticatedAs($administrator);
        $this->get('/en/admin')->assertRedirectContains('two-factor');
    }

    /* The seam ----------------------------------------------------------------- */

    public function test_swapping_the_provider_changes_one_binding_and_no_application_code(): void
    {
        $reflection = new \ReflectionClass(IdentityProvider::class);

        // Three things an identity provider has to do. Everything else
        // about SAML and OAuth stays inside an implementation.
        $this->assertSame(
            ['name', 'isConfigured', 'redirect', 'user', 'logoutUrl'],
            array_map(fn ($m) => $m->getName(), $reflection->getMethods())
        );
    }

    public function test_the_requirements_document_exists_and_asks_for_what_is_needed(): void
    {
        $path = base_path('docs/SSO_REQUIREMENTS.md');

        $this->assertFileExists($path);

        $document = file_get_contents($path);

        // The seven things a bridge cannot be built without.
        foreach ([
            'identity source', 'SAML 2.0', 'OpenID Connect', 'stable identifier',
            'create accounts', 'test account', 'technical contact',
        ] as $required) {
            $this->assertStringContainsStringIgnoringCase($required, $document);
        }

        // And the callback URL CRASA has to allow-list.
        $this->assertStringContainsString('/sso/callback', $document);
    }
}
