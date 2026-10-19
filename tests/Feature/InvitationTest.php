<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Screen 13's invitation, end to end.
 *
 * Until 19 October 2026 an invited account stayed "invited" after its holder
 * chose a password, so every account created from the back office landed on
 * the suspended page at its first sign-in. The 15 October audit tested
 * sending invitations and never accepting one; this test accepts one.
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'workshop room 2026 ok';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
        RateLimiter::clear('login:');
    }

    /** @return list<Email> */
    private function sentMail(): array
    {
        return array_map(
            fn ($sent) => $sent->getOriginalMessage(),
            iterator_to_array(app('mail.manager')->mailer()->getSymfonyTransport()->messages()),
        );
    }

    private function linkIn(Email $mail): string
    {
        preg_match('~https?://[^\s"<]+/password/reset/[^\s"<]+~', $mail->getTextBody(), $m);
        $this->assertNotEmpty($m, 'The mail carries no link.');

        return html_entity_decode($m[0]);
    }

    private function invite(string $locale = 'fr', string $role = 'viewer'): User
    {
        $admin = User::factory()->administrator()->create(['name' => 'Thandi Secretariat']);

        $this->actingAs($admin)->post('/en/admin/users/invite', [
            'name' => 'Invited Person',
            'email' => 'invited@example.test',
            'role' => $role,
            'preferred_locale' => $locale,
        ])->assertRedirect()->assertSessionHasNoErrors();

        auth()->logout();

        return User::where('email', 'invited@example.test')->sole();
    }

    private function accept(string $link, string $email): \Illuminate\Testing\TestResponse
    {
        $path = parse_url($link, PHP_URL_PATH);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $token = basename($path);
        $locale = explode('/', trim($path, '/'))[0];

        $this->get($path.'?'.http_build_query($query))->assertOk();

        return $this->post("/{$locale}/password/reset", [
            'token' => $token,
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);
    }

    public function test_an_invited_user_accepts_chooses_a_password_and_signs_in(): void
    {
        $user = $this->invite('fr');
        $this->assertSame(UserStatus::Invited, $user->status);

        $mails = $this->sentMail();
        $this->assertCount(1, $mails);

        $this->accept($this->linkIn($mails[0]), $user->email)
            ->assertRedirect('/fr/login')
            ->assertSessionHas('status', __('auth.invite_done', [], 'fr'));

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertDatabaseHas('audit_log', [
            'action' => AuditLog::INVITATION_ACCEPTED,
            'auditable_id' => $user->id,
            'user_id' => $user->id,
        ]);

        $this->post('/fr/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // Signed in and allowed in: not sent to the suspended page.
        $this->get('/fr/account')->assertOk();
        $this->get('/fr/account/suspended')->assertStatus(403);
    }

    public function test_the_invitation_is_an_invitation_in_the_invitees_language(): void
    {
        $this->invite('pt', 'member_state_rep');

        $mail = $this->sentMail()[0];
        $text = $mail->getTextBody();

        $this->assertSame('Foi convidado para a plataforma da CRASA', $mail->getSubject());
        $this->assertStringContainsString('Thandi Secretariat convidou-o para a plataforma da CRASA como Representante de Estado-membro.', $text);
        $this->assertStringContainsString('/pt/password/reset/', $text);
        $this->assertStringContainsString('7 dias', $text);

        // Nothing of the framework's reset mail, in any language.
        foreach (['Reset Password', 'password reset request', 'Hello!', 'Regards', 'repor', 'reposição'] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase($phrase, $text);
        }
    }

    public function test_the_invitation_link_lasts_a_week_not_an_hour(): void
    {
        $user = $this->invite('en');
        $link = $this->linkIn($this->sentMail()[0]);

        $this->travel(6)->days();

        $this->accept($link, $user->email)->assertRedirect('/en/login');
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_an_invitation_older_than_a_week_is_refused(): void
    {
        $user = $this->invite('en');
        $link = $this->linkIn($this->sentMail()[0]);

        $this->travel(8)->days();

        $this->accept($link, $user->email)->assertSessionHasErrors('email');
        $this->assertSame(UserStatus::Invited, $user->fresh()->status);
    }

    public function test_a_suspended_account_stays_suspended_after_a_reset(): void
    {
        $user = User::factory()->viewer()->create([
            'status' => UserStatus::Suspended,
            'preferred_locale' => 'en',
        ]);

        $this->post('/en/password/forgotten', ['email' => $user->email])->assertRedirect();

        $this->accept($this->linkIn($this->sentMail()[0]), $user->email)
            ->assertRedirect('/en/login')
            ->assertSessionHas('status', __('auth.reset_done'));

        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);
        $this->assertDatabaseHas('audit_log', ['action' => AuditLog::PASSWORD_RESET, 'auditable_id' => $user->id]);
    }

    public function test_a_reset_mail_goes_out_in_the_holders_language_whatever_page_asked(): void
    {
        $user = User::factory()->viewer()->create(['preferred_locale' => 'fr']);

        $this->post('/pt/password/forgotten', ['email' => $user->email])->assertRedirect();

        $mail = $this->sentMail()[0];
        $this->assertSame('Réinitialiser votre mot de passe de la plateforme de la CRASA', $mail->getSubject());
        $this->assertStringContainsString('/fr/password/reset/', $mail->getTextBody());
        $this->assertStringNotContainsString('Hello!', $mail->getTextBody());
    }
}
