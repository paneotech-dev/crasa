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
}
