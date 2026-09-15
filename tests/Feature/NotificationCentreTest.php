<?php

namespace Tests\Feature;

use App\Enums\NotificationTrigger;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\RegulatoryActivity;
use App\Services\NotifiesMembers;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The notification centre and its triggers. RP-05, extranet item 6.
 *
 * TWO THINGS THESE HOLD. That every audience is derived from seats and
 * mandates rather than from a stored subscription list, so Botswana is
 * never notified about anything of Mozambique's. And that the trigger set
 * stays closed: nothing here is about a journey, a room or a transfer.
 */
class NotificationCentreTest extends TestCase
{
    use RefreshDatabase;

    private User $botswanaRep;

    private User $mozambiqueRep;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->botswanaRep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
        ]);

        $this->mozambiqueRep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'mz')->value('id'),
        ]);
    }

    public function test_the_bell_leads_to_the_centre_and_not_to_an_anchor(): void
    {
        $html = $this->actingAs($this->botswanaRep)->get('/en/extranet/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('notifications', ['locale' => 'en']).'"', $html);
        $this->assertStringNotContainsString('account#notifications', $html);
    }

    public function test_the_centre_lists_a_notice_and_marks_it_read_on_the_way_through(): void
    {
        $this->botswanaRep->notify(new RegulatoryActivity(
            NotificationTrigger::Announcement,
            'A notice worth reading',
            route('announcements', ['locale' => 'en'])
        ));

        $this->actingAs($this->botswanaRep)
            ->get('/en/notifications')
            ->assertOk()
            ->assertSee('A notice worth reading');

        $notification = $this->botswanaRep->notifications()->sole();
        $this->assertNull($notification->read_at);

        $this->actingAs($this->botswanaRep)
            ->get('/en/notifications/'.$notification->id)
            ->assertRedirect(route('announcements', ['locale' => 'en']));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * The stored URL is data written by a sender. A sender that could be
     * persuaded to write another host would be an open redirect behind an
     * authenticated route.
     */
    public function test_a_notification_pointing_off_this_host_does_not_redirect_there(): void
    {
        $this->botswanaRep->notify(new RegulatoryActivity(
            NotificationTrigger::Announcement,
            'Somewhere else entirely',
            'https://example.invalid/phish'
        ));

        $notification = $this->botswanaRep->notifications()->sole();

        $this->actingAs($this->botswanaRep)
            ->get('/en/notifications/'.$notification->id)
            ->assertRedirect(route('notifications', ['locale' => 'en']));
    }

    public function test_preferences_are_saved_per_trigger(): void
    {
        $this->actingAs($this->botswanaRep)
            ->put('/en/notifications/preferences', [
                'triggers' => ['committee_document', 'announcement'],
                'digest_frequency' => 'weekly',
            ])
            ->assertRedirect();

        $user = $this->botswanaRep->fresh();

        $this->assertSame(['committee_document', 'announcement'], $user->notification_preferences);
        $this->assertSame('weekly', $user->digest_frequency);
    }

    public function test_an_unrecognised_trigger_is_refused(): void
    {
        $this->actingAs($this->botswanaRep)
            ->put('/en/notifications/preferences', [
                'triggers' => ['flight_reminder'],
                'digest_frequency' => 'daily',
            ])
            ->assertSessionHasErrors('triggers.0');
    }

    /* The audience ---------------------------------------------------------- */

    public function test_a_committee_trigger_reaches_only_the_seats_on_that_committee(): void
    {
        $committee = Committee::firstOrFail();
        $committee->users()->attach($this->botswanaRep->id, ['role' => 'member', 'is_sample' => false]);

        $sent = app(NotifiesMembers::class)->fire(
            NotificationTrigger::CommitteeDocument,
            'A paper was filed',
            url('/en/extranet'),
            committee: $committee,
        );

        $this->assertSame(0, $sent, 'Nobody is subscribed to this trigger by default, so nobody should be notified.');

        $this->botswanaRep->update(['notification_preferences' => ['committee_document']]);
        $this->mozambiqueRep->update(['notification_preferences' => ['committee_document']]);

        $sent = app(NotifiesMembers::class)->fire(
            NotificationTrigger::CommitteeDocument,
            'A paper was filed',
            url('/en/extranet'),
            committee: $committee,
        );

        $this->assertSame(1, $sent);
        $this->assertSame(1, $this->botswanaRep->notifications()->count());
        $this->assertSame(0, $this->mozambiqueRep->notifications()->count(), 'Mozambique holds no seat on this committee.');
    }

    public function test_a_sector_trigger_reaches_only_the_regulators_that_hold_that_mandate(): void
    {
        $postal = Sector::where('slug', 'postal')->firstOrFail();

        $botswanaAuthority = \App\Models\Authority::factory()->create(['country_id' => $this->botswanaRep->country_id]);
        $botswanaAuthority->sectors()->attach($postal->id);
        $this->botswanaRep->update(['authority_id' => $botswanaAuthority->id, 'notification_preferences' => ['sector_instrument']]);

        $mozambiqueAuthority = \App\Models\Authority::factory()->create(['country_id' => $this->mozambiqueRep->country_id]);
        $mozambiqueAuthority->sectors()->attach(Sector::where('slug', 'broadcasting')->value('id'));
        $this->mozambiqueRep->update(['authority_id' => $mozambiqueAuthority->id, 'notification_preferences' => ['sector_instrument']]);

        $sent = app(NotifiesMembers::class)->fire(
            NotificationTrigger::SectorInstrument,
            'A postal guideline entered the register',
            url('/en/register'),
            sector: $postal,
        );

        $this->assertSame(1, $sent);
        $this->assertSame(1, $this->botswanaRep->notifications()->count());
        $this->assertSame(0, $this->mozambiqueRep->notifications()->count());
    }

    /**
     * The database row is always written. Turning email off must not mean
     * losing the record.
     */
    public function test_turning_email_off_does_not_stop_the_record_being_written(): void
    {
        Mail::fake();

        $this->botswanaRep->update(['digest_frequency' => 'never', 'notification_preferences' => []]);

        $this->botswanaRep->notify(new RegulatoryActivity(
            NotificationTrigger::SubmissionDecision,
            'Your return was validated',
            url('/en/submissions')
        ));

        $this->assertSame(1, $this->botswanaRep->notifications()->count());
    }

    /* The boundary the trigger set holds ------------------------------------ */

    /**
     * Event and travel management is reserved commercial scope. The enum is
     * the boundary, and this is the test that notices when somebody widens
     * it.
     */
    public function test_the_trigger_set_carries_nothing_logistical(): void
    {
        $forbidden = ['travel', 'flight', 'hotel', 'accommodation', 'transfer', 'catering', 'reminder', 'visa', 'room'];

        foreach (NotificationTrigger::values() as $value) {
            foreach ($forbidden as $word) {
                $this->assertStringNotContainsString(
                    $word,
                    $value,
                    "The trigger [{$value}] is about logistics. Event and travel management is reserved commercial scope."
                );
            }
        }

        $this->assertCount(6, NotificationTrigger::cases());
    }

    public function test_the_digest_sends_nothing_to_an_account_with_nothing_unread(): void
    {
        Mail::fake();

        $this->artisan('notifications:digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_digest_sends_what_is_unread_and_records_that_it_did(): void
    {
        Mail::fake();

        $this->botswanaRep->notify(new RegulatoryActivity(
            NotificationTrigger::Announcement,
            'A notice worth reading',
            url('/en/announcements')
        ));

        $this->artisan('notifications:digest')->assertSuccessful();

        Mail::assertSent(\App\Mail\NotificationDigest::class, function (\App\Mail\NotificationDigest $mail): bool {
            return $mail->items->count() === 1 && $mail->user->is($this->botswanaRep);
        });
        $this->assertNotNull($this->botswanaRep->fresh()->digest_sent_at);
    }
}
