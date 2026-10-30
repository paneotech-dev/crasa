<?php

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageBlock;
use Database\Seeders\PartnerPageSeeder;
use Illuminate\Database\Migrations\Migration;

/*
 * The "Partner with us" page and its footer link (30 October 2026), on a
 * site that already carries its content (the About page exists). A fresh
 * database gets them from DatabaseSeeder instead. Insert only, by slug:
 * running this twice, or after an editor has changed the page, changes
 * nothing. See PartnerPageSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Page::where('slug', 'about')->exists()) {
            return;
        }

        (new PartnerPageSeeder)->run();
    }

    public function down(): void
    {
        foreach (MenuItem::where('destination_type', 'page')->where('destination', PartnerPageSeeder::SLUG)->get() as $item) {
            $item->translations()->delete();
            $item->delete();
        }

        $page = Page::withTrashed()->where('slug', PartnerPageSeeder::SLUG)->first();
        if ($page === null) {
            return;
        }

        foreach (PageBlock::where('page_id', $page->id)->get() as $block) {
            $block->translations()->delete();
            $block->delete();
        }
        $page->versions()->delete();
        $page->translations()->delete();
        $page->forceDelete();
    }
};
