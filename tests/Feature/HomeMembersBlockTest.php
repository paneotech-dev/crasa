<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Country;
use App\Models\Page;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The homepage members block and the members page standfirst, as changed
 * on 29 October 2026: the intro once, the island members on the small map,
 * and two member regulators working in French rather than three.
 */
class HomeMembersBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);

        foreach (Country::all() as $country) {
            Authority::factory()->create(['country_id' => $country->id]);
        }
    }

    private function membersSection(string $html): string
    {
        $at = strpos($html, 'b-members__summary');
        $this->assertNotFalse($at, 'The homepage has no members summary.');
        $start = strrpos(substr($html, 0, $at), '<section');
        $end = strpos($html, '</section>', $at);

        return substr($html, $start, $end - $start);
    }

    public function test_the_members_standfirst_says_two_work_in_french(): void
    {
        $this->get('/en/members')->assertOk()->assertSee('Two work in Portuguese, two in French, and Mauritius in both English and French')->assertDontSee('Four work in Portuguese');
        $this->get('/fr/members')->assertOk()->assertSee('deux en français', false);
        $this->get('/pt/members')->assertOk()->assertSee('dois em francês', false);
    }

    public function test_the_migration_corrects_old_wording_and_reverses(): void
    {
        $page = Page::where('slug', 'members')->firstOrFail();
        $row = fn (string $locale) => DB::table('translations')
            ->where('translatable_type', Page::class)->where('translatable_id', $page->id)
            ->where('locale', $locale)->where('field', 'standfirst');

        $row('en')->update(['value' => 'Fourteen states. Four work in Portuguese, three in French, and so on.']);
        $row('pt')->update(['value' => 'Catorze. Quatro trabalham em português, três em francês, e assim.']);

        $migration = require database_path('migrations/2026_10_29_023000_members_standfirst_two_in_french.php');
        $migration->up();
        $migration->up();

        $this->assertSame('Fourteen states. Four work in Portuguese, two in French, and so on.', $row('en')->value('value'));
        $this->assertSame('Catorze. Quatro trabalham em português, dois em francês, e assim.', $row('pt')->value('value'));

        $migration->down();
        $this->assertSame('Fourteen states. Four work in Portuguese, three in French, and so on.', $row('en')->value('value'));
    }
}
