<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Meeting;
use App\Support\Turnstile;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Turnstile widget is drawn inside each public form once both keys are
 * set, and nowhere while either is missing.
 *
 * TurnstileTest already renders contact, sign-in and the two password
 * pages with keys set; the meeting registration form is checked there only
 * by reading its Blade source. This test renders all five pages, in the
 * three languages, and checks that the widget sits inside the form that
 * posts to the route VerifyTurnstile guards, so a widget moved outside its
 * form (where its token would never be sent) fails here.
 */
class TurnstileWidgetTest extends TestCase
{
    use RefreshDatabase;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->meeting = Meeting::create([
            'title' => 'CRASA Annual General Meeting',
            'slug' => 'agm-turnstile',
            'kind' => 'agm',
            'starts_on' => now()->addMonth()->toDateString(),
            'ends_on' => now()->addMonth()->addDays(2)->toDateString(),
            'location' => 'Lilongwe, Malawi',
            'clearance' => Clearance::Public,
            'is_sample' => true,
        ]);
    }

    /**
     * Page path and the route its form posts to, per locale.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function forms(string $locale): array
    {
        return [
            'contact' => ["/{$locale}/contact", route('contact.store', ['locale' => $locale])],
            'registration' => [
                "/{$locale}/events/{$this->meeting->slug}/register",
                route('events.register.store', ['locale' => $locale, 'meeting' => $this->meeting->slug]),
            ],
            'sign-in' => ["/{$locale}/login", route('login.store', ['locale' => $locale])],
            'password request' => ["/{$locale}/password/forgotten", route('password.email', ['locale' => $locale])],
            'password reset' => ["/{$locale}/password/reset/some-token", route('password.update', ['locale' => $locale])],
        ];
    }

    /** The HTML of the form on the page that posts to $action, or null. */
    private function formPostingTo(string $html, string $action): ?string
    {
        $pattern = '#<form\b[^>]*\baction="'.preg_quote(e($action), '#').'"[^>]*>.*?</form>#s';

        return preg_match($pattern, $html, $m) ? $m[0] : null;
    }

    public function test_with_both_keys_set_the_widget_is_inside_each_public_form(): void
    {
        config([
            'services.turnstile.site_key' => '1x00000000000000000000AA',
            'services.turnstile.secret' => '1x0000000000000000000000000000000AA',
        ]);

        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach ($this->forms($locale) as $name => [$path, $action]) {
                $html = $this->get($path)->assertOk()->getContent();
                $form = $this->formPostingTo($html, $action);

                $this->assertNotNull($form, "{$name} ({$locale}): no form posting to {$action}");
                $this->assertStringContainsString(
                    'class="cf-turnstile" data-sitekey="1x00000000000000000000AA"',
                    $form,
                    "{$name} ({$locale}): widget missing from the form"
                );
                $this->assertStringContainsString('data-language="'.$locale.'"', $form, "{$name} ({$locale})");
                $this->assertSame(1, substr_count($html, Turnstile::SCRIPT), "{$name} ({$locale}): script once");
            }
        }
    }

    public function test_with_one_key_missing_no_form_draws_the_widget(): void
    {
        config([
            'services.turnstile.site_key' => '1x00000000000000000000AA',
            'services.turnstile.secret' => null,
        ]);

        foreach ($this->forms('en') as $name => [$path]) {
            $this->get($path)->assertOk()
                ->assertDontSee('cf-turnstile', false)
                ->assertDontSee(Turnstile::SCRIPT, false);
        }
    }
}
