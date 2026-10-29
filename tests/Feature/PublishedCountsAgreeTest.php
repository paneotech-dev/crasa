<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\User;
use App\Support\PublishedCounts;
use App\Support\SectionCounts;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * INC-10, PE-T08: the home page, the publications page, the members page
 * and the dashboard state the same number, and it counts published items
 * only.
 */
class PublishedCountsAgreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_surface_counts_the_same_published_publications(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $committee = Committee::first();

        Publication::factory()->count(3)->create(['is_public' => true, 'committee_id' => $committee->id, 'is_sample' => false]);
        Publication::factory()->count(2)->unlisted()->create(['committee_id' => $committee->id, 'is_sample' => false]);
        NewsItem::factory()->count(2)->create(['status' => 'published', 'published_at' => now()->subDay()]);
        NewsItem::factory()->create(['status' => 'draft', 'published_at' => null]);

        $this->assertSame(3, PublishedCounts::publications());
        $this->assertSame(2, PublishedCounts::news());

        // The publications page.
        $this->assertSame(3, $this->get('/en/publications')->assertOk()->viewData('total'));

        // The section counts the public pages state.
        $this->assertSame('3', SectionCounts::publications()[0]['value']);

        // The dashboard tile.
        $tiles = $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin')->assertOk()->viewData('tiles');
        $this->assertSame(3, $tiles[0]['n']);
        $this->assertSame(2, $tiles[1]['n']);

        // A committee card counts only what a reader can open.
        $card = \App\Models\Committee::withCount(['publications' => PublishedCounts::listedScope()])->find($committee->id);
        $this->assertSame(3, $card->publications_count);
    }
}
