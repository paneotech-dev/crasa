<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Two things the legacy newsroom carries that this one did not.
 *
 * PRESS RELEASE. Eleven of the legacy site's items are press releases, a
 * category it keeps separate from news, from tenders and from
 * consultations. Folding them into plain news loses a distinction CRASA
 * maintains, and a press release is the item a journalist looks for by
 * name.
 *
 * ARCHIVED. The legacy site has an Archived News section. The ruling is
 * that archived items stay visible, so this is not a status: a hidden item
 * would be a fifth status and would take the item off the site, which is
 * the opposite of what the section is for. It is a flag the newsroom
 * filters on and the listing labels.
 *
 * The category column is a string with the set enforced by the enum cast,
 * following the pattern set by the page status migration: the enum syntax
 * differs between MariaDB and SQLite and the suite runs on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function ($table) {
            $table->string('category_new', 24)->default('news')->after('category');
            $table->boolean('archived')->default(false)->after('status');
        });

        \Illuminate\Support\Facades\DB::table('news_items')->update(['category_new' => \Illuminate\Support\Facades\DB::raw('category')]);

        Schema::table('news_items', function ($table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });

        Schema::table('news_items', function ($table) {
            $table->renameColumn('category_new', 'category');
        });

        Schema::table('news_items', function ($table) {
            $table->index('category');
            $table->index('archived');
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('news_items')
            ->where('category', 'press_release')
            ->update(['category' => 'news']);

        Schema::table('news_items', function ($table) {
            $table->dropIndex(['archived']);
            $table->dropColumn('archived');
        });
    }
};
