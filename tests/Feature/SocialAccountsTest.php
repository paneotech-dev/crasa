<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Support\Locales;
use App\Support\SocialAccounts;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * CRASA's accounts, and the brand marks that point at them.
 *
 * The three URLs were hardcoded in a Blade template and two of the three
 * were wrong: x.com/Crasa2C, facebook.com/pages/CRASA and
 * linkedin.com/company/crasa. None of those is CRASA's account. A link on a
 * regulator's site pointing somewhere that is not theirs is worse than no
 * link, and a value nobody can edit is a value nobody checks.
 */
class SocialAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(HomepageSeeder::class);
    }

    public function test_the_accounts_are_the_ones_crasa_gave_us(): void
    {
        $this->assertSame('https://x.com/CRASA_ORG', SiteSetting::get('social_x'));
        $this->assertStringContainsString(
            'crasa-secretariat-communications-regulators-association-of-southern-africa',
            (string) SiteSetting::get('social_linkedin'),
        );
        $this->assertSame(
            'https://www.facebook.com/people/CRASA/100064573337016/',
            SiteSetting::get('social_facebook'),
        );
    }

    public function test_the_wrong_accounts_are_gone_from_every_template(): void
    {
        // The specific ones that shipped. Named, so nobody restores them.
        foreach (glob(base_path('resources/views/**/*.blade.php'), GLOB_BRACE) as $view) {
            // Comments stripped first. The template that used to carry
            // these now explains why it does not, and a guard that fails on
            // its own explanation teaches people to delete the explanation.
            // Blade and PHP comments stripped first. The template that
            // used to carry these now explains why it does not, and a guard
            // that fails on its own explanation teaches people to delete
            // the explanation.
            $markup = (string) file_get_contents($view);
            $markup = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $markup);
            $markup = (string) preg_replace('#/\*.*?\*/#s', '', $markup);

            foreach (['Crasa2C', 'facebook.com/pages/CRASA', 'linkedin.com/company/crasa"'] as $wrong) {
                $this->assertStringNotContainsString(
                    $wrong,
                    $markup,
                    basename($view).' hardcodes a social URL that is not CRASA\'s.',
                );
            }
        }
    }

    public function test_an_empty_slot_renders_nothing_rather_than_a_dead_link(): void
    {
        // YouTube is registered and empty until CRASA supplies a channel.
        $this->assertNull(SiteSetting::get('social_youtube'));
        $this->assertArrayNotHasKey('youtube', SocialAccounts::all());

        $this->assertStringNotContainsString('youtube', $this->get('/en')->getContent());
    }

    public function test_editing_a_setting_changes_the_page(): void
    {
        // The whole point of moving them out of the template.
        SiteSetting::where('key', 'social_x')->update(['value' => 'https://x.com/CHANGED']);
        Cache::forget('site-settings');

        $this->assertStringContainsString('https://x.com/CHANGED', $this->get('/en')->getContent());
    }

    public function test_every_social_link_opens_safely_and_says_whose_it_is(): void
    {
        foreach (array_keys(Locales::all()) as $locale) {
            $html = $this->get("/{$locale}")->assertOk()->getContent();

            preg_match_all('/<a class="b-social__link"[^>]*>/', $html, $links);

            $this->assertNotEmpty($links[0], "No social links in {$locale}.");

            foreach ($links[0] as $link) {
                $this->assertStringContainsString('rel="noopener"', $link);
                $this->assertStringContainsString('target="_blank"', $link);

                // "CRASA on LinkedIn", not "LinkedIn": the name of a link
                // should say where it goes and whose it is.
                $this->assertMatchesRegularExpression(
                    '/aria-label="[^"]*(CRASA|La CRASA|A CRASA)[^"]*"/',
                    $link,
                    "A social link in {$locale} does not name whose account it is.",
                );
            }
        }
    }

    public function test_each_mark_is_the_networks_own_in_its_own_colour(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        foreach (['#000000' => 'x', '#0A66C2' => 'linkedin', '#1877F2' => 'facebook'] as $colour => $network) {
            $this->assertStringContainsString(
                'fill="'.$colour.'"',
                $html,
                "The {$network} mark is not in its own brand colour. A recoloured trademark is a "
                .'guideline breach.',
            );
        }

        // Decorative: the link's accessible name carries the meaning.
        // The attributes are written across several lines in the
        // component, so match across whitespace rather than assuming order.
        preg_match_all('/<svg\s[^>]*c-social-mark[^>]*>/s', $html, $marks);
        $this->assertNotEmpty($marks[0]);

        foreach ($marks[0] as $mark) {
            $this->assertStringContainsString('aria-hidden="true"', $mark);
        }
    }

    public function test_the_carve_out_is_written_down_and_scoped(): void
    {
        $claude = (string) file_get_contents(base_path('CLAUDE.md'));

        $this->assertStringContainsString('One carve-out: third-party brand marks', $claude);
        $this->assertStringContainsString(
            'may use those values for anything of its own',
            $claude,
            'The carve-out is not scoped, so it reads as permission to add colours.',
        );

        // And the marks are in the licence register with their guideline.
        $register = (string) file_get_contents(base_path('docs/LICENCES.csv'));

        foreach (['Brand mark: X', 'Brand mark: LinkedIn', 'Brand mark: Facebook', 'Brand mark: YouTube'] as $row) {
            $this->assertStringContainsString($row, $register);
        }
    }

    /* CRASA on social media (29 October 2026) ----------------------------- */

    public function test_the_handle_is_read_off_the_configured_url(): void
    {
        $this->assertSame('@CRASA_ORG', SocialAccounts::handle('x', 'https://x.com/CRASA_ORG'));
        $this->assertSame('CRASA', SocialAccounts::handle('facebook', 'https://www.facebook.com/people/CRASA/100064573337016/'));
        $this->assertSame('crasa-secretariat', SocialAccounts::handle('linkedin', 'https://www.linkedin.com/company/crasa-secretariat/'));
    }

    public function test_the_timeline_addresses_the_page_by_the_id_in_its_url(): void
    {
        // The page plugin renders nothing for the /people/ form.
        $this->assertSame('https://www.facebook.com/100064573337016', SocialAccounts::facebookPluginHref('https://www.facebook.com/people/CRASA/100064573337016/'));
        $this->assertSame('https://www.facebook.com/crasa', SocialAccounts::facebookPluginHref('https://www.facebook.com/crasa'));
    }

    public function test_the_homepage_names_the_section_and_shows_each_channel_with_its_handle_and_no_embed(): void
    {
        foreach (['en' => 'CRASA on social media', 'fr' => 'La CRASA sur les réseaux sociaux', 'pt' => 'A CRASA nas redes sociais'] as $locale => $heading) {
            $html = $this->get("/{$locale}")->assertOk()->getContent();

            $this->assertStringContainsString($heading, $html);
            // The footer keeps its own "Follow us" label; the section does not.
            $this->assertStringNotContainsString('class="b__heading">Follow us<', $html);
            $this->assertStringContainsString('@CRASA_ORG', $html);
            $this->assertStringContainsString('c-socials__handle', $html);
            // The homepage loads nothing from Facebook.
            $this->assertStringNotContainsString('data-fb-timeline', $html);
            $this->assertStringNotContainsString('facebook.com/plugins', $html);
        }
    }

    /**
     * The Facebook page box rendered empty on the newsroom and is gone (C13,
     * 29 October 2026). The posts themselves come from the newsroom feed
     * partial, which carries its own tests.
     */
    public function test_the_newsroom_has_no_empty_facebook_page_box(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $html = $this->get("/{$locale}/newsroom")->assertOk()->getContent();

            $this->assertStringNotContainsString('data-fb-timeline', $html);
            $this->assertStringNotContainsString('plugins/page.php', $html);
            $this->assertStringNotContainsString('<iframe', $html);
        }

        $this->assertStringContainsString(
            "@includeIf('partials.newsroom.social')",
            file_get_contents(resource_path('views/pages/newsroom/index.blade.php'))
        );
    }

    public function test_the_policy_frames_facebook_and_nothing_else(): void
    {
        $policy = $this->get('/en/newsroom')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('frame-src https://www.facebook.com;', $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertSame(1, substr_count($policy, 'frame-src'));
    }
}
