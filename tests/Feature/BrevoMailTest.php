<?php

namespace Tests\Feature;

use App\Enums\NotificationTrigger;
use App\Mail\DelegateRegistrationFiled;
use App\Mail\NotificationDigest;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\RegulatoryActivity;
use App\Support\DeliveryCheck;
use App\Support\MailStatus;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

/**
 * Production sends mail through Brevo's HTTP API (MAIL_MAILER=brevo);
 * staging keeps the log mailer. Every "e-mail is switched off" badge and
 * notice reads App\Support\MailStatus, so it follows the mailer.
 *
 * No test here talks to Brevo: the transport is built and inspected, never
 * used, and every send goes to Mail::fake().
 */
class BrevoMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
    }

    private function brevo(): void
    {
        config([
            'mail.default' => 'brevo',
            'services.brevo.key' => 'test-key-from-config',
            'mail.from.address' => 'admin@crasa.africa',
            'mail.from.name' => 'CRASA',
        ]);
    }

    /* The transport ---------------------------------------------------------- */

    public function test_the_brevo_mailer_is_built_from_config_as_the_api_transport(): void
    {
        $this->brevo();

        $transport = Mail::mailer('brevo')->getSymfonyTransport();

        $this->assertInstanceOf(BrevoApiTransport::class, $transport);
        $this->assertSame('test-key-from-config', (new \ReflectionProperty($transport, 'key'))->getValue($transport));
        // The transport's name, which is what reaches a log or an error page,
        // does not carry the key.
        $this->assertStringNotContainsString('test-key-from-config', (string) $transport);
    }

    public function test_the_key_is_read_from_services_config_not_from_the_mailer_entry(): void
    {
        config(['services.brevo.key' => 'another-key']);
        $this->assertArrayNotHasKey('key', config('mail.mailers.brevo'));

        $transport = Mail::mailer('brevo')->getSymfonyTransport();

        $this->assertSame('another-key', (new \ReflectionProperty($transport, 'key'))->getValue($transport));
    }

    /* MailStatus ------------------------------------------------------------- */

    public function test_mail_is_on_with_brevo_and_off_with_log_array_or_null(): void
    {
        $this->brevo();
        $this->assertTrue(MailStatus::isOn());
        $this->assertFalse(MailStatus::isOff());
        $this->assertFalse(DeliveryCheck::mailIsSilent());

        foreach (['log', 'array', 'null', ''] as $mailer) {
            config(['mail.default' => $mailer]);
            $this->assertFalse(MailStatus::isOn(), $mailer.' delivers nothing.');
            $this->assertTrue(DeliveryCheck::mailIsSilent());
        }
    }

    public function test_off_in_production_only_outside_local_and_testing(): void
    {
        config(['mail.default' => 'log']);
        $this->assertFalse(MailStatus::isOffInProduction());

        $this->app['env'] = 'production';
        $this->assertTrue(MailStatus::isOffInProduction());

        $this->brevo();
        $this->assertFalse(MailStatus::isOffInProduction());
    }

    /* The badge and the notices ---------------------------------------------- */

    public function test_the_administrator_badge_is_hidden_when_mail_is_on(): void
    {
        $this->brevo();
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin')
            ->assertOk()
            ->assertDontSee('a-top__mailoff', false)
            ->assertDontSee(__('office.mail_off_badge'));
    }

    public function test_the_administrator_badge_is_shown_when_mail_is_off(): void
    {
        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin')
            ->assertOk()
            ->assertSee('a-top__mailoff', false)
            ->assertSee(__('office.mail_off_badge'));
    }

    public function test_the_notification_page_says_mail_is_sent_only_when_it_is(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->brevo();
        $this->actingAs($admin)->get('/en/notifications')->assertOk()
            ->assertSee(__('d3_mail.email_note_on'))
            ->assertDontSee(__('notifications.email_note'));

        config(['mail.default' => 'log']);
        $this->actingAs($admin)->get('/en/notifications')->assertOk()
            ->assertSee(__('notifications.email_note'))
            ->assertDontSee(__('d3_mail.email_note_on'));
    }

    public function test_the_registrations_page_drops_the_mail_off_notice_when_mail_is_on(): void
    {
        Mail::fake();
        $admin = User::factory()->administrator()->create();
        $this->registration();

        $this->brevo();
        $this->actingAs($admin)->get('/en/admin/registrations')->assertOk()
            ->assertDontSee(__('d3_people.mail_off_note'))
            ->assertSee(__('d3_mail.reg_chain_on'));

        config(['mail.default' => 'log']);
        $this->actingAs($admin)->get('/en/admin/registrations')->assertOk()
            ->assertSee(__('d3_people.mail_off_note'))
            ->assertSee(__('d3_people.reg_chain'));
    }

    /* Sending ---------------------------------------------------------------- */

    public function test_a_resent_copy_goes_through_mail_and_says_it_was_sent(): void
    {
        Mail::fake();
        $this->brevo();
        $row = $this->registration();

        $this->actingAs(User::factory()->administrator()->create())
            ->post("/en/admin/registrations/{$row->id}/resend")
            ->assertSessionHas('status', __('d3_people.resent', ['name' => 'Thandiwe Phiri', 'email' => 'thandiwe@example.org']));

        Mail::assertQueued(DelegateRegistrationFiled::class, fn ($mail) => $mail->hasTo('thandiwe@example.org'));
    }

    public function test_the_notification_digest_goes_through_mail_with_brevo_configured(): void
    {
        Mail::fake();
        $this->brevo();
        $this->seed(CountrySeeder::class);
        $rep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
            'digest_frequency' => 'daily',
        ]);

        $rep->notify(new RegulatoryActivity(NotificationTrigger::Announcement, 'A notice worth reading', url('/en/announcements')));

        $this->artisan('notifications:digest')->assertSuccessful();

        Mail::assertSent(NotificationDigest::class, fn (NotificationDigest $mail): bool => $mail->hasTo($rep->email));
    }

    private function registration(): DelegateRegistration
    {
        $this->seed(SectorSeeder::class);
        $meeting = Meeting::factory()->upcoming()->create(['clearance' => 'public']);

        return DelegateRegistration::create([
            'meeting_id' => $meeting->id,
            'delegate_name' => 'Thandiwe Phiri',
            'delegate_email' => 'thandiwe@example.org',
            'arrival_on' => $meeting->starts_on->copy()->subDay()->toDateString(),
            'departure_on' => $meeting->starts_on->toDateString(),
            'status' => 'filed',
        ]);
    }
}
