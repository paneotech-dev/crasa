<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Page;
use App\Models\Publication;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UX-05, UX-06, TR-03: the Pages and Publications lists carry status, the
 * state of each language (missing, machine translated, reviewed, as a word
 * and an icon), whether there is a file, and when the record was updated.
 */
class AdminListColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_publications_list_shows_languages_file_and_updated(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $admin = User::factory()->administrator()->create();

        $publication = Publication::factory()->create(['published_on' => '2021-03-01']);
        $publication->setTranslation('title', 'en', 'Roaming guideline');
        $publication->setTranslation('title', 'fr', 'Ligne directrice');
        DB::table('translations')->where('translatable_id', $publication->id)->where('locale', 'fr')->update(['is_machine_draft' => true]);
        Document::factory()->create(['documentable_type' => $publication->getMorphClass(), 'documentable_id' => $publication->id]);

        $html = $this->actingAs($admin)->get('/en/admin/data/publications')->assertOk()->getContent();

        foreach (['Languages', 'File', 'Updated', 'Public site'] as $header) {
            $this->assertStringContainsString('>'.$header.'<', $html);
        }
        $this->assertMatchesRegularExpression('/data-state="reviewed">.*?EN.*?reviewed/s', $html);
        $this->assertMatchesRegularExpression('/data-state="machine">.*?FR.*?machine translated/s', $html);
        $this->assertMatchesRegularExpression('/data-state="missing">.*?PT.*?missing/s', $html);
        $this->assertStringContainsString('New publication', $html);
    }

    public function test_the_pages_list_shows_the_state_of_each_language(): void
    {
        $admin = User::factory()->administrator()->create();
        $page = Page::factory()->create();
        $page->setTranslation('title', 'en', 'About');

        $html = $this->actingAs($admin)->get('/en/admin/pages')->assertOk()->getContent();
        $this->assertStringContainsString('class="a-trstates"', $html);
        $this->assertMatchesRegularExpression('/data-state="missing">.*?FR.*?missing/s', $html);
    }
}
