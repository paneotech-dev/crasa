<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The public utility bar and the phone drawer name the back office once,
 * whatever the role and the language. A back-office reader's workspace link
 * is that link; it used to sit beside a second one with the same label.
 */
class UtilityBarBackOfficeOnceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(HeaderMenuSeeder::class);
        config(['crasa.two_factor_required' => false]);
    }

    /** @return array{bar: int, drawer: int} */
    private function counts(string $html, string $label): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $x = new \DOMXPath($dom);
        $quoted = '"'.$label.'"';

        return [
            'bar' => $x->query('//div[contains(@class, "k-ubar")]//a[normalize-space()='.$quoted.']')->length,
            'drawer' => $x->query('//div[contains(@class, "k-d-btns")]//a[normalize-space()='.$quoted.']')->length,
        ];
    }

    public function test_a_moderator_and_an_administrator_see_the_back_office_once_in_each_language(): void
    {
        $readers = ['moderator' => User::factory()->moderator()->create(), 'administrator' => User::factory()->administrator()->create()];
        $labels = ['en' => 'Back office', 'fr' => __('nav.back_office', [], 'fr'), 'pt' => 'Administração'];

        foreach ($readers as $role => $user) {
            foreach ($labels as $locale => $label) {
                $html = $this->actingAs($user)->get("/{$locale}")->assertOk()->getContent();
                $this->assertSame(['bar' => 1, 'drawer' => 1], $this->counts($html, $label), "{$role} in {$locale}");
            }
        }
    }

    public function test_a_visitor_and_a_committee_member_keep_the_back_office_link_once(): void
    {
        $this->assertSame(['bar' => 1, 'drawer' => 1], $this->counts($this->get('/en')->assertOk()->getContent(), 'Back office'));

        $member = User::factory()->committeeMember()->create();
        $html = $this->actingAs($member)->get('/pt')->assertOk()->getContent();
        $this->assertSame(['bar' => 1, 'drawer' => 1], $this->counts($html, 'Administração'));
    }
}
