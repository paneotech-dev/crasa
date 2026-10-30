<?php

namespace Tests\Feature;

use App\Mail\EnquiryReceived;
use App\Models\Country;
use App\Models\Enquiry;
use App\Support\Locales;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        Mail::fake();
        RateLimiter::clear('enquiries');
    }

    /** @return array<string, string> */
    private function validEnquiry(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Thandiwe Moyo',
            'email' => 'thandiwe@example.test',
            'organisation' => 'Botswana Communications Regulatory Authority',
            'subject' => 'Roaming guidelines',
            'message' => 'We would like clarification on the roaming guidelines before the next committee meeting.',
            'website' => '',
        ], $overrides);
    }

    public function test_the_contact_page_renders_in_every_locale(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get("/{$locale}/contact")->assertOk();
        }
    }

    public function test_a_valid_enquiry_is_stored(): void
    {
        $this->post('/en/contact', $this->validEnquiry())
            ->assertRedirect(route('contact', ['locale' => 'en']))
            ->assertSessionHas('enquiry_received');

        $this->assertDatabaseHas('enquiries', [
            'email' => 'thandiwe@example.test',
            'subject' => 'Roaming guidelines',
            'locale' => 'en',
        ]);
    }

    /**
     * Stored first, then queued. A mail outage delays the notification; it
     * never loses the enquiry.
     */
    public function test_the_enquiry_is_stored_before_the_mail_is_queued(): void
    {
        $this->post('/en/contact', $this->validEnquiry());

        $enquiry = Enquiry::sole();

        $this->assertNotNull($enquiry->id);
        Mail::assertQueued(EnquiryReceived::class, fn ($mail) => $mail->enquiry->is($enquiry));
        $this->assertNotNull($enquiry->fresh()->delivered_at);
    }

    public function test_the_mail_is_queued_not_sent_synchronously(): void
    {
        $this->post('/en/contact', $this->validEnquiry());

        Mail::assertQueued(EnquiryReceived::class);
        Mail::assertNotSent(EnquiryReceived::class);
    }

    public function test_the_notification_replies_to_the_sender(): void
    {
        $this->post('/en/contact', $this->validEnquiry());

        Mail::assertQueued(EnquiryReceived::class, function ($mail) {
            return $mail->envelope()->replyTo[0]->address === 'thandiwe@example.test';
        });
    }

    public function test_an_enquiry_records_the_member_state_when_one_is_given(): void
    {
        $country = Country::where('iso_code', 'bw')->sole();

        $this->post('/en/contact', $this->validEnquiry(['country_id' => $country->id]));

        $this->assertSame($country->id, Enquiry::sole()->country_id);
    }

    /* Validation ----------------------------------------------------------- */

    public function test_the_required_fields_are_required(): void
    {
        $this->post('/en/contact', ['website' => ''])
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_message_of_two_words_is_refused(): void
    {
        $this->post('/en/contact', $this->validEnquiry(['message' => 'Hello']))
            ->assertSessionHasErrors('message');
    }

    public function test_validation_messages_come_in_the_readers_language(): void
    {
        $this->post('/fr/contact', ['website' => ''])->assertSessionHasErrors('name');
        $errors = session('errors')->get('name');
        $this->assertStringContainsString('Votre nom', $errors[0]);

        $this->post('/pt/contact', ['website' => ''])->assertSessionHasErrors('name');
        $errors = session('errors')->get('name');
        $this->assertStringContainsString('O seu nome', $errors[0]);
    }

    /* Honeypot -------------------------------------------------------------- */

    public function test_a_filled_honeypot_is_refused_and_stores_nothing(): void
    {
        $this->post('/en/contact', $this->validEnquiry(['website' => 'https://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, Enquiry::count());
        Mail::assertNothingQueued();
    }

    public function test_the_honeypot_is_hidden_from_people_and_screen_readers(): void
    {
        $html = $this->get('/en/contact')->getContent();

        $this->assertStringContainsString('c-honeypot', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
    }

    /* Rate limiting; Turnstile only when its keys are set (TurnstileTest) ---- */

    public function test_without_turnstile_keys_the_form_uses_no_captcha(): void
    {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret' => null]);

        $html = $this->get('/en/contact')->getContent();

        foreach (['recaptcha', 'hcaptcha', 'turnstile', 'g-recaptcha'] as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $html);
        }
    }

    public function test_a_flood_of_enquiries_is_throttled(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/en/contact', $this->validEnquiry(['subject' => 'Enquiry '.$i]))
                ->assertSessionHasNoErrors();
        }

        $this->post('/en/contact', $this->validEnquiry(['subject' => 'One too many']))
            ->assertSessionHasErrors('message');

        $this->assertSame(6, Enquiry::count());
    }

    public function test_the_throttle_message_names_a_way_through(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/en/contact', $this->validEnquiry(['subject' => 'Enquiry '.$i]));
        }

        // Follow the redirect and read the message off the rendered page,
        // which is where the sender actually sees it.
        $this->followingRedirects()
            ->post('/en/contact', $this->validEnquiry())
            ->assertSee('crasa@crasa.org');
    }

    /* Accessibility --------------------------------------------------------- */

    public function test_every_field_has_a_real_label(): void
    {
        $html = $this->get('/en/contact')->getContent();

        foreach (['name', 'email', 'organisation', 'country_id', 'subject', 'message'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<label[^>]*for="'.preg_quote($field, '/').'"/',
                $html,
                'Field '.$field.' has no label element.'
            );
        }
    }

    /* Privacy ---------------------------------------------------------------- */

    /**
     * The map is drawn (C15, 29 October 2026) over a saved image of the same
     * area, so a failed tile server still leaves a map; since item 1h it is
     * drawn only after consent or "Show the map" (CookieConsentTest).
     */
    public function test_the_map_is_drawn_on_load_with_a_static_fallback(): void
    {
        $html = $this->get('/en/contact')->getContent();

        $this->assertStringContainsString('data-contact-map', $html);
        $this->assertStringContainsString('/js/contact-map.js', $html);
        $this->assertStringContainsString('images/contact/contact-map.webp', $html);
        $this->assertFileExists(public_path('images/contact/contact-map.webp'));
        $this->assertMatchesRegularExpression('~<noscript>\s*<p class="cp-map__noscript"><a [^>]*data-map-load[^>]*>Show the map</a>~', $html);
        $this->assertStringContainsString('Open in Google Maps', $html);
    }

    public function test_leaflet_is_served_from_this_platform_not_a_cdn(): void
    {
        $js = file_get_contents(public_path('js/contact-map.js'));

        $this->assertStringContainsString('/js/vendor/leaflet.js', $js);
        $this->assertStringNotContainsString('unpkg.com', $js);
        $this->assertStringNotContainsString('cdn.jsdelivr', $js);
        $this->assertFileExists(public_path('js/vendor/leaflet.js'));
    }

    public function test_the_address_is_readable_without_the_map(): void
    {
        $this->get('/en/contact')
            ->assertSee('P.O. Box 25793, Gaborone, Botswana')
            ->assertSee('crasa@crasa.org');
    }
}
