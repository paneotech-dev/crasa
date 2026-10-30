<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\MenuItem;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Publication;
use App\Models\Tag;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Translations that outlive their record.
 *
 * Polymorphic relations have no foreign key, so nothing cascades. A purge
 * rehearsal on 9 September left 337 orphans; four reseeds the same afternoon
 * made another 525. Every one was invisible — nothing errored and no page
 * broke.
 *
 * THE FAILURE IS NOT UNTIDINESS. A translation is addressed by
 * translatable_type and translatable_id, and ids are reused.
 */
class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * THE ONE THAT MATTERS.
     *
     * Delete a publication, create another that takes its id, and the new
     * one must not inherit the old one's French title. That is a
     * demonstration string appearing under a real document, on a platform
     * whose whole claim is that its content is sourced.
     */
    public function test_a_record_reusing_a_freed_id_inherits_no_text(): void
    {
        $first = Publication::factory()->create(['slug' => 'the-first-one']);
        $first->setTranslations('fr', ['title' => 'Titre de demonstration']);
        $first->setTranslations('en', ['title' => 'Demonstration title']);

        $freedId = $first->id;
        $first->forceDelete();

        // Take the freed id, which is what happens on a reseed or after a
        // purge on a table whose sequence is reset.
        $second = new Publication([
            'slug' => 'the-second-one',
            'publication_type' => $first->publication_type,
            'published_on' => now(),
            'is_public' => true,
        ]);
        $second->id = $freedId;
        $second->save();

        $this->assertSame($freedId, $second->id);

        $this->assertNull(
            $second->fresh()->translate('title', 'fr'),
            'A new publication inherited the deleted one French title.'
        );
        $this->assertSame(
            0,
            DB::table('translations')->where('translatable_type', Publication::class)->count()
        );
    }

    /**
     * Every translatable model, not just the one that was noticed. A trait
     * fixed for one model and not the others is a fix that looks done.
     */
    public function test_deleting_any_translatable_model_removes_its_translations(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $cases = [
            'publication' => Publication::factory()->create(),
            'news item' => NewsItem::factory()->create(),
            'tag' => Tag::create(['slug' => 'a-tag']),
            'menu item' => MenuItem::create([
                'menu' => 'header', 'position' => 0,
                'destination_type' => 'route', 'destination' => 'contact',
            ]),
        ];

        foreach ($cases as $name => $model) {
            $model->setTranslations('fr', ['title' => 'Texte', 'label' => 'Libelle', 'name' => 'Nom']);

            $this->assertGreaterThan(0, $model->translations()->count(), $name.' saved no translation to test with.');

            method_exists($model, 'forceDelete') && in_array(
                SoftDeletes::class,
                class_uses_recursive($model),
                true
            ) ? $model->forceDelete() : $model->delete();

            $this->assertSame(
                0,
                DB::table('translations')
                    ->where('translatable_type', $model->getMorphClass())
                    ->where('translatable_id', $model->id)
                    ->count(),
                'Deleting a '.$name.' left its translations behind.'
            );
        }
    }

    /**
     * The hole the model hook does not cover.
     *
     * `Model::query()->delete()` and `$parent->children()->delete()` are mass
     * deletes: one DELETE, no model events, no hook. Every seeder that
     * cleared records this way orphaned their translations on every run, and
     * the platform reseeds often.
     */
    public function test_a_mass_delete_takes_its_translations_too(): void
    {
        $page = Page::factory()->create(['slug' => 'a-page', 'status' => 'published']);

        foreach (range(1, 3) as $position) {
            $block = PageBlock::create([
                'page_id' => $page->id,
                'block_type' => BlockType::RichText,
                'position' => $position,
                'settings' => [],
            ]);
            $block->setTranslations('en', ['body' => 'Block '.$position]);
        }

        $this->assertSame(3, DB::table('translations')->where('translatable_type', PageBlock::class)->count());

        // The scope the seeders use, rather than delete().
        $page->blocks()->purge();

        $this->assertSame(
            0,
            DB::table('translations')->where('translatable_type', PageBlock::class)->count(),
            'A mass delete left its translations behind.'
        );
    }

    /** Reseeding repeatedly must not accumulate anything. */
    public function test_reseeding_leaves_no_orphans_behind(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        foreach (range(1, 3) as $ignored) {
            $this->seed(PublicPagesSeeder::class);
        }

        $this->artisan('data:check')->assertSuccessful();
    }

    /* The check itself --------------------------------------------------------- */

    public function test_the_check_reports_orphans_and_fails_so_a_scheduler_notices(): void
    {
        $before = DB::table('translations')->count();
        $this->orphan();

        // Non-zero exit, or a nightly schedule reports nothing wrong.
        $this->artisan('data:check')->assertFailed();

        $this->assertSame($before + 1, DB::table('translations')->count(), 'The check removed rows without being asked to.');
    }

    public function test_the_check_clears_them_when_asked(): void
    {
        // The migrations seed translated names for the taxonomy terms; those
        // rows have their parent and must survive the fix.
        $before = DB::table('translations')->count();
        $this->orphan();

        $this->artisan('data:check --fix')->assertSuccessful();

        $this->assertSame($before, DB::table('translations')->count());
        $this->assertSame(0, DB::table('translations')->where('translatable_id', 999_999)->count());
    }

    public function test_a_clean_platform_reports_clean(): void
    {
        $this->artisan('data:check')
            ->expectsOutputToContain('No orphaned records.')
            ->assertSuccessful();
    }

    /**
     * A translation addressing a model the codebase has dropped is orphaned
     * in whole: there is no table left to check it against.
     */
    public function test_a_translation_for_a_class_that_no_longer_exists_is_an_orphan(): void
    {
        $before = DB::table('translations')->count();
        DB::table('translations')->insert([
            'translatable_type' => 'App\\Models\\SomethingRemoved',
            'translatable_id' => 1,
            'locale' => 'en',
            'field' => 'title',
            'value' => 'Left behind by a refactor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('data:check --fix')->assertSuccessful();

        $this->assertSame($before, DB::table('translations')->count());
        $this->assertSame(0, DB::table('translations')->where('translatable_type', 'App\\Models\\SomethingRemoved')->count());
    }

    /** The check runs nightly, so nobody has to remember it. */
    public function test_the_check_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command);

        $this->assertTrue(
            $events->contains(fn ($command) => str_contains((string) $command, 'data:check')),
            'The integrity check is not scheduled, so orphans accumulate silently again.'
        );
    }

    private function orphan(): void
    {
        DB::table('translations')->insert([
            'translatable_type' => Publication::class,
            'translatable_id' => 999_999,
            'locale' => 'fr',
            'field' => 'title',
            'value' => 'Orpheline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
