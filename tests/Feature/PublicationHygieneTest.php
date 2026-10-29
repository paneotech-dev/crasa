<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * INC-04, INC-03, INC-01, INC-02, UX-07, UX-14, PE-T09: publications are
 * published with a file or a link, undated ones say so, and the news list
 * reads newest first with real categories.
 */
class PublicationHygieneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->admin = User::factory()->administrator()->create();
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'publication_type' => 'guideline',
            'published_on' => '2026-03-01',
            'is_public' => 1,
            'en' => ['title' => 'Postal test guideline', 'summary' => 'A test.'],
        ];
    }

    public function test_a_publication_without_a_file_or_link_cannot_be_published_from_the_form(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/data/publications', $this->form())
            ->assertSessionHasErrors('is_public');
        $this->assertSame(0, Publication::count());

        // Hidden, it may be saved.
        $this->actingAs($this->admin)->post('/en/admin/data/publications', $this->form(['is_public' => 0]))
            ->assertSessionHasNoErrors();
        $publication = Publication::sole();

        // Published later: still refused without a file.
        $this->actingAs($this->admin)->put("/en/admin/data/publications/{$publication->id}", $this->form())
            ->assertSessionHasErrors('is_public');
        $this->assertFalse($publication->fresh()->is_public);

        // With a file it goes through.
        Document::factory()->create(['documentable_type' => $publication->getMorphClass(), 'documentable_id' => $publication->id]);
        $this->actingAs($this->admin)->put("/en/admin/data/publications/{$publication->id}", $this->form())
            ->assertSessionHasNoErrors();
        $this->assertTrue($publication->fresh()->is_public);
    }

    public function test_an_external_link_may_be_published_without_a_file(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/data/publications', $this->form(['external_url' => 'https://example.org/guideline']))
            ->assertSessionHasNoErrors();

        $publication = Publication::sole();
        $this->assertTrue($publication->is_public);

        $this->get('/en/publications/'.$publication->slug)->assertOk()
            ->assertSee('href="https://example.org/guideline"', false);
    }

    public function test_the_model_refuses_to_publish_without_a_file_or_link(): void
    {
        $publication = Publication::factory()->unlisted()->create();

        $this->expectException(ValidationException::class);
        $publication->update(['is_public' => true]);
    }

    public function test_an_undated_publication_says_so_and_adds_no_year(): void
    {
        $dated = Publication::factory()->create(['published_on' => '2019-05-01']);
        $undated = Publication::factory()->create(['published_on' => null]);
        $undated->setTranslation('title', 'en', 'Undated guideline');
        $dated->setTranslation('title', 'en', 'Dated guideline');

        $response = $this->get('/en/publications')->assertOk()->assertSee('Date not recorded');
        // The year filter offers only real years (PE-T09).
        $this->assertSame(['2019'], collect($response->viewData('years'))->all());

        $this->get('/en/publications/'.$undated->slug)->assertOk()->assertSee('Date not recorded');
        $this->get('/fr/publications/'.$undated->slug)->assertOk()->assertSee('Date non enregistrée');
        $this->get('/pt/publications/'.$undated->slug)->assertOk()->assertSee('Data não registada');
        $this->get('/en/publications?year[]=2019')->assertOk()->assertDontSee('Undated guideline');
        $this->get('/en/search?q=Undated')->assertOk();

        $this->actingAs($this->admin)->get('/en/admin/data/publications')->assertOk()->assertSee('Date not recorded');
    }

    public function test_the_tidy_migration_removes_the_test_and_empties_placeholder_dates(): void
    {
        $test = Publication::factory()->create(['slug' => 'test-of-publication', 'published_on' => '2026-10-08']);
        $test->setTranslation('title', 'en', 'Test of Publication');
        $placeholder = Publication::factory()->create(['slug' => 'guidelines-on-tariff-for-sadc-countries', 'published_on' => '2000-01-01']);
        $edited = Publication::factory()->create(['slug' => 'guidelines-on-interconnection-for-sadc-countries', 'published_on' => '2000-03-15']);

        $migration = require database_path('migrations/2026_10_29_030100_tidy_publications_for_the_workshop.php');
        $migration->up();
        $migration->up();

        $this->assertNull(Publication::withTrashed()->where('slug', 'test-of-publication')->first());
        $this->assertSame(0, DB::table('translations')->where('translatable_type', $test->getMorphClass())->where('translatable_id', $test->id)->count());
        $this->assertNull($placeholder->fresh()->published_on);
        // A date somebody has since entered is not the placeholder and stays.
        $this->assertSame('2000-03-15', $edited->fresh()->published_on->toDateString());

        $migration->down();
        $this->assertSame('2000-01-01', $placeholder->fresh()->published_on->toDateString());
    }

    public function test_the_news_list_is_newest_first_with_real_categories_and_filters(): void
    {
        NewsItem::factory()->create(['slug' => 'old-item', 'category' => 'news', 'status' => 'published', 'published_at' => '2022-06-07 10:00:00']);
        NewsItem::factory()->create(['slug' => 'new-item', 'category' => 'press_release', 'status' => 'published', 'published_at' => '2026-10-20 10:00:00']);
        NewsItem::factory()->create(['slug' => 'draft-item', 'category' => 'tender', 'status' => 'draft', 'published_at' => null]);

        $html = $this->actingAs($this->admin)->get('/en/admin/data/news')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, '7 Jun 2022'), strpos($html, '20 Oct 2026'), 'Newest first.');
        $this->assertStringContainsString(__('news.category_press_release'), $html);
        $this->assertStringNotContainsString('>press_release<', $html);
        $this->assertStringContainsString('name="category"', $html);
        $this->assertStringContainsString('name="year"', $html);
        $this->assertStringContainsString('name="status"', $html);

        $filtered = $this->actingAs($this->admin)->get('/en/admin/data/news?year=2022')->assertOk()->getContent();
        $this->assertStringContainsString('7 Jun 2022', $filtered);
        $this->assertStringNotContainsString('20 Oct 2026', $filtered);

        $this->actingAs($this->admin)->get('/en/admin/data/news?status=draft')->assertOk()
            ->assertDontSee('20 Oct 2026')->assertSee('Date not recorded');

        // A value that is not an option is ignored, not passed to the query.
        $this->actingAs($this->admin)->get('/en/admin/data/news?category=nonsense')->assertOk()->assertSee('20 Oct 2026');
    }

    /** UX-14: the visibility tick says what it does, required is a word, the error sits by its field. */
    public function test_the_publication_form_labels_visibility_and_marks_required_in_words(): void
    {
        $html = $this->actingAs($this->admin)->get('/en/admin/data/publications/new')->assertOk()->getContent();
        $this->assertStringContainsString('Visible on the public site', $html);
        $this->assertStringContainsString('listed in the public library', $html);
        $this->assertMatchesRegularExpression('/<span class="a-required">Required<\/span>/', $html);

        $this->actingAs($this->admin)->from('/en/admin/data/publications/new')
            ->post('/en/admin/data/publications', $this->form())->assertRedirect('/en/admin/data/publications/new');
        $after = $this->actingAs($this->admin)->get('/en/admin/data/publications/new')->getContent();
        $this->assertMatchesRegularExpression('/id="f-is_public".*?<p class="c-field__error" id="e-is_public">/s', $after);
    }
}
