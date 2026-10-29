<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UX-03: the area switcher at the top of the sidebar offers the areas the
 * account may enter, and an administrator in the member area has a way back.
 */
class AreaSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
    }

    private function areas(string $html): array
    {
        preg_match('/<ul class="a-side__areas".*?<\/ul>/s', $html, $m);
        preg_match_all('/data-area="(\w+)"/', $m[0] ?? '', $keys);

        return $keys[1];
    }

    public function test_an_administrator_can_switch_both_ways(): void
    {
        $admin = User::factory()->administrator()->create();

        $member = $this->actingAs($admin)->get('/en/extranet/calendar')->assertOk()->getContent();
        $this->assertSame(['admin', 'member', 'public'], $this->areas($member));
        $this->assertStringContainsString('href="'.url('/en/admin').'" data-area="admin"', $member);
        $this->assertMatchesRegularExpression('/is-current"\s*href="[^"]+" data-area="member"/', $member);

        $back = $this->actingAs($admin)->get('/en/admin')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/is-current"\s*href="[^"]+" data-area="admin"/', $back);
    }

    public function test_a_representative_sees_only_the_member_area_and_the_public_site(): void
    {
        $rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();

        $html = $this->actingAs($rep)->get('/en/extranet/calendar')->assertOk()->getContent();
        $this->assertSame(['member', 'public'], $this->areas($html));
        $this->actingAs($rep)->get('/pt/extranet/calendar')->assertOk()->assertSee('Sítio público');
    }

    public function test_an_editor_sees_administration_and_the_public_site(): void
    {
        $editor = User::factory()->editor()->create();
        $html = $this->actingAs($editor)->get('/en/admin')->assertOk()->getContent();

        $this->assertContains('admin', $this->areas($html));
        $this->assertContains('public', $this->areas($html));
    }

    /**
     * UX-15, UX-17: Sign out is a word, and help reaches the guide for the
     * role. Since Day 3 (client brief item 7) the help icon opens the help
     * page for the current screen, and that page links to the role guide in
     * a new tab.
     */
    public function test_sign_out_is_labelled_and_help_opens_the_role_guide(): void
    {
        $rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();
        $html = $this->actingAs($rep)->get('/en/extranet/calendar')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="a-side__out">.*?<span>Sign out<\/span>/s', $html);
        $this->assertStringContainsString('href="'.url('/en/help/extranet.calendar'), $html);

        $help = $this->actingAs($rep)->get('/en/help/extranet.calendar')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.config('crasa.role_guide_url').'#member"', $help);
        $this->assertMatchesRegularExpression('/#member"\s*target="_blank" rel="noopener"/', $help);

        $admin = User::factory()->administrator()->create();
        $this->assertStringContainsString(config('crasa.role_guide_url').'#admin', $this->actingAs($admin)->get('/en/help/admin.dashboard')->getContent());
    }
}
