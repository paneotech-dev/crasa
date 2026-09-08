<?php

namespace Tests\Feature;

use App\Console\Commands\CreateUser;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Screens 11 and 13. The interface for the layer built in task 1.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct horse battery 42';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:');
    }

    private function account(string $state = 'editor'): User
    {
        return User::factory()->{$state}()->create(['password' => Hash::make(self::PASSWORD)]);
    }

    /* Login ----------------------------------------------------------------- */

    public function test_the_login_page_renders_in_every_locale(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get("/{$locale}/login")->assertOk();
        }
    }

    public function test_valid_credentials_sign_a_user_in(): void
    {
        $user = $this->account();

        $this->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Screen 11 is explicit: the message never states which of the two was
     * wrong, and reads identically for an unknown address, so the form
     * cannot be used to discover who holds an account.
     */
    public function test_the_failure_message_does_not_reveal_which_field_was_wrong(): void
    {
        $user = $this->account();

        $first = $this->post('/en/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertSessionHasErrors('email')
            ->baseResponse->getSession()->get('errors')->get('email')[0];

        $second = $this->post('/en/login', ['email' => 'nobody@example.test', 'password' => 'nope'])
            ->assertSessionHasErrors('email')
            ->baseResponse->getSession()->get('errors')->get('email')[0];

        // Identical wording for a wrong password and an unknown address.
        $this->assertSame(
            preg_replace('/\d+/', 'N', $first),
            preg_replace('/\d+/', 'N', $second)
        );
        $this->assertGuest();
    }

    public function test_the_failure_message_counts_down_the_remaining_attempts(): void
    {
        $user = $this->account();

        // Read off the rendered page, which is where the sender sees it.
        $this->followingRedirects()
            ->post('/en/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertSee('4 attempts remain');
    }

    public function test_five_failures_lock_the_account_for_thirty_minutes(): void
    {
        $user = $this->account();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/en/login', ['email' => $user->email, 'password' => 'nope']);
        }

        // Even the correct password is refused once locked.
        $this->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_failed_login_writes_an_audit_row_with_no_user(): void
    {
        $this->post('/en/login', ['email' => 'nobody@example.test', 'password' => 'nope']);

        $entry = AuditLog::where('action', AuditLog::LOGIN_FAILED)->sole();

        $this->assertNull($entry->user_id);
        $this->assertSame('nobody@example.test', $entry->changes['email']);
        $this->assertNotNull($entry->ip_address);
    }

    public function test_a_successful_login_writes_an_audit_row_and_stamps_the_account(): void
    {
        $user = $this->account();

        $this->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $this->assertSame(1, AuditLog::where('action', AuditLog::LOGIN)->where('user_id', $user->id)->count());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertNotNull($user->fresh()->last_login_ip);
    }

    public function test_logout_writes_an_audit_row(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('/en/logout')->assertRedirect();

        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', AuditLog::LOGOUT)->count());
    }

    /* Two-factor ------------------------------------------------------------- */

    public function test_a_correct_password_alone_does_not_authenticate_an_enrolled_account(): void
    {
        $user = $this->account('administrator');

        $this->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('two-factor.challenge', ['locale' => 'en']));

        // The session holds a pending id, not an authenticated user.
        $this->assertGuest();
    }

    public function test_the_challenge_accepts_a_valid_code(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();
        $user = $this->account('administrator');
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post('/en/two-factor/challenge', ['code' => $code, 'mode' => 'totp'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_code_is_refused_and_audited(): void
    {
        $service = app(TwoFactorService::class);
        $user = $this->account('administrator');
        $user->forceFill([
            'two_factor_secret' => $service->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/en/two-factor/challenge', ['code' => '000000', 'mode' => 'totp'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', AuditLog::TWO_FACTOR_FAILED)->count());
    }

    public function test_the_challenge_cannot_be_reached_without_a_pending_login(): void
    {
        $this->get('/en/two-factor/challenge')->assertForbidden();
        $this->post('/en/two-factor/challenge', ['code' => '123456'])->assertForbidden();
    }

    /* Recovery codes ---------------------------------------------------------- */

    public function test_a_recovery_code_signs_a_user_in_and_works_only_once(): void
    {
        $service = app(TwoFactorService::class);
        $user = $this->account('administrator');
        $user->forceFill([
            'two_factor_secret' => $service->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $codes = $service->regenerateRecoveryCodes($user);
        $code = $codes[0];

        $this->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/en/two-factor/challenge', ['code' => $code, 'mode' => 'recovery'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post('/en/logout');

        // The same code a second time is refused.
        $this->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/en/two-factor/challenge', ['code' => $code, 'mode' => 'recovery'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_codes_are_hashed_and_never_retrievable(): void
    {
        $service = app(TwoFactorService::class);
        $user = $this->account('administrator');

        $codes = $service->regenerateRecoveryCodes($user);

        $stored = DB::table('users')->where('id', $user->id)
            ->value('two_factor_recovery_codes');

        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, (string) $stored);
        }

        // Decrypted, it is still only hashes.
        $hashes = json_decode(Crypt::decryptString($stored), true);
        $this->assertCount(TwoFactorService::RECOVERY_CODE_COUNT, $hashes);
        foreach ($hashes as $hash) {
            $this->assertStringStartsWith('$2y$', $hash);
        }
    }

    public function test_using_a_recovery_code_is_audited(): void
    {
        $service = app(TwoFactorService::class);
        $user = $this->account('administrator');
        $codes = $service->regenerateRecoveryCodes($user);

        $service->consumeRecoveryCode($user, $codes[0]);

        $entry = AuditLog::where('action', AuditLog::RECOVERY_CODE_USED)->sole();
        $this->assertSame(7, $entry->changes['codes_remaining']);
    }

    /* Enrolment --------------------------------------------------------------- */

    public function test_enrolment_offers_the_secret_as_a_qr_code_and_as_text(): void
    {
        $user = $this->account('administrator')->fresh();
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        $html = $this->actingAs($user)->get('/en/two-factor/enrol')->assertOk()->getContent();

        $this->assertStringContainsString('<svg', $html, 'No QR code was rendered.');
        $this->assertStringContainsString('c-secret', $html, 'The secret was not offered as text.');
        $this->assertStringContainsString('Cannot scan the code?', $html);
    }

    public function test_confirming_enrolment_turns_it_on_and_shows_recovery_codes_once(): void
    {
        $user = $this->account('administrator');
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        $this->actingAs($user)->get('/en/two-factor/enrol');
        $secret = session('two_factor:secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($user)->post('/en/two-factor/confirm', ['code' => $code])->assertRedirect();

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertCount(TwoFactorService::RECOVERY_CODE_COUNT, session('recovery_codes'));
        $this->assertSame(1, AuditLog::where('action', AuditLog::TWO_FACTOR_ENROLLED)->count());
    }

    public function test_the_codes_page_warns_they_will_not_be_shown_again(): void
    {
        $user = $this->account('administrator');
        app(TwoFactorService::class)->regenerateRecoveryCodes($user);

        $this->actingAs($user)
            ->withSession(['recovery_codes' => ['AAAAA-BBBBB']])
            ->get('/en/two-factor/enrol')
            ->assertSee('They will not be shown again');
    }

    public function test_an_administrator_cannot_turn_two_factor_off(): void
    {
        $user = $this->account('administrator');

        $this->actingAs($user)->delete('/en/two-factor')->assertSessionHasErrors('two_factor');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    /* Account ------------------------------------------------------------------ */

    public function test_a_user_can_change_their_language_and_notifications(): void
    {
        $user = $this->account();

        $this->actingAs($user)->put('/en/account', [
            'name' => $user->name,
            'email' => $user->email,
            'preferred_locale' => 'pt',
            'notifications' => ['publications', 'meetings'],
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('pt', $user->preferred_locale);
        $this->assertTrue($user->wantsNotification('publications'));
        $this->assertFalse($user->wantsNotification('submissions'));
    }

    public function test_changing_a_password_requires_the_current_one_and_is_audited(): void
    {
        $user = $this->account();

        $this->actingAs($user)->put('/en/account/password', [
            'current_password' => 'wrong',
            'password' => 'a brand new password 9',
            'password_confirmation' => 'a brand new password 9',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/en/account/password', [
            'current_password' => self::PASSWORD,
            'password' => 'a brand new password 9',
            'password_confirmation' => 'a brand new password 9',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('a brand new password 9', $user->fresh()->password));
        $this->assertSame(1, AuditLog::where('action', AuditLog::PASSWORD_CHANGED)->count());
    }

    /* Administration ------------------------------------------------------------ */

    public function test_only_an_administrator_reaches_user_administration(): void
    {
        $this->actingAs($this->account('editor'))->get('/en/admin/users')->assertForbidden();
        $this->actingAs($this->account('viewer'))->get('/en/admin/users')->assertForbidden();
        $this->actingAs($this->account('administrator'))->get('/en/admin/users')->assertOk();
    }

    public function test_only_an_administrator_reaches_the_audit_log(): void
    {
        $this->actingAs($this->account('editor'))->get('/en/admin/audit')->assertForbidden();
        $this->actingAs($this->account('administrator'))->get('/en/admin/audit')->assertOk();
    }

    public function test_the_matrix_states_each_cell_as_a_character_not_only_a_colour(): void
    {
        $html = $this->actingAs($this->account('administrator'))->get('/en/admin/users')->getContent();

        $this->assertStringContainsString('Granted', $html);
        $this->assertStringContainsString('Not granted', $html);
        $this->assertStringContainsString('own member state only', $html);
    }

    public function test_suspending_and_reactivating_are_audited(): void
    {
        $admin = $this->account('administrator');
        $target = $this->account('editor');

        $this->actingAs($admin)->put("/en/admin/users/{$target->id}/suspend")->assertRedirect();
        $this->assertTrue($target->fresh()->isSuspended());

        $this->actingAs($admin)->put("/en/admin/users/{$target->id}/reactivate")->assertRedirect();
        $this->assertFalse($target->fresh()->isSuspended());

        $this->assertSame(1, AuditLog::where('action', AuditLog::SUSPENDED)->count());
        $this->assertSame(1, AuditLog::where('action', AuditLog::REACTIVATED)->count());
    }

    public function test_an_administrator_cannot_suspend_themselves(): void
    {
        $admin = $this->account('administrator');

        $this->actingAs($admin)->put("/en/admin/users/{$admin->id}/suspend")->assertForbidden();
        $this->assertFalse($admin->fresh()->isSuspended());
    }

    public function test_a_role_change_records_the_old_and_new_role(): void
    {
        $admin = $this->account('administrator');
        $target = $this->account('viewer');

        $this->actingAs($admin)->put("/en/admin/users/{$target->id}/role", ['role' => 'editor']);

        $entry = AuditLog::where('action', AuditLog::ROLE_CHANGED)->sole();
        $this->assertSame('viewer', $entry->changes['from']);
        $this->assertSame('editor', $entry->changes['to']);
        $this->assertSame($admin->id, $entry->user_id);
    }

    public function test_an_invited_account_is_never_given_a_password_by_an_administrator(): void
    {
        $admin = $this->account('administrator');

        $this->actingAs($admin)->post('/en/admin/users/invite', [
            'name' => 'New Person',
            'email' => 'new@crasa.org',
            'role' => 'viewer',
            'preferred_locale' => 'en',
        ])->assertRedirect();

        $invited = User::where('email', 'new@crasa.org')->sole();

        $this->assertSame('invited', $invited->status->value);
        // The password is random and unknown to anyone; the holder sets it
        // from the reset link.
        $this->assertFalse(Hash::check(self::PASSWORD, $invited->password));
    }

    /* The console command --------------------------------------------------------- */

    public function test_the_user_command_takes_no_password_argument(): void
    {
        // A password in an argument lands in shell history. There must be no
        // option to pass one, so it cannot happen by accident.
        $definition = (new CreateUser)->getDefinition();

        $this->assertFalse($definition->hasOption('password'));
        $this->assertFalse($definition->hasArgument('password'));
    }

    public function test_the_user_command_creates_an_account_from_prompts(): void
    {
        $this->artisan('crasa:user', ['--email' => 'console@crasa.org', '--name' => 'Console User', '--role' => 'editor'])
            ->expectsQuestion('Password (not echoed)', 'a fine long password 7')
            ->expectsQuestion('Confirm password', 'a fine long password 7')
            ->assertSuccessful();

        $user = User::where('email', 'console@crasa.org')->sole();
        $this->assertTrue(Hash::check('a fine long password 7', $user->password));
    }

    public function test_the_user_command_refuses_mismatched_passwords(): void
    {
        $this->artisan('crasa:user', ['--email' => 'x@crasa.org', '--name' => 'X', '--role' => 'viewer'])
            ->expectsQuestion('Password (not echoed)', 'one password here 1')
            ->expectsQuestion('Confirm password', 'a different one 2')
            ->assertFailed();

        $this->assertSame(0, User::where('email', 'x@crasa.org')->count());
    }

    public function test_no_seeder_creates_an_account(): void
    {
        // A seeded credential on a public development URL is how a demo
        // password reached a circulated PDF on this project.
        foreach (glob(database_path('seeders/*.php')) as $seeder) {
            $contents = file_get_contents($seeder);

            $this->assertStringNotContainsString('User::create', $contents, basename($seeder));
            $this->assertStringNotContainsString('User::factory', $contents, basename($seeder));
        }
    }
}
