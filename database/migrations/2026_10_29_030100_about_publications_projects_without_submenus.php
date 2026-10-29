<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * About, Publications and Projects become plain links to their page. The
 * filters on Publications and Projects do what the sub-menus did, and the
 * About page is one page. The child items are hidden rather than deleted,
 * so the Secretariat sees the same menu in the back office and can show
 * them again; their URLs keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->children()->update(['is_visible' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $this->children()->update(['is_visible' => true, 'updated_at' => now()]);
    }

    private function children()
    {
        $roots = DB::table('menu_items')
            ->where('menu', 'header')
            ->whereNull('parent_id')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('destination_type', 'page')->where('destination', 'about'))
                ->orWhere(fn ($q) => $q->where('destination_type', 'route')->whereIn('destination', ['publications', 'projects'])))
            ->pluck('id');

        return DB::table('menu_items')->whereIn('parent_id', $roots);
    }
};
