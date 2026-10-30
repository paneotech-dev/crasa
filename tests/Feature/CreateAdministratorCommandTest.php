<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** crasa:create-admin: an invited administrator who chooses their own password. */
class CreateAdministratorCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINEL = 'Sentinel-Random-Value-Never-Shown-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://crasa.africa']);
        // Every random string the command draws is this one, so the test
        // can tell whether the password (or anything derived from it
        // without hashing) reaches the terminal.
        Str::createRandomStringsUsing(fn () => self::SENTINEL);
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();
        parent::tearDown();
    }

    public function test_with_mail_on_it_sends_the_invitation_and_prints_no_password_or_link(): void
    {
        config(['mail.default' => 'smtp']);
        Notification::fake();

        $code = Artisan::call('crasa:create-admin', ['--name' => 'Thabo Mokoena', '--email' => 'T.Mokoena@crasa.org']);
        $output = Artisan::output();

        $this->assertSame(0, $code);
        $user = User::firstWhere('email', 't.mokoena@crasa.org');
        $this->assertSame(Role::Administrator, $user->role);
        $this->assertSame(UserStatus::Invited, $user->status);
        $this->assertTrue(Hash::check(self::SENTINEL, $user->password));

        $this->assertStringNotContainsString(self::SENTINEL, $output);
        $this->assertStringNotContainsString('password/reset', $output);
        $this->assertStringContainsString('Invitation sent', $output);
        Notification::assertSentTo($user, AccountInvitation::class);
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', $user->email)->count());
    }

    public function test_with_mail_off_it_prints_the_one_time_link_and_says_why(): void
    {
        config(['mail.default' => 'log']);
        Notification::fake();

        $code = Artisan::call('crasa:create-admin', ['--name' => 'Thabo Mokoena', '--email' => 'thabo@crasa.org']);
        $output = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringNotContainsString(self::SENTINEL, $output);
        $this->assertStringContainsString('Mail is off', $output);
        $this->assertStringContainsString('https://crasa.africa/en/password/reset/', $output);
        $this->assertStringContainsString('thabo%40crasa.org', $output);
        Notification::assertNothingSent();
    }

    public function test_it_refuses_an_address_that_exists(): void
    {
        $existing = User::factory()->editor()->create(['email' => 'taken@crasa.org']);

        $this->artisan('crasa:create-admin', ['--name' => 'Someone', '--email' => 'Taken@crasa.org'])
            ->expectsOutputToContain('--reinvite')
            ->assertFailed();

        $this->assertSame(Role::Editor, $existing->fresh()->role);
        $this->assertSame(1, User::count());
    }

    public function test_reinvite_sends_an_invited_administrator_a_new_invitation_only(): void
    {
        config(['mail.default' => 'smtp']);
        Notification::fake();

        $invited = User::factory()->administrator()->invited()->create(['email' => 'invited@crasa.org']);
        $active = User::factory()->administrator()->create(['email' => 'active@crasa.org']);
        $editor = User::factory()->editor()->invited()->create(['email' => 'editor@crasa.org']);
        $activeHash = $active->password;

        $this->artisan('crasa:create-admin', ['--name' => 'Invited', '--email' => 'invited@crasa.org', '--reinvite' => true])
            ->assertSuccessful();
        Notification::assertSentTo($invited, AccountInvitation::class);
        $this->assertSame(UserStatus::Invited, $invited->fresh()->status);

        $this->artisan('crasa:create-admin', ['--name' => 'Active', '--email' => 'active@crasa.org', '--reinvite' => true])
            ->assertFailed();
        $this->assertSame($activeHash, $active->fresh()->password);

        $this->artisan('crasa:create-admin', ['--name' => 'Editor', '--email' => 'editor@crasa.org', '--reinvite' => true])
            ->assertFailed();
        $this->assertSame(Role::Editor, $editor->fresh()->role);

        Notification::assertSentTimes(AccountInvitation::class, 1);
    }

    public function test_it_needs_a_name_and_a_valid_address(): void
    {
        $this->artisan('crasa:create-admin', ['--email' => 'x@crasa.org'])->assertFailed();
        $this->artisan('crasa:create-admin', ['--name' => 'X', '--email' => 'not-an-address'])->assertFailed();
        $this->assertSame(0, User::count());
    }
}
