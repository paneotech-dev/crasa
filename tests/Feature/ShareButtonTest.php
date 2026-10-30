<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Meeting;
use App\Models\Project;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PartnerPageSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The breadcrumb and share row under the header is gone (30 October 2026,
 * client request: it took space). One Share button sits under the title of
 * each content page: news article, publication, event, project, theme.
 * Not on the home page, not on list pages.
 */
class ShareButtonTest extends TestCase
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
        $this->seed(PartnerPageSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(PublicationSeeder::class);

        Meeting::create([
            'title' => 'Share meeting', 'slug' => 'share-meeting',
            'starts_on' => '2026-11-10', 'ends_on' => '2026-11-12',
            'location' => 'Gaborone, Botswana', 'clearance' => Clearance::Public, 'committee_code' => 'PC',
        ]);
        $project = Project::create(['slug' => 'share-project', 'state' => 'current', 'status' => 'published', 'published_at' => now()->subDay()]);
        $project->setTranslations('en', ['title' => 'Share project']);
    }

    private function html(string $path): string
    {
        return $this->get($path)->assertOk()->getContent();
    }

    private function theme(): string
    {
        return array_key_first(config('themes'));
    }

    public function test_no_page_shows_the_breadcrumb_row(): void
    {
        foreach (['/en', '/en/about', '/en/members', '/en/committees', '/en/newsroom', '/en/contact',
            '/en/partner-with-us', '/en/themes', '/en/themes/'.$this->theme(), '/en/newsroom/spectrum-committee-ninth-meeting',
            '/en/publications/sadc-roaming-guidelines-2019', '/en/events/share-meeting', '/fr/about', '/pt/about'] as $path) {
            $html = $this->html($path);
            $this->assertStringNotContainsString('k-crumb', $html, $path);
            $this->assertStringNotContainsString(__('chome.breadcrumb'), $html, $path);
        }
        $this->assertFileDoesNotExist(resource_path('views/partials/k/breadcrumb.blade.php'));
        $this->assertStringNotContainsString('.k-crumb', file_get_contents(public_path('css/k.css')));
    }

    public function test_content_pages_have_one_share_button_under_the_title(): void
    {
        foreach (['/en/newsroom/spectrum-committee-ninth-meeting', '/en/publications/sadc-roaming-guidelines-2019',
            '/en/events/share-meeting', '/en/projects/share-project', '/en/themes/'.$this->theme()] as $path) {
            $html = $this->html($path);
            $this->assertSame(1, substr_count($html, 'data-k-share '), $path);
            $this->assertSame(1, substr_count($html, 'data-k-share-live'), $path);
            // Inside main, after the page title.
            $this->assertMatchesRegularExpression('#<main\b.*?<h1\b.*?</h1>.*?class="k-share-row".*?</main>#s', $html, $path);
            $this->assertStringContainsString('aria-label="Share this page"', $html, $path);
            $this->assertStringContainsString('data-copied="Link copied"', $html, $path);
        }
    }

    public function test_the_share_labels_are_translated(): void
    {
        $fr = $this->html('/fr/themes/'.$this->theme());
        $this->assertStringContainsString('aria-label="Partager cette page"', $fr);
        $this->assertStringContainsString('data-copied="Lien copié"', $fr);
        $pt = $this->html('/pt/themes/'.$this->theme());
        $this->assertStringContainsString('aria-label="Partilhar esta página"', $pt);
        $this->assertStringContainsString('<span>Partilhar</span>', $pt);
    }

    public function test_home_and_list_pages_have_no_share_button(): void
    {
        foreach (['/en', '/en/about', '/en/newsroom', '/en/publications', '/en/events', '/en/projects', '/en/themes', '/en/contact'] as $path) {
            $this->assertStringNotContainsString('data-k-share', $this->html($path), $path);
        }
    }
}
