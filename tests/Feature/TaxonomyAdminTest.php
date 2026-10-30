<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\NewsCategory;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\PublicationType;
use App\Models\Sector;
use App\Models\Tag;
use App\Models\User;
use App\Support\BadgeColours;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Content > Taxonomies (Day 3, taxo): news categories, publication types,
 * sectors and tags, and who may change them.
 */
class TaxonomyAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SectorSeeder::class);
        $this->admin = User::factory()->administrator()->create();
    }

    private function names(string $en, string $fr, string $pt): array
    {
        return ['en' => $en, 'fr' => $fr, 'pt' => $pt];
    }

    public function test_every_list_and_form_opens_for_an_administrator(): void
    {
        foreach (['categories', 'publication-types', 'sectors', 'tags'] as $kind) {
            $this->actingAs($this->admin)->get('/en/admin/taxonomies/'.$kind)->assertOk();
            $this->actingAs($this->admin)->get('/fr/admin/taxonomies/'.$kind.'/new')->assertOk();
        }

        $this->actingAs($this->admin)->get('/en/admin/taxonomies')->assertOk()->assertSee('Press release');
        $this->actingAs($this->admin)->get('/en/admin/taxonomies/categories/'.NewsCategory::bySlug('tender')->id)->assertOk()->assertSee('Appel d&#039;offres', false);
        $this->actingAs($this->admin)->get('/en/admin/taxonomies/sectors/'.Sector::where('slug', 'postal')->value('id'))->assertOk()->assertSee('Services postaux');
    }

    public function test_editors_and_other_roles_are_refused_on_every_action(): void
    {
        // Signed out: sent to sign in.
        $this->get('/en/admin/taxonomies')->assertRedirect();

        $category = NewsCategory::bySlug('news');

        foreach (['editor', 'viewer', 'moderator', 'auditor', 'memberStateRep'] as $state) {
            $user = User::factory()->{$state}()->create();

            $this->actingAs($user)->get('/en/admin/taxonomies')->assertForbidden();
            $this->actingAs($user)->get('/en/admin/taxonomies/categories/new')->assertForbidden();
            $this->actingAs($user)->post('/en/admin/taxonomies/categories', [])->assertForbidden();
            $this->actingAs($user)->get('/en/admin/taxonomies/categories/'.$category->id)->assertForbidden();
            $this->actingAs($user)->put('/en/admin/taxonomies/categories/'.$category->id, [])->assertForbidden();
            $this->actingAs($user)->delete('/en/admin/taxonomies/categories/'.$category->id)->assertForbidden();
        }

        $this->assertDatabaseHas('news_categories', ['slug' => 'news']);
        $this->actingAs($this->admin)->get('/en/admin/taxonomies/colours')->assertNotFound();
        $this->actingAs($this->admin)->get('/en/admin/taxonomies/categories/999999')->assertNotFound();
    }

    public function test_a_new_category_is_offered_on_the_news_form_and_in_the_public_filter(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/taxonomies/categories', [
            'name' => $this->names('Vacancy', 'Offre d\'emploi', 'Vaga'),
            'slug' => 'vacancy',
            'sort_order' => 6,
            'colour' => 'green',
        ])->assertRedirect('/en/admin/taxonomies/categories')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('news_categories', ['slug' => 'vacancy', 'sort_order' => 6, 'colour' => 'green']);
        $this->assertTrue(AuditLog::where('auditable_type', NewsCategory::class)->exists());

        $this->actingAs($this->admin)->get('/en/admin/data/news/new')->assertOk()->assertSee('value="vacancy"', false);

        $item = NewsItem::factory()->create(['category' => 'vacancy']);
        $this->get('/pt/newsroom')->assertOk()->assertSee('value="vacancy"', false)->assertSee('Vaga');
        $this->get('/fr/newsroom/'.$item->slug)->assertOk()->assertSee('Offre d&#039;emploi', false);
    }

    public function test_renaming_and_reordering_change_the_public_filter(): void
    {
        NewsItem::factory()->create(['category' => 'news']);
        NewsItem::factory()->create(['category' => 'tender']);
        $tender = NewsCategory::bySlug('tender');

        $this->actingAs($this->admin)->put('/en/admin/taxonomies/categories/'.$tender->id, [
            'name' => $this->names('Call for tenders', 'Appel d\'offres', 'Concurso'),
            'slug' => 'tender',
            'sort_order' => 0,
            'colour' => 'amber',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $html = $this->get('/en/newsroom')->assertOk()->getContent();
        $this->assertStringContainsString('Call for tenders', $html);
        $this->assertLessThan(strpos($html, 'value="news"'), strpos($html, 'value="tender"'));
    }

    public function test_changing_a_slug_carries_the_items_with_it(): void
    {
        $item = NewsItem::factory()->create(['category' => 'consultation']);
        $category = NewsCategory::bySlug('consultation');

        $this->actingAs($this->admin)->put('/en/admin/taxonomies/categories/'.$category->id, [
            'name' => $this->names('Public consultation', 'Consultation publique', 'Consulta pública'),
            'slug' => 'public_consultation',
            'sort_order' => 3,
            'colour' => 'purple',
        ])->assertSessionHasNoErrors();

        $this->assertSame('public_consultation', $item->fresh()->categorySlug());
    }

    public function test_delete_is_refused_while_in_use_and_names_the_count(): void
    {
        NewsItem::factory()->count(3)->create(['category' => 'tender']);
        $tender = NewsCategory::bySlug('tender');

        $this->actingAs($this->admin)->get('/en/admin/taxonomies/categories/'.$tender->id)
            ->assertOk()->assertSee('Tender is used by 3 items and cannot be deleted.');

        $this->actingAs($this->admin)
            ->from('/en/admin/taxonomies/categories/'.$tender->id)
            ->delete('/en/admin/taxonomies/categories/'.$tender->id)
            ->assertRedirect('/en/admin/taxonomies/categories/'.$tender->id)
            ->assertSessionHasErrors(['term' => 'Tender is used by 3 items and cannot be deleted. Move those items to another entry first.']);
        $this->assertDatabaseHas('news_categories', ['slug' => 'tender']);

        Publication::factory()->create(['publication_type' => 'minutes']);
        $minutes = PublicationType::bySlug('minutes');
        $this->actingAs($this->admin)->delete('/en/admin/taxonomies/publication-types/'.$minutes->id)
            ->assertSessionHasErrors(['term' => 'Minutes is used by 1 item and cannot be deleted. Move that item to another entry first.']);

        $postal = Sector::where('slug', 'postal')->first();
        NewsItem::factory()->create(['sector_id' => $postal->id]);
        $this->actingAs($this->admin)->delete('/en/admin/taxonomies/sectors/'.$postal->id)->assertSessionHasErrors('term');
        $this->assertDatabaseHas('sectors', ['slug' => 'postal']);
    }

    public function test_an_unused_entry_is_deleted_with_its_names(): void
    {
        $communique = NewsCategory::bySlug('communique');

        $this->actingAs($this->admin)->delete('/en/admin/taxonomies/categories/'.$communique->id)
            ->assertRedirect('/en/admin/taxonomies/categories')->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('news_categories', ['slug' => 'communique']);
        $this->assertDatabaseMissing('translations', ['translatable_type' => NewsCategory::class, 'translatable_id' => $communique->id]);

        $tag = Tag::factory()->create(['slug' => 'roaming']);
        $this->actingAs($this->admin)->delete('/en/admin/taxonomies/tags/'.$tag->id)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('tags', ['slug' => 'roaming']);
    }

    public function test_names_in_three_languages_a_valid_slug_and_a_palette_colour_are_required(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/taxonomies/categories', [
            'name' => ['en' => 'Vacancy', 'fr' => '', 'pt' => ''],
            'slug' => 'Not A Slug',
            'sort_order' => 1,
            'colour' => '#8e51ff',
        ])->assertSessionHasErrors(['name.fr', 'name.pt', 'slug', 'colour']);

        $this->actingAs($this->admin)->post('/en/admin/taxonomies/categories', [
            'name' => $this->names('News', 'Actualité', 'Notícia'),
            'slug' => 'news',
            'sort_order' => 1,
            'colour' => 'navy',
        ])->assertSessionHasErrors('slug');

        $this->assertSame(5, NewsCategory::count());
    }

    public function test_every_badge_colour_is_an_existing_token_pair_at_4_5_to_1(): void
    {
        $tokens = file_get_contents(public_path('css/tokens.css'));

        foreach (BadgeColours::PAIRS as $key => [$groundToken, $groundHex, $textToken, $textHex]) {
            $this->assertMatchesRegularExpression('/'.preg_quote($groundToken, '/').':'.preg_quote($groundHex, '/').'/i', $tokens, $key.' ground');
            $this->assertMatchesRegularExpression('/'.preg_quote($textToken, '/').':'.preg_quote($textHex, '/').'/i', $tokens, $key.' text');
            $this->assertGreaterThanOrEqual(4.5, BadgeColours::contrast($key), $key);
        }

        $this->assertSame(array_keys(BadgeColours::PAIRS), BadgeColours::keys());
    }

    public function test_a_sector_s_french_name_is_edited_here_and_its_slug_stays(): void
    {
        $postal = Sector::where('slug', 'postal')->first();
        NewsItem::factory()->create(['sector_id' => $postal->id]);

        $this->actingAs($this->admin)->put('/en/admin/taxonomies/sectors/'.$postal->id, [
            'name' => $this->names('Postal', 'Poste', 'Correios'),
            'slug' => 'changed',
            'sort_order' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('postal', $postal->fresh()->slug);
        $this->get('/fr/newsroom')->assertOk()->assertSee('Poste');
        $this->get('/pt/newsroom')->assertOk()->assertSee('Correios');
        $this->get('/en/newsroom')->assertOk()->assertSee('Postal');
    }

    public function test_the_sidebar_shows_taxonomies_to_administrators_only(): void
    {
        $this->actingAs($this->admin)->get('/en/admin')->assertSee('/en/admin/taxonomies', false);
        $this->actingAs(User::factory()->editor()->create())->get('/en/admin')->assertDontSee('/en/admin/taxonomies', false);
    }
}
