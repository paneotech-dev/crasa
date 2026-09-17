<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A legacy page that states no date is shown as having none.
 *
 * The import stored the day it read the site. Printed, that put twelve old
 * articles at the top of the newsroom dated 16 September 2026 and two
 * meetings on the calendar on a day they did not meet.
 */
class UndatedLegacyContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        Carbon::setTestNow('2026-09-17 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function news(string $slug, string $title, string $date, bool $unknown): NewsItem
    {
        $item = NewsItem::factory()->create([
            'slug' => $slug, 'status' => 'published', 'published_at' => $date,
            'date_unknown' => $unknown, 'is_sample' => false,
        ]);
        $item->setTranslation('title', 'en', $title);

        return $item;
    }

    private function meeting(string $slug, string $date, bool $unknown): Meeting
    {
        return Meeting::factory()->create([
            'slug' => $slug, 'title' => ucfirst(str_replace('-', ' ', $slug)), 'starts_on' => $date,
            'date_unknown' => $unknown, 'clearance' => 'public', 'is_sample' => false,
        ]);
    }

    public function test_an_undated_article_says_so_and_sorts_after_the_dated_ones(): void
    {
        $this->news('dated-older', 'Dated older article', '2025-03-01', false);
        $this->news('undated', 'Undated article', '2026-09-16 23:09:00', true);

        $html = $this->get('/en/newsroom')->assertOk()->getContent();

        $this->assertStringNotContainsString('16 September 2026', $html);
        $this->assertStringContainsString('Date not stated', $html);
        $this->assertLessThan(strpos($html, 'Undated article'), strpos($html, 'Dated older article'));

        $this->get('/en/newsroom/undated')->assertOk()
            ->assertSee('Date not stated')
            ->assertDontSee('16 September 2026');

        $this->get('/fr/newsroom/undated')->assertOk()->assertSee('Date non précisée');
        $this->get('/pt/newsroom/undated')->assertOk()->assertSee('Data não indicada');
    }

    public function test_an_undated_meeting_is_never_upcoming_and_never_open_for_registration(): void
    {
        $this->meeting('undated-future-looking', '2026-12-01', true);
        $this->meeting('real-upcoming', '2026-11-20', false);

        $index = $this->get('/en/events')->assertOk()->getContent();
        $upcoming = substr($index, 0, (int) strpos($index, 'Undated future looking'));

        $this->assertStringContainsString('Real upcoming', $upcoming, 'The dated meeting is listed before the undated one.');
        $this->assertStringContainsString('Date not stated', $index);
        $this->assertStringNotContainsString('1 December 2026', $index);

        $this->get('/en/events/undated-future-looking')->assertOk()
            ->assertSee('Date not stated')
            ->assertSee(__('delegates.closed'));

        $this->get('/en/events/undated-future-looking/register')->assertStatus(410);
        $this->assertSame(1, Meeting::upcoming()->count());
    }

    public function test_the_extranet_calendar_does_not_place_an_undated_meeting_on_a_day(): void
    {
        $this->meeting('undated-in-september', '2026-09-16', true);
        $this->meeting('dated-in-september', '2026-09-02', false);

        $user = User::factory()->administrator()->create();
        $html = $this->actingAs($user)->get('/en/extranet/calendar?month=2026-09')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Dated in september') >= 1 ? 1 : 0);
        $this->assertSame(0, Meeting::dated()->where('slug', 'undated-in-september')->count());

        $ics = $this->actingAs($user)->get('/en/extranet/calendar.ics?month=2026-09');
        if ($ics->status() === 200) {
            $this->assertStringNotContainsString('Undated in september', $ics->getContent());
        }
    }

    public function test_the_migration_flags_crawl_dated_rows_and_leaves_page_dated_ones(): void
    {
        $undatedNews = $this->news('n1', 'N1', '2026-09-16 23:09:00', false);
        $datedNews = $this->news('n2', 'N2', '2024-05-01', false);
        $undatedMeeting = $this->meeting('m1', '2026-09-16', false);
        $eventDated = $this->meeting('m2', '2026-04-13', false);

        foreach ([[$undatedNews, 'crawl'], [$datedNews, 'page'], [$undatedMeeting, 'crawl'], [$eventDated, 'crawl']] as $i => [$model, $source]) {
            DB::table('legacy_records')->insert([
                'legacy_url' => 'https://www.crasa.org/post-articles/x'.$i,
                'legacy_path' => '/post-articles/x'.$i,
                'recordable_type' => $model::class,
                'recordable_id' => $model->id,
                'needs_review' => $source === 'crawl',
                'date_source' => $source,
                'imported_at' => '2026-09-16 23:09:49',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_09_17_060000_add_date_unknown_to_news_and_meetings.php');
        $migration->down();
        $migration->up();

        $this->assertTrue($undatedNews->fresh()->date_unknown);
        $this->assertFalse($datedNews->fresh()->date_unknown);
        $this->assertTrue($undatedMeeting->fresh()->date_unknown);
        $this->assertFalse($eventDated->fresh()->date_unknown, 'A meeting whose page gave event dates keeps them.');
    }

    public function test_the_import_flags_an_undated_page_and_keeps_event_dates(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('legacy/content.json', json_encode(['pages' => [
            'https://www.crasa.org/post-articles/undated-committee-meeting' => [
                'title' => '1ST MEETING OF A COMMITTEE', 'summary' => '', 'body_html' => '<p>Text.</p>',
                'category' => 'Events', 'date_text' => null, 'hero_image' => null, 'documents' => [],
                'images' => [], 'event_dates' => ['start' => null, 'end' => null],
            ],
            'https://www.crasa.org/post-articles/summit-with-a-date' => [
                'title' => 'Summit with a date', 'summary' => 'Summit', 'body_html' => '<p>Text.</p>',
                'category' => 'Events', 'date_text' => null, 'hero_image' => null, 'documents' => [],
                'images' => [], 'event_dates' => ['start' => 'April 13, 2026', 'end' => null],
            ],
            'https://www.crasa.org/post-articles/undated-news' => [
                'title' => 'An undated news article', 'summary' => '', 'body_html' => '<p>Text.</p>',
                'category' => 'CRASA News', 'date_text' => null, 'hero_image' => null, 'documents' => [],
                'images' => [], 'event_dates' => ['start' => null, 'end' => null],
            ],
        ]]));

        $this->artisan('legacy:map')->assertSuccessful();

        $undated = Meeting::where('title', 'like', '1st Meeting%')->sole();
        $summit = Meeting::where('title', 'Summit with a date')->sole();

        $this->assertTrue($undated->date_unknown);
        $this->assertFalse($summit->date_unknown);
        $this->assertSame('2026-04-13', $summit->starts_on->toDateString());
        $this->assertSame('page', DB::table('legacy_records')->where('recordable_id', $summit->id)->where('recordable_type', Meeting::class)->value('date_source'));
        $this->assertTrue(NewsItem::whereHas('translations', fn ($q) => $q->where('value', 'An undated news article'))->sole()->date_unknown);
    }
}
