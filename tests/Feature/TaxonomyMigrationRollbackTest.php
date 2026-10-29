<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\Publication;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The taxonomy migration rolls back to the enum-era schema and forward
 * again without losing which category or type an item had.
 */
class TaxonomyMigrationRollbackTest extends TestCase
{
    use DatabaseMigrations;

    public function test_down_and_up_keep_every_item_s_category_and_type(): void
    {
        $news = NewsItem::factory()->create(['category' => 'press_release']);
        $publication = Publication::factory()->create(['publication_type' => 'minutes']);

        $migration = require database_path('migrations/2026_10_29_190000_create_news_categories_and_publication_types.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('news_categories'));
        $this->assertFalse(Schema::hasTable('publication_types'));
        $this->assertFalse(Schema::hasColumn('sectors', 'sort_order'));
        $this->assertSame('press_release', DB::table('news_items')->where('id', $news->id)->value('category'));
        $this->assertSame('minutes', DB::table('publications')->where('id', $publication->id)->value('publication_type'));

        $migration->up();

        $this->assertSame(5, DB::table('news_categories')->count());
        $this->assertSame(6, DB::table('publication_types')->count());
        $this->assertSame('press_release', DB::table('news_items')->where('id', $news->id)->value('category'));
        $this->assertSame('minutes', DB::table('publications')->where('id', $publication->id)->value('publication_type'));
    }
}
