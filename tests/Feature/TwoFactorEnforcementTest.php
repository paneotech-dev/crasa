<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 2FA is mandatory for administrators and enforced at login, not offered in
 * settings. Two layers: the middleware redirects, and the permission matrix
 * withholds the privileged abilities regardless of how a request arrived.
 */
class TwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/t/anything', fn () => response('reached'))->name('t.anything');
    }

    public function test_an_administrator_without_confirmed_two_factor_is_redirected_to_enrolment(): void
    {
        $this->actingAs(User::factory()->administrator()->withoutTwoFactor()->create())
            ->get('/t/anything')
            ->assertRedirect(route('two-factor.enrol', ['locale' => 'en']));
    }

    public function test_an_enrolled_administrator_passes_through(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get('/t/anything')
            ->assertOk();
    }

    public function test_the_enrolment_route_itself_is_reachable_or_the_redirect_loops(): void
    {
        $this->actingAs(User::factory()->administrator()->withoutTwoFactor()->create())
            ->get(route('two-factor.enrol', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('Scan the code');
    }

    public function test_two_factor_is_not_forced_on_other_roles(): void
    {
        foreach (['editor', 'viewer'] as $state) {
            $this->actingAs(User::factory()->{$state}()->create())
                ->get('/t/anything')
                ->assertOk();
        }
    }

    /**
     * The middleware is the first layer. This is the second: a route added
     * later without the middleware still cannot publish or change a role.
     */
    public function test_an_unenrolled_administrator_loses_the_privileged_abilities(): void
    {
        $admin = User::factory()->administrator()->withoutTwoFactor()->create();

        $this->assertFalse($admin->hasPermission(Permission::PublishContent));
        $this->assertFalse($admin->hasPermission(Permission::ValidateSubmission));
        $this->assertFalse($admin->hasPermission(Permission::AssignRoles));
        $this->assertFalse($admin->hasPermission(Permission::ManageSettings));
    }

    public function test_an_unenrolled_administrator_keeps_read_access(): void
    {
        $admin = User::factory()->administrator()->withoutTwoFactor()->create();

        $this->assertTrue($admin->hasPermission(Permission::ViewPublishedContent));
        $this->assertTrue($admin->hasPermission(Permission::ViewPublishedIndicators));
        $this->assertTrue($admin->hasPermission(Permission::ReadAuditLog));
    }

    /**
     * The redirect must work from a route outside the locale prefix, where
     * no URL default has been registered. It uses the account's own
     * preferred_locale.
     */
    public function test_the_redirect_honours_the_accounts_preferred_locale(): void
    {
        $admin = User::factory()->administrator()->withoutTwoFactor()->create([
            'preferred_locale' => 'pt',
        ]);

        $this->actingAs($admin)
            ->get('/t/anything')
            ->assertRedirect(route('two-factor.enrol', ['locale' => 'pt']));
    }

    public function test_an_unsupported_preferred_locale_falls_back_to_english(): void
    {
        $admin = User::factory()->administrator()->withoutTwoFactor()->create();
        $admin->forceFill(['preferred_locale' => 'de'])->save();

        $this->actingAs($admin)
            ->get('/t/anything')
            ->assertRedirect(route('two-factor.enrol', ['locale' => 'en']));
    }

    /* Encryption at rest --------------------------------------------------- */

    public function test_the_two_factor_secret_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->administrator()->create();
        $admin->two_factor_secret = 'JBSWY3DPEHPK3PXP';
        $admin->save();

        $stored = DB::table('users')->where('id', $admin->id)->value('two_factor_secret');

        $this->assertNotSame('JBSWY3DPEHPK3PXP', $stored);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string) $stored);
        $this->assertSame('JBSWY3DPEHPK3PXP', Crypt::decryptString($stored));
        $this->assertSame('JBSWY3DPEHPK3PXP', $admin->fresh()->two_factor_secret);
    }

    public function test_the_two_factor_secret_is_never_serialised(): void
    {
        $admin = User::factory()->administrator()->create();
        $admin->two_factor_secret = 'JBSWY3DPEHPK3PXP';
        $admin->save();

        $this->assertArrayNotHasKey('two_factor_secret', $admin->fresh()->toArray());
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $admin->fresh()->toJson());
    }

    /* The enrolment screen itself -------------------------------------- */

    /**
     * Both ways of taking the secret, because one of them fails in a
     * conference room. A delegate whose telephone camera will not focus on
     * a projector, or who is enrolling on the same machine as the browser,
     * needs to type the secret in.
     */
    public function test_enrolment_offers_the_secret_as_a_code_and_as_text(): void
    {
        $admin = User::factory()->administrator()->withoutTwoFactor()->create();

        $html = $this->actingAs($admin)->get('/en/two-factor/enrol')->assertOk()->getContent();

        // The QR path: an inline SVG, not an image fetched from a third
        // party. A secret that leaves this platform to be drawn is a secret
        // somebody else has seen.
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('chart.googleapis.com', $html);
        $this->assertStringNotContainsString('qrserver.com', $html);

        // The manual path: the same secret in readable groups.
        $secret = session('two_factor:secret');
        $this->assertNotNull($secret);

        $formatted = app(\App\Services\TwoFactorService::class)->formatSecret($secret);
        $this->assertStringContainsString($formatted, $html);

        // Both describe the same secret.
        $this->assertSame($secret, str_replace(' ', '', $formatted));
    }

    /**
     * Recovery codes are shown once, at enrolment, and are never
     * retrievable afterwards: the column holds hashes.
     */
    public function test_recovery_codes_are_displayed_once_and_then_never_again(): void
    {
        $admin = User::factory()->administrator()->withoutTwoFactor()->create();

        $this->actingAs($admin)->get('/en/two-factor/enrol')->assertOk();
        $secret = session('two_factor:secret');

        $code = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($admin)
            ->post('/en/two-factor/confirm', ['code' => $code])
            ->assertRedirect()
            ->assertSessionHas('recovery_codes');

        $codes = session('recovery_codes');
        $this->assertNotEmpty($codes);

        // Shown on the page they are handed to.
        $this->actingAs($admin)->get('/en/two-factor/enrol')
            ->assertOk()
            ->assertSee($codes[0]);

        // And gone on the next request: they were in a flash, and the
        // stored copy is a hash list.
        $again = $this->actingAs($admin)->get('/en/two-factor/enrol')->assertOk()->getContent();
        $this->assertStringNotContainsString($codes[0], $again);

        // Stored as a JSON list of hashes, encrypted at rest.
        $stored = json_decode($admin->fresh()->two_factor_recovery_codes, true);

        $this->assertNotEmpty($stored);

        foreach ($stored as $hash) {
            $this->assertNotContains($hash, $codes, 'A recovery code is stored in the clear.');
        }
    }
}