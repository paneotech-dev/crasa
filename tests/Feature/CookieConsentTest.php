<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Meeting;
use App\Models\SocialPost;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item 1h: the cookie choice. No third-party script, frame or tile is in a
 * page before the reader allows it; each embed is a placeholder card with a
 * button that loads that one item and a link to it. The banner has three
 * buttons and the footer reopens the settings.
 *
 * The swap itself happens in the browser (public/js/consent.js), because a
 * public page is one cached page for everyone; the tests below hold the
 * server half and the script's contract.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(HomepageSeeder::class);
    }

    /** Every script, frame and image address in a page that is not this platform's own. */
    private function thirdParty(string $html): array
    {
        $own = rtrim(url('/'), '/');
        preg_match_all('~<(script|iframe|img|link)\b[^>]*\s(?:src|href)="(https?://[^"]+)"~i', $html, $m, PREG_SET_ORDER);

        $hits = array_map(fn ($hit) => $hit[1].' '.$hit[2], $m);

        return array_values(array_filter($hits, fn ($hit) => ! str_contains($hit, ' '.$own)));
    }

    public function test_no_third_party_script_frame_or_tile_before_consent(): void
    {
        $this->assertGreaterThan(0, SocialPost::live()->count());
        $meeting = Meeting::create([
            'title' => 'Postal Committee', 'slug' => 'postal-committee',
            'starts_on' => now()->addMonth()->toDateString(), 'ends_on' => now()->addMonth()->addDay()->toDateString(),
            'location' => 'Gaborone, Botswana', 'clearance' => Clearance::Public, 'committee_code' => 'PC',
        ]);

        foreach (['/en', '/en/newsroom', '/en/contact', '/en/events/'.$meeting->slug, '/fr', '/pt/newsroom'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame([], $this->thirdParty($html), $path.' requests another origin before consent.');
            $this->assertStringNotContainsString('<iframe', $html, $path);
            $this->assertStringNotContainsString('platform.twitter.com/widgets.js"', $html, $path);
            $this->assertStringNotContainsString('class="twitter-tweet"', $html, $path);
        }

        // The event map's tiles wait in data-src.
        $event = $this->get('/en/events/'.$meeting->slug)->getContent();
        $this->assertMatchesRegularExpression('~<figure class="e-map" data-consent-embed="maps" data-embed="tiles">~', $event);
        $this->assertStringContainsString('data-src="https://tile.openstreetmap.org/', $event);
    }

    public function test_each_social_embed_is_a_placeholder_card_that_names_the_network(): void
    {
        $linkedin = SocialPost::live()->where('network', 'linkedin')->first();
        $this->assertNotNull($linkedin);

        foreach (['/en', '/en/newsroom'] as $path) {
            $html = $this->get($path)->getContent();

            $this->assertStringContainsString('data-consent-embed="social"', $html, $path);
            $this->assertStringContainsString('This post is hosted by LinkedIn. Showing it loads content and cookies from LinkedIn.', $html, $path);
            $this->assertStringContainsString('data-consent-show>Show the post', $html, $path);
        }

        $html = $this->get('/en/newsroom')->getContent();
        // The frame waits as data, with the link to the post beside the button.
        $this->assertStringContainsString('data-embed-src="'.e($linkedin->embed_url).'"', $html);
        $this->assertStringContainsString('href="'.e($linkedin->url).'"', $html);

        $x = SocialPost::live()->where('network', 'x')->whereNotNull('embed_html')->first();
        if ($x) {
            // X: the post text from this platform, without the class X's
            // widget looks for, until consent.
            $this->assertStringContainsString('data-embed="x"', $html);
            $this->assertStringContainsString('<blockquote class="s-post__quote" data-x-quote', $html);
            $this->assertStringContainsString('This post is hosted by X.', $html);
        }

        $fr = $this->get('/fr/newsroom')->getContent();
        $this->assertStringContainsString('Cette publication est hébergée par LinkedIn.', $fr);
        $this->assertStringContainsString('Afficher la publication', $fr);
        $this->assertStringContainsString('Mostrar a publicação', $this->get('/pt/newsroom')->getContent());
    }

    public function test_the_contact_map_is_a_static_placeholder_with_the_address_and_google_maps(): void
    {
        $html = $this->get('/en/contact')->getContent();

        $this->assertMatchesRegularExpression('~<div class="cp-map" data-contact-map data-consent-embed="maps" data-embed="map"~', $html);
        $this->assertStringContainsString('images/contact/contact-map.webp', $html);
        $this->assertStringContainsString('The map is drawn from OpenStreetMap. Showing it sends your IP address to OpenStreetMap.', $html);
        $this->assertMatchesRegularExpression('~<button type="button" class="u-btn u-btn--primary" data-consent-show>Show the map</button>~', $html);
        $this->assertMatchesRegularExpression('~<address class="cp-map__gate-address">Plot 143~', $html);
        $this->assertMatchesRegularExpression('~cp-map__gate-actions">.*?href="https://www.google.com/maps/search/~s', $html);

        // The map script draws only on the consent event, never on load.
        $js = file_get_contents(public_path('js/contact-map.js'));
        $this->assertStringContainsString("box.addEventListener('consent:show'", $js);
        $this->assertDoesNotMatchRegularExpression('~^  draw\(\);$~m', $js);
    }

    public function test_the_banner_has_three_buttons_and_the_settings_one_switch_per_category(): void
    {
        $expect = [
            'en' => ['Accept all', 'Reject non-essential', 'Settings', 'Cookie settings'],
            'fr' => ['Tout accepter', 'Refuser les cookies non essentiels', 'Paramètres', 'Paramètres des cookies'],
            'pt' => ['Aceitar todos', 'Rejeitar não essenciais', 'Definições', 'Definições de cookies'],
        ];

        foreach ($expect as $locale => [$all, $none, $settings, $footer]) {
            $html = $this->get('/'.$locale)->assertOk()->getContent();

            // Hidden until the script finds no stored choice; first after the
            // skip link, so the keyboard reaches it first.
            $this->assertMatchesRegularExpression('~class="c-skip"[^>]*>[^<]*</a>\s*<section class="cc" data-consent-banner aria-labelledby="cc-title" hidden>~', $html);
            preg_match('~<section class="cc".*?</section>~s', $html, $banner);
            $this->assertSame(3, substr_count($banner[0], '<button type="button"'), $locale);
            $this->assertStringContainsString('data-consent-all>'.$all.'</button>', $banner[0]);
            $this->assertStringContainsString('data-consent-none>'.e($none).'</button>', $banner[0]);
            $this->assertStringContainsString('aria-haspopup="dialog">'.e($settings).'</button>', $banner[0]);
            $this->assertStringContainsString('href="'.url($locale.'/privacy').'"', $banner[0]);

            // The settings dialog: a real label per optional category,
            // essential named as always on.
            preg_match('~<dialog class="cc-dialog".*?</dialog>~s', $html, $dialog);
            $this->assertStringContainsString('<input type="checkbox" class="cc-cat__box" id="cc-social" name="social"', $dialog[0]);
            $this->assertStringContainsString('<label class="cc-cat__name" for="cc-social">', $dialog[0]);
            $this->assertStringContainsString('<input type="checkbox" class="cc-cat__box" id="cc-maps" name="maps"', $dialog[0]);
            $this->assertStringContainsString('<label class="cc-cat__name" for="cc-maps">', $dialog[0]);
            $this->assertStringContainsString('data-consent-save>', $dialog[0]);

            // The footer's bottom line reopens it, and leads to the notice
            // without scripting.
            preg_match('~<div class="k-fbar">.*?</div>~s', $html, $bar);
            $this->assertStringContainsString('<a href="'.url($locale.'/privacy').'" data-consent-open aria-haspopup="dialog">'.e($footer).'</a>', $bar[0]);
        }

        $this->assertFileExists(public_path('js/consent.js'));
        $this->assertFileExists(public_path('css/consent.css'));
        $this->assertStringContainsString('/js/consent.js', $this->get('/en/contact')->getContent());
    }

    /**
     * The script's contract: a versioned choice kept twelve months, one flag
     * per category, a category applied only when true, "Show" loading one
     * item, and X's widget script requested only from activate().
     */
    public function test_the_consent_script_keeps_a_versioned_choice_and_loads_only_what_is_allowed(): void
    {
        $js = file_get_contents(public_path('js/consent.js'));

        $this->assertStringContainsString("var KEY = 'crasa-consent';", $js);
        $this->assertStringContainsString('var VERSION = 1;', $js);
        $this->assertStringContainsString('var MAX_AGE = 365 * 24 * 60 * 60 * 1000;', $js);
        $this->assertStringContainsString("var CATEGORIES = ['social', 'maps'];", $js);
        $this->assertStringContainsString("if (choice && choice[el.getAttribute('data-consent-embed')] === true) activate(el, false);", $js);
        $this->assertStringContainsString("decide(all(false))", $js);
        $this->assertStringContainsString("var el = t.closest('[data-consent-embed]');", $js);
        $this->assertSame(1, substr_count($js, 'https://platform.twitter.com/widgets.js'));
        $this->assertSame(1, substr_count($js, "createElement('iframe')"));
    }
}
